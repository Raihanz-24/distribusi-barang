<?php

namespace App\Models;

use Database\Factories\ProductPriceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ProductPrice extends Model
{
    /** @use HasFactory<ProductPriceFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'price_per_renteng' => 'integer',
            'effective_from' => 'date',
            'effective_until' => 'date',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (ProductPrice $price): void {
            $price->created_by ??= Auth::id();
        });
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
