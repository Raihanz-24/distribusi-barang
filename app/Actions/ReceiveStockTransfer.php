<?php

namespace App\Actions;

use App\Enums\StockMovementType;
use App\Enums\StockTransferStatus;
use App\Enums\StockTransferType;
use App\Models\StockTransfer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReceiveStockTransfer
{
    public function execute(StockTransfer $transfer, array $receivedItems, User $actor, ?string $notes = null): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $receivedItems, $actor, $notes): StockTransfer {
            $lockedTransfer = StockTransfer::query()
                ->with(['items.product', 'sourceLocation', 'destinationLocation'])
                ->whereKey($transfer->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedTransfer->status !== StockTransferStatus::Issued) {
                throw ValidationException::withMessages(['status' => 'Transfer belum diserahkan atau sudah diterima.']);
            }

            $canReceive = match ($lockedTransfer->transfer_type) {
                StockTransferType::IssueToSales => $actor->isAdmin() || $lockedTransfer->sales_id === $actor->id,
                StockTransferType::WarehouseTransfer, StockTransferType::ReturnFromSales => $actor->isAdmin(),
            };

            if (! $canReceive) {
                throw ValidationException::withMessages(['authorization' => 'Anda tidak berhak mengonfirmasi mutasi ini.']);
            }

            $receivedById = collect($receivedItems)->mapWithKeys(
                fn (array $item) => [(int) $item['item_id'] => (int) $item['quantity_received']]
            );

            if ($receivedById->keys()->sort()->values()->all() !== $lockedTransfer->items->pluck('id')->sort()->values()->all()) {
                throw ValidationException::withMessages(['items' => 'Daftar produk penerimaan tidak lengkap.']);
            }

            $hasDiscrepancy = false;

            foreach ($lockedTransfer->items as $item) {
                $received = $receivedById[$item->id];

                if ($received < 0) {
                    throw ValidationException::withMessages(['items' => 'Jumlah diterima tidak boleh negatif.']);
                }

                $difference = $received - $item->quantity_issued;
                $hasDiscrepancy = $hasDiscrepancy || $difference !== 0;

                $item->update([
                    'quantity_received' => $received,
                    'discrepancy_quantity' => $difference,
                ]);

                if ($received > 0) {
                    $movementType = match ($lockedTransfer->transfer_type) {
                        StockTransferType::WarehouseTransfer => StockMovementType::WarehouseTransfer,
                        StockTransferType::IssueToSales => StockMovementType::TransferToSales,
                        StockTransferType::ReturnFromSales => StockMovementType::SalesReturnToWarehouse,
                    };

                    app(MoveStock::class)->execute(
                        $item->product,
                        $lockedTransfer->sourceLocation,
                        $lockedTransfer->destinationLocation,
                        $received,
                        $movementType,
                        $actor,
                        $lockedTransfer,
                        "Penerimaan {$lockedTransfer->number}",
                    );
                }
            }

            if ($hasDiscrepancy && blank(trim((string) $notes))) {
                throw ValidationException::withMessages([
                    'discrepancy_notes' => 'Catatan wajib diisi jika jumlah diterima berbeda.',
                ]);
            }

            $lockedTransfer->update([
                'status' => $hasDiscrepancy ? StockTransferStatus::Discrepancy : StockTransferStatus::Received,
                'discrepancy_notes' => $hasDiscrepancy ? $notes : null,
                'received_by' => $actor->id,
                'received_at' => now(),
            ]);

            return $lockedTransfer->refresh();
        });
    }
}
