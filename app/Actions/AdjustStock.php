<?php

namespace App\Actions;

use App\Enums\StockLocationType;
use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdjustStock
{
    public function execute(
        StockLocation $location,
        Product $product,
        int $targetQuantity,
        string $reason,
        User $actor,
        ?string $externalSource = null,
        bool $initialStock = false,
    ): ?StockMovement {
        if (! $actor->isAdmin()) {
            throw ValidationException::withMessages(['authorization' => 'Hanya Admin yang dapat menyesuaikan stok.']);
        }

        if ($targetQuantity < 0) {
            throw ValidationException::withMessages(['target_quantity' => 'Saldo stok tidak boleh negatif.']);
        }

        if (blank(trim($reason))) {
            throw ValidationException::withMessages(['reason' => 'Alasan penyesuaian wajib diisi.']);
        }

        if ($initialStock && $location->type !== StockLocationType::MainWarehouse) {
            throw ValidationException::withMessages(['stock_location_id' => 'Hasil produksi hanya boleh masuk ke Gudang Utama.']);
        }

        if ($initialStock && blank(trim((string) $externalSource))) {
            throw ValidationException::withMessages(['external_source' => 'Asal hasil produksi wajib diisi.']);
        }

        return DB::transaction(function () use ($location, $product, $targetQuantity, $reason, $actor, $externalSource, $initialStock): ?StockMovement {
            StockBalance::query()->firstOrCreate(
                ['stock_location_id' => $location->id, 'product_id' => $product->id],
                ['quantity_pieces' => 0],
            );

            $balance = StockBalance::query()
                ->where('stock_location_id', $location->id)
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->firstOrFail();

            $difference = $initialStock
                ? $targetQuantity
                : $targetQuantity - $balance->quantity_pieces;

            if ($difference === 0) {
                return null;
            }

            if ($initialStock && $difference < 1) {
                throw ValidationException::withMessages([
                    'target_quantity' => 'Jumlah hasil produksi harus lebih dari nol.',
                ]);
            }

            $movement = app(MoveStock::class)->execute(
                $product,
                $difference < 0 ? $location : null,
                $difference > 0 ? $location : null,
                abs($difference),
                $initialStock ? StockMovementType::ProductionReceipt : StockMovementType::Adjustment,
                $actor,
                notes: $reason,
            );

            if ($movement && $initialStock) {
                $movement->update(['external_source' => trim((string) $externalSource)]);
            }

            return $movement;
        });
    }
}
