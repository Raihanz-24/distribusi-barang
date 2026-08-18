<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RouteOptimizationRun extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'start_coordinate' => 'array',
            'input_store_ids' => 'array',
            'result_order' => 'array',
            'geometry' => 'array',
            'request_payload' => 'array',
            'response_payload' => 'array',
            'executed_at' => 'datetime',
        ];
    }
}
