<?php

namespace App\Actions;

use App\Enums\DailyRouteStatus;
use App\Enums\SalesSettlementStatus;
use App\Enums\StockMovementType;
use App\Enums\StockTransferStatus;
use App\Models\DailySalesSettlement;
use App\Models\StockTransfer;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApproveSalesReturn
{
    public function execute(
        StockTransfer $return,
        User $admin,
        ?int $actualCashReceived = null,
        ?array $receivedItems = null,
        ?string $notes = null,
    ): DailySalesSettlement {
        if (! $admin->isAdmin()) {
            throw ValidationException::withMessages(['authorization' => 'Hanya Admin yang dapat menyetujui pengembalian.']);
        }

        return DB::transaction(function () use ($return, $admin, $actualCashReceived, $receivedItems, $notes): DailySalesSettlement {
            $locked = StockTransfer::query()->lockForUpdate()->findOrFail($return->id);

            if ($locked->status !== StockTransferStatus::PendingApproval) {
                throw ValidationException::withMessages(['status' => 'Pengajuan sudah diproses atau belum diajukan.']);
            }

            $locked->load(['items.product', 'sourceLocation', 'destinationLocation']);
            $settlement = DailySalesSettlement::query()
                ->where('return_transfer_id', $locked->id)
                ->lockForUpdate()
                ->firstOrFail();
            $actualCash = $actualCashReceived ?? (int) $settlement->total_collection;

            if ($actualCash < 0) {
                throw ValidationException::withMessages(['actual_cash_received' => 'Uang fisik yang diterima tidak boleh negatif.']);
            }

            $receivedById = $receivedItems === null
                ? $locked->items->mapWithKeys(fn ($item) => [$item->id => $item->quantity_issued])
                : collect($receivedItems)->mapWithKeys(fn (array $item) => [
                    (int) $item['item_id'] => (int) $item['quantity_received'],
                ]);

            if ($receivedById->keys()->sort()->values()->all() !== $locked->items->pluck('id')->sort()->values()->all()) {
                throw ValidationException::withMessages(['received_items' => 'Daftar pemeriksaan stok pengembalian tidak lengkap.']);
            }

            $stockShortage = 0;
            foreach ($locked->items as $item) {
                $received = (int) $receivedById[$item->id];

                if ($received < 0 || $received > $item->quantity_issued) {
                    throw ValidationException::withMessages([
                        'received_items' => "Jumlah aktual {$item->product->name} harus antara 0 dan {$item->quantity_issued} bungkus.",
                    ]);
                }

                $stockShortage += $item->quantity_issued - $received;
            }

            $cashDifference = $actualCash - (int) $settlement->total_collection;
            if (($cashDifference !== 0 || $stockShortage > 0) && blank(trim((string) $notes))) {
                throw ValidationException::withMessages([
                    'verification_notes' => 'Catatan pemeriksaan wajib diisi jika uang atau stok fisik memiliki selisih.',
                ]);
            }

            if ($locked->items->isEmpty()) {
                $locked->update([
                    'status' => StockTransferStatus::Received,
                    'issued_by' => $admin->id,
                    'issued_at' => now(),
                    'received_by' => $admin->id,
                    'received_at' => now(),
                ]);
            } else {
                $locked->update(['status' => StockTransferStatus::Draft]);
                app(IssueStockTransfer::class)->execute($locked, $admin);
                app(ReceiveStockTransfer::class)->execute(
                    $locked,
                    $locked->items->map(fn ($item) => [
                        'item_id' => $item->id,
                        'quantity_received' => (int) $receivedById[$item->id],
                    ])->all(),
                    $admin,
                    $notes,
                );
            }

            if ($stockShortage > 0) {
                foreach ($locked->items as $item) {
                    $shortage = $item->quantity_issued - (int) $receivedById[$item->id];
                    if ($shortage < 1) {
                        continue;
                    }

                    app(MoveStock::class)->execute(
                        $item->product,
                        $locked->sourceLocation,
                        null,
                        $shortage,
                        StockMovementType::Loss,
                        $admin,
                        $locked,
                        "Selisih closing {$locked->number}: ".trim((string) $notes),
                    );
                }
            }

            $settlement->update([
                'status' => SalesSettlementStatus::Approved,
                'actual_cash_received' => $actualCash,
                'cash_difference' => $cashDifference,
                'stock_shortage_pieces' => $stockShortage,
                'verification_notes' => filled($notes) ? trim($notes) : null,
                'approved_by' => $admin->id,
                'approved_at' => now(),
            ]);

            $settlement->dailyRoute?->update([
                'status' => DailyRouteStatus::Completed,
                'completed_at' => now(),
            ]);

            app(AuditLogger::class)->record(
                'distribution.closing_approved',
                $settlement,
                $admin,
                oldValues: ['status' => SalesSettlementStatus::Pending->value],
                newValues: [
                    'status' => SalesSettlementStatus::Approved->value,
                    'expected_cash' => (int) $settlement->total_collection,
                    'actual_cash' => $actualCash,
                    'cash_difference' => $cashDifference,
                    'stock_shortage_pieces' => $stockShortage,
                ],
                description: 'Admin menyelesaikan pemeriksaan closing harian.',
            );

            return $settlement->refresh();
        });
    }
}
