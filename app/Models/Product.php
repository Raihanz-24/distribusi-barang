<?php

namespace App\Models;

use App\Enums\StockUnit;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'pieces_per_renteng' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Product $product): void {
            $product->code = Str::upper(trim($product->code));
            $product->slug = Str::slug($product->slug ?: $product->name);
        });
    }

    public function prices()
    {
        return $this->hasMany(ProductPrice::class)->latest('effective_from');
    }

    public function currentPrice()
    {
        return $this->hasOne(ProductPrice::class)
            ->ofMany(
                ['id' => 'max'],
                fn ($query) => $query->where('is_active', true)
            );
    }

    public function convertStockToPieces(int $quantity, StockUnit $unit): int
    {
        return $quantity * ($unit === StockUnit::Renteng
            ? max(1, $this->pieces_per_renteng)
            : 1);
    }

    public function formatStockQuantity(int $pieces): string
    {
        $piecesPerRenteng = max(1, $this->pieces_per_renteng);
        $renteng = intdiv($pieces, $piecesPerRenteng);
        $remainder = $pieces % $piecesPerRenteng;

        if ($renteng === 0) {
            return number_format($pieces).' bungkus';
        }

        $converted = number_format($renteng).' renteng';

        if ($remainder > 0) {
            $converted .= ' + '.number_format($remainder).' bungkus';
        }

        return $converted.' ('.number_format($pieces).' bungkus)';
    }
}
