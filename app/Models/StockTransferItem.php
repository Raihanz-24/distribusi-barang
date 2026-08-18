<?php

namespace App\Models;

use App\Enums\StockUnit;
use Illuminate\Database\Eloquent\Model;

class StockTransferItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'quantity_issued' => 'integer',
            'quantity_entered' => 'integer',
            'quantity_unit' => StockUnit::class,
            'quantity_received' => 'integer',
            'discrepancy_quantity' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (StockTransferItem $item): void {
            if (! $item->quantity_entered || ! $item->quantity_unit || ! $item->product_id) {
                return;
            }

            $unit = $item->quantity_unit instanceof StockUnit
                ? $item->quantity_unit
                : StockUnit::from($item->quantity_unit);
            $product = Product::query()->findOrFail($item->product_id);

            $item->quantity_issued = $product->convertStockToPieces($item->quantity_entered, $unit);
        });
    }

    public function transfer()
    {
        return $this->belongsTo(StockTransfer::class, 'stock_transfer_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
