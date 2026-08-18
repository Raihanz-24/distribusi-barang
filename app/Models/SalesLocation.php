<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesLocation extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'accuracy_meters' => 'float',
            'altitude_meters' => 'float',
            'heading_degrees' => 'float',
            'speed_mps' => 'float',
            'recorded_at' => 'datetime',
        ];
    }

    public function sales()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
