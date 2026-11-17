<?php

namespace App\Actions;

use App\Enums\StockTransferStatus;
use App\Enums\StockTransferType;
use App\Models\DailySalesSettlement;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\StoreAccountEntry;
use App\Models\StoreVisit;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\StockLocationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Menghapus seluruh distribusi Sales yang masih berjalan beserta record
 * terkaitnya. Digunakan khusus Developer untuk membersihkan data testing.
 *
 * Stok yang sedang dibawa Sales dihapus dari saldo (tidak dikembalikan),
 * sesuai tujuan "dianggap tidak pernah ada".
 */
class PurgeSalesDistribution
{
    public function execute(StockTransfer $transfer, User $actor): void
    {
        if (! $actor->isDeveloper()) {
            throw ValidationException::withMessages([
                'authorization' => 'Hanya Developer yang dapat menghapus distribusi.',
            ]);
        }

        DB::transaction(function () use ($transfer, $actor): void {
            $pickup = $this->resolvePickup($transfer);

            $locked = StockTransfer::query()
                ->with(['items', 'returnTransfer.items', 'dailyRoute'])
                ->lockForUpdate()
                ->findOrFail($pickup->id);

            $return = $locked->returnTransfer;
            $route = $locked->dailyRoute;

            if ($locked->status === StockTransferStatus::Draft
                || $locked->status === StockTransferStatus::Cancelled) {
                throw ValidationException::withMessages([
                    'status' => 'Distribusi masih draf atau sudah dibatalkan, tidak perlu dihapus.',
                ]);
            }

            $transferIds = collect([$locked->id])
                ->merge($return ? [$return->id] : [])
                ->all();

            $visitIds = $route
                ? StoreVisit::query()->where('daily_route_id', $route->id)->pluck('id')
                : collect();

            // Kumpulkan mutasi stok milik distribusi ini agar saldo yang
            // tersentuh (bawaan Sales dan titipan toko) bisa dibersihkan.
            $movementQuery = StockMovement::query()->where(function ($query) use ($transferIds, $visitIds): void {
                $query->where(function ($query) use ($transferIds): void {
                    $query->where('reference_type', StockTransfer::class)
                        ->whereIn('reference_id', $transferIds);
                });

                if ($visitIds->isNotEmpty()) {
                    $query->orWhere(function ($query) use ($visitIds): void {
                        $query->where('reference_type', StoreVisit::class)
                            ->whereIn('reference_id', $visitIds);
                    });
                }
            });

            $affectedBalances = $movementQuery->get()
                ->flatMap(fn (StockMovement $movement): array => [
                    ['location_id' => $movement->source_location_id, 'product_id' => $movement->product_id],
                    ['location_id' => $movement->destination_location_id, 'product_id' => $movement->product_id],
                ])
                ->filter(fn (array $balance): bool => $balance['location_id'] !== null)
                ->unique(fn (array $balance): string => $balance['location_id'].':'.$balance['product_id']);

            // 1. Hapus mutasi stok milik distribusi ini.
            $movementQuery->delete();

            // 2. Hapus saldo stok bawaan Sales (stok yang dibawa hilang).
            $salesLocation = app(StockLocationService::class)->forSales($locked->sales);
            StockBalance::query()
                ->where('stock_location_id', $salesLocation->id)
                ->whereIn('product_id', $locked->items->pluck('product_id')->all())
                ->delete();

            // 3. Hapus saldo titipan toko yang tersentuh distribusi ini.
            foreach ($affectedBalances as $balance) {
                if ($balance['location_id'] === $salesLocation->id) {
                    continue;
                }

                StockBalance::query()
                    ->where('stock_location_id', $balance['location_id'])
                    ->where('product_id', $balance['product_id'])
                    ->delete();
            }

            // 4. Hapus jurnal piutang toko dari kunjungan distribusi ini.
            if ($visitIds->isNotEmpty()) {
                StoreAccountEntry::query()->whereIn('store_visit_id', $visitIds)->delete();
            }

            // 5. Hapus kunjungan toko (item kunjungan ikut terhapus via cascade).
            if ($visitIds->isNotEmpty()) {
                StoreVisit::query()->whereIn('id', $visitIds)->delete();
            }

            // 6. Hapus settlement sebelum transfer (foreign key restrictOnDelete).
            DailySalesSettlement::query()
                ->whereIn('pickup_transfer_id', $transferIds)
                ->orWhereIn('return_transfer_id', $transferIds)
                ->delete();

            // 7. Hapus pengajuan pengembalian, lalu pengambilan (item ikut terhapus).
            $return?->delete();
            $locked->delete();

            // 8. Hapus rute (route stores, area, optimization ikut terhapus).
            $route?->delete();

            app(AuditLogger::class)->record(
                'distribution.purged',
                actor: $actor,
                newValues: [
                    'pickup_number' => $locked->number,
                    'return_number' => $return?->number,
                    'sales_id' => $locked->sales_id,
                    'route_id' => $route?->id,
                ],
                description: "Developer menghapus distribusi {$locked->number} beserta seluruh record terkait.",
            );
        });
    }

    private function resolvePickup(StockTransfer $transfer): StockTransfer
    {
        if ($transfer->transfer_type === StockTransferType::IssueToSales) {
            return $transfer;
        }

        if ($transfer->transfer_type === StockTransferType::ReturnFromSales) {
            $pickup = $transfer->pickupTransfer()->first();

            if ($pickup) {
                return $pickup;
            }

            throw ValidationException::withMessages([
                'type' => 'Dokumen pengajuan ini tidak terhubung dengan pengambilan Sales.',
            ]);
        }

        throw ValidationException::withMessages([
            'type' => 'Hanya distribusi pengambilan Sales yang dapat dihapus.',
        ]);
    }
}
