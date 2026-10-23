<?php

namespace App\Actions;

use App\Enums\DailyRouteStatus;
use App\Enums\SalesSettlementStatus;
use App\Enums\StockTransferStatus;
use App\Enums\StockTransferType;
use App\Models\DailyRoute;
use App\Models\DailySalesSettlement;
use App\Models\StockBalance;
use App\Models\StockTransfer;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\StockLocationService;
use App\Services\StoreSettlementCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitSalesReturn
{
    /**
     * @param  User  $actor  Sales pemilik pengambilan, atau Admin yang mengajukan
     *                       atas nama Sales (mis. saat Admin mensimulasikan alur).
     */
    public function execute(StockTransfer $pickup, User $actor): DailySalesSettlement
    {
        if (! $actor->isAdmin() && (! $actor->isSales() || $pickup->sales_id !== $actor->id)) {
            throw ValidationException::withMessages(['authorization' => 'Pengajuan hanya dapat dibuat oleh Sales pemilik transaksi atau Admin.']);
        }

        if ($pickup->transfer_type !== StockTransferType::IssueToSales || $pickup->status !== StockTransferStatus::Received) {
            throw ValidationException::withMessages(['status' => 'Stok harus sudah diambil sebelum pengembalian diajukan.']);
        }

        if ($pickup->returnTransfer()->exists()) {
            throw ValidationException::withMessages(['status' => 'Pengajuan pengembalian sudah pernah dibuat.']);
        }

        $sales = User::query()->findOrFail($pickup->sales_id);

        return DB::transaction(function () use ($pickup, $sales, $actor): DailySalesSettlement {
            $route = DailyRoute::query()
                ->withCount([
                    'routeStores as unfinished_stores_count' => fn ($query) => $query->whereDoesntHave('latestVisit'),
                ])
                ->whereKey($pickup->daily_route_id)
                ->where('sales_id', $sales->id)
                ->first();

            if (! $route) {
                throw ValidationException::withMessages([
                    'route' => 'Jadwal kerja yang terhubung dengan pengiriman tidak ditemukan. Hubungi Admin sebelum menyelesaikan distribusi.',
                ]);
            }

            if ($route->unfinished_stores_count > 0) {
                throw ValidationException::withMessages([
                    'route' => "Masih ada {$route->unfinished_stores_count} toko yang belum diproses. Selesaikan atau catat hasil kunjungannya terlebih dahulu.",
                ]);
            }

            $locations = app(StockLocationService::class);
            $salesLocation = $locations->forSales($sales);
            $adminWarehouse = $locations->adminWarehouse();
            $balances = StockBalance::query()
                ->where('stock_location_id', $salesLocation->id)
                ->where('quantity_pieces', '>', 0)
                ->get();
            $return = StockTransfer::create([
                'transfer_type' => StockTransferType::ReturnFromSales,
                'pickup_transfer_id' => $pickup->id,
                'source_location_id' => $salesLocation->id,
                'destination_location_id' => $adminWarehouse->id,
                'sales_id' => $sales->id,
                'status' => StockTransferStatus::PendingApproval,
                'created_by' => $actor->id,
                'notes' => 'Pengajuan pengembalian stok setelah kunjungan toko.',
            ]);

            foreach ($balances as $balance) {
                $return->items()->create([
                    'product_id' => $balance->product_id,
                    'quantity_entered' => $balance->quantity_pieces,
                    'quantity_unit' => 'piece',
                    'quantity_issued' => $balance->quantity_pieces,
                ]);
            }

            $visits = $route?->visits()->with('items')->get() ?? collect();
            $items = $visits->flatMap->items;
            $collection = (int) $visits->sum('amount_paid');
            $fee = app(StoreSettlementCalculator::class)->salesFee($collection);

            $settlement = DailySalesSettlement::create([
                'pickup_transfer_id' => $pickup->id,
                'return_transfer_id' => $return->id,
                'daily_route_id' => $route?->id,
                'sales_id' => $sales->id,
                'settlement_date' => $route->route_date,
                'status' => SalesSettlementStatus::Pending,
                'total_stores' => $visits->filter(fn ($visit) => $visit->items->isNotEmpty() || $visit->amount_paid > 0)->count(),
                'total_sold_pieces' => $items->sum('sold_pieces'),
                'total_deposited_pieces' => $items->sum('deposited_pieces'),
                'total_withdrawn_pieces' => $items->sum('withdrawn_pieces'),
                'total_collection' => $collection,
                'equivalent_renteng' => $fee['equivalent_renteng'],
                'sales_fee' => $fee['fee'],
                'submitted_at' => now(),
            ]);

            $route->update([
                'status' => DailyRouteStatus::PendingReview,
            ]);

            app(AuditLogger::class)->record(
                'distribution.closing_submitted',
                $settlement,
                $actor,
                newValues: [
                    'expected_cash' => $collection,
                    'return_transfer_id' => $return->id,
                    'route_id' => $route->id,
                    'submitted_by_admin' => $actor->isAdmin(),
                ],
                description: $actor->isAdmin()
                    ? 'Admin mengajukan pemeriksaan closing harian atas nama Sales.'
                    : 'Sales mengajukan pemeriksaan closing harian.',
            );

            return $settlement;
        });
    }
}
