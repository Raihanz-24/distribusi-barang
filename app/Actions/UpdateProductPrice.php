<?php

namespace App\Actions;

use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class UpdateProductPrice
{
    public function execute(
        Product $product,
        int $pricePerRenteng,
        User $creator,
    ): ProductPrice {
        return DB::transaction(function () use ($product, $pricePerRenteng, $creator): ProductPrice {
            $lockedProduct = Product::query()->whereKey($product->getKey())->lockForUpdate()->firstOrFail();

            $currentPrice = ProductPrice::query()
                ->where('product_id', $product->id)
                ->where('is_active', true)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (
                $currentPrice
                && $currentPrice->price_per_renteng === $pricePerRenteng
            ) {
                return $currentPrice;
            }

            ProductPrice::query()
                ->where('product_id', $product->id)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            $newPrice = ProductPrice::create([
                'product_id' => $lockedProduct->id,
                'price_per_renteng' => $pricePerRenteng,
                'effective_from' => today(),
                'effective_until' => null,
                'is_active' => true,
                'created_by' => $creator->id,
            ]);

            app(AuditLogger::class)->record(
                'product.price_updated',
                $lockedProduct,
                $creator,
                oldValues: ['price_per_renteng' => $currentPrice?->price_per_renteng],
                newValues: ['price_per_renteng' => $pricePerRenteng],
                description: 'Harga produk diperbarui dan versi harga lama dipertahankan.',
            );

            return $newPrice;
        });
    }
}
