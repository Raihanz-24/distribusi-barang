<?php

namespace App\Models;

use App\Enums\StockMovementType;
use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'movement_type' => StockMovementType::class,
            'quantity_pieces' => 'integer',
            'occurred_at' => 'datetime',
            'source_balance_after' => 'integer',
            'destination_balance_after' => 'integer',
        ];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function sourceLocation()
    {
        return $this->belongsTo(StockLocation::class, 'source_location_id');
    }

    public function destinationLocation()
    {
        return $this->belongsTo(StockLocation::class, 'destination_location_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reference()
    {
        return $this->morphTo();
    }
}
