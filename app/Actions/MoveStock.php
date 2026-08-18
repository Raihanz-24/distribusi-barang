<?php

namespace App\Actions;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MoveStock
{
    public function execute(
        Product $product,
        ?StockLocation $source,
        ?StockLocation $destination,
        int $quantityPieces,
        StockMovementType $type,
        User $actor,
        ?Model $reference = null,
        ?string $notes = null,
    ): StockMovement {
        if ($quantityPieces < 1) {
            throw ValidationException::withMessages(['quantity_pieces' => 'Jumlah stok harus lebih dari nol.']);
        }

        if (! $source && ! $destination) {
            throw ValidationException::withMessages(['stock_location' => 'Sumber atau tujuan stok wajib diisi.']);
        }

        if ($source?->is($destination)) {
            throw ValidationException::withMessages(['stock_location' => 'Lokasi sumber dan tujuan harus berbeda.']);
        }

        return DB::transaction(function () use (
            $product,
            $source,
            $destination,
            $quantityPieces,
            $type,
            $actor,
            $reference,
            $notes,
        ): StockMovement {
            $sourceBalance = $source
                ? $this->lockedBalance($source, $product)
                : null;
            $destinationBalance = $destination
                ? $this->lockedBalance($destination, $product)
                : null;

            if ($sourceBalance && $sourceBalance->quantity_pieces < $quantityPieces) {
                throw ValidationException::withMessages([
                    'quantity_pieces' => "Stok {$product->name} di {$source->name} tidak mencukupi. "
                        ."Tersedia {$sourceBalance->quantity_pieces} bungkus, dibutuhkan {$quantityPieces} bungkus.",
                ]);
            }

            if ($sourceBalance) {
                $sourceBalance->decrement('quantity_pieces', $quantityPieces);
                $sourceBalance->refresh();
            }

            if ($destinationBalance) {
                $destinationBalance->increment('quantity_pieces', $quantityPieces);
                $destinationBalance->refresh();
            }

            return StockMovement::create([
                'product_id' => $product->id,
                'source_location_id' => $source?->id,
                'destination_location_id' => $destination?->id,
                'quantity_pieces' => $quantityPieces,
                'movement_type' => $type,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'occurred_at' => now(),
                'created_by' => $actor->id,
                'notes' => $notes,
                'source_balance_after' => $sourceBalance?->quantity_pieces,
                'destination_balance_after' => $destinationBalance?->quantity_pieces,
            ]);
        });
    }

    private function lockedBalance(StockLocation $location, Product $product): StockBalance
    {
        StockBalance::query()->firstOrCreate(
            ['stock_location_id' => $location->id, 'product_id' => $product->id],
            ['quantity_pieces' => 0],
        );

        return StockBalance::query()
            ->where('stock_location_id', $location->id)
            ->where('product_id', $product->id)
            ->lockForUpdate()
            ->firstOrFail();
    }
}
