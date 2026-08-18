<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreVisitItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'sold_pieces' => 'integer',
            'deposited_pieces' => 'integer',
            'withdrawn_pieces' => 'integer',
            'retail_price_per_piece' => 'integer',
            'price_per_renteng' => 'integer',
            'store_fee' => 'integer',
            'amount_due' => 'integer',
        ];
    }

    public function visit()
    {
        return $this->belongsTo(StoreVisit::class, 'store_visit_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
