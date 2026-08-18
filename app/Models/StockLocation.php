<?php

namespace App\Models;

use App\Enums\StockLocationType;
use Illuminate\Database\Eloquent\Model;

class StockLocation extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => StockLocationType::class,
            'is_active' => 'boolean',
        ];
    }

    public function balances()
    {
        return $this->hasMany(StockBalance::class);
    }
}
