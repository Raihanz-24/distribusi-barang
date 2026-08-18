<?php

namespace App\Actions;

use App\Enums\StockLocationType;
use App\Enums\StockTransferStatus;
use App\Enums\StockTransferType;
use App\Models\StockBalance;
use App\Models\StockTransfer;
use App\Models\User;
use App\Services\StockLocationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IssueStockTransfer
{
    public function execute(StockTransfer $transfer, User $actor): StockTransfer
    {
        if (! $actor->isAdmin()) {
            throw ValidationException::withMessages(['authorization' => 'Hanya Admin yang dapat menyerahkan stok.']);
        }

        return DB::transaction(function () use ($transfer, $actor): StockTransfer {
            $lockedTransfer = StockTransfer::query()
                ->with(['items.product', 'sourceLocation', 'destinationLocation'])
                ->whereKey($transfer->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedTransfer->status !== StockTransferStatus::Draft) {
                throw ValidationException::withMessages(['status' => 'Hanya transfer draf yang dapat diserahkan.']);
            }

            if ($lockedTransfer->items->isEmpty()) {
                throw ValidationException::withMessages(['items' => 'Tambahkan minimal satu produk.']);
            }

            $locations = app(StockLocationService::class);
            $normalizedLocations = match ($lockedTransfer->transfer_type) {
                StockTransferType::WarehouseTransfer => [
                    $locations->mainWarehouse()->id,
                    $locations->adminWarehouse()->id,
                ],
                StockTransferType::IssueToSales => [
                    $locations->adminWarehouse()->id,
                    $locations->forSales($lockedTransfer->sales)->id,
                ],
                StockTransferType::ReturnFromSales => [
                    $locations->forSales($lockedTransfer->sales)->id,
                    $locations->adminWarehouse()->id,
                ],
            };

            $lockedTransfer->update([
                'source_location_id' => $normalizedLocations[0],
                'destination_location_id' => $normalizedLocations[1],
            ]);
            $lockedTransfer->unsetRelation('sourceLocation');
            $lockedTransfer->unsetRelation('destinationLocation');
            $lockedTransfer->load(['sourceLocation', 'destinationLocation']);

            $sourceType = $lockedTransfer->sourceLocation->type;
            $destinationType = $lockedTransfer->destinationLocation->type;
            $validFlow = match ($lockedTransfer->transfer_type) {
                StockTransferType::WarehouseTransfer => $sourceType === StockLocationType::MainWarehouse
                    && $destinationType === StockLocationType::AdminWarehouse,
                StockTransferType::IssueToSales => $sourceType === StockLocationType::AdminWarehouse
                    && $destinationType === StockLocationType::Sales
                    && $lockedTransfer->destinationLocation->reference_id === $lockedTransfer->sales_id,
                StockTransferType::ReturnFromSales => $sourceType === StockLocationType::Sales
                    && $lockedTransfer->sourceLocation->reference_id === $lockedTransfer->sales_id
                    && $destinationType === StockLocationType::AdminWarehouse,
            };

            if (! $validFlow) {
                throw ValidationException::withMessages(['locations' => 'Kombinasi lokasi mutasi tidak valid.']);
            }

            foreach ($lockedTransfer->items as $item) {
                $available = (int) StockBalance::query()
                    ->where('stock_location_id', $lockedTransfer->source_location_id)
                    ->where('product_id', $item->product_id)
                    ->lockForUpdate()
                    ->value('quantity_pieces');

                if ($available < $item->quantity_issued) {
                    throw ValidationException::withMessages([
                        'items' => "Stok {$item->product->name} di {$lockedTransfer->sourceLocation->name} tidak mencukupi. "
                            ."Tersedia {$available} bungkus, dibutuhkan {$item->quantity_issued} bungkus.",
                    ]);
                }
            }

            $lockedTransfer->update([
                'status' => StockTransferStatus::Issued,
                'issued_by' => $actor->id,
                'issued_at' => now(),
            ]);

            return $lockedTransfer->refresh();
        });
    }
}
