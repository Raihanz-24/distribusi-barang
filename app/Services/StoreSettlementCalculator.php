<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Validation\ValidationException;

class StoreSettlementCalculator
{
    public function calculate(Product $product, int $soldPieces): array
    {
        $soldPieces = max(0, $soldPieces);
        $piecesPerRenteng = max(1, $product->pieces_per_renteng);
        $fullRenteng = intdiv($soldPieces, $piecesPerRenteng);
        $remainder = $soldPieces % $piecesPerRenteng;
        $rentengPrice = (int) ($product->currentPrice?->price_per_renteng ?? 0);

        if ($rentengPrice < 1) {
            throw ValidationException::withMessages([
                'product_price' => "Harga aktif {$product->name} belum tersedia. Hubungi Admin sebelum mencatat transaksi.",
            ]);
        }
        $retailPrice = (int) config('sales.retail_price_per_piece');
        $partialFee = match (true) {
            $remainder === 0 => 0,
            $remainder <= 5 => 1_000,
            $remainder <= 9 => 2_000,
            default => 3_000,
        };
        $storeFee = ($fullRenteng * 4_000) + $partialFee;
        $amountDue = ($fullRenteng * $rentengPrice)
            + max(0, ($remainder * $retailPrice) - $partialFee);

        return [
            'sold_pieces' => $soldPieces,
            'full_renteng' => $fullRenteng,
            'remainder_pieces' => $remainder,
            'retail_price_per_piece' => $retailPrice,
            'price_per_renteng' => $rentengPrice,
            'store_fee' => $storeFee,
            'amount_due' => $amountDue,
        ];
    }

    public function salesFee(int $finalCollection): array
    {
        $basis = max(1, (int) config('sales.sales_fee_basis'));
        $equivalentRenteng = intdiv(max(0, $finalCollection), $basis);

        return [
            'equivalent_renteng' => $equivalentRenteng,
            'fee' => $equivalentRenteng * (int) config('sales.sales_fee_per_equivalent_renteng'),
        ];
    }
}
