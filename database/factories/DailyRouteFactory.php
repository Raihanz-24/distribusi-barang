<?php

namespace Database\Factories;

use App\Enums\DailyRouteStatus;
use App\Enums\OptimizationStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DailyRouteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sales_id' => User::factory(),
            'route_date' => now()->toDateString(),
            'status' => DailyRouteStatus::Scheduled,
            'optimization_status' => OptimizationStatus::Pending,
            'created_by' => User::factory()->admin(),
        ];
    }
}
