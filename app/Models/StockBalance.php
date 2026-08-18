<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockBalance extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['quantity_pieces' => 'integer'];
    }

    public function location()
    {
        return $this->belongsTo(StockLocation::class, 'stock_location_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
