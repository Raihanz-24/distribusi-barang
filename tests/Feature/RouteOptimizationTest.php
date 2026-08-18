<?php

namespace Tests\Feature;

use App\Actions\OptimizeDailyRoute;
use App\Data\Coordinate;
use App\Models\DailyRoute;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouteOptimizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_osrm_failure_falls_back_and_persists_order(): void
    {
        config(['maps.routing_provider' => 'osrm', 'maps.osrm_base_url' => null]);
        $admin = User::factory()->admin()->create();
        $route = DailyRoute::factory()->create([
            'created_by' => $admin->id,
            'start_latitude' => -8.58,
            'start_longitude' => 116.10,
        ]);
        $stores = Store::factory()->count(3)->sequence(
            ['latitude' => -8.57, 'longitude' => 116.11],
            ['latitude' => -8.56, 'longitude' => 116.12],
            ['latitude' => -8.59, 'longitude' => 116.09],
        )->create(['created_by' => $admin->id]);
        $route->stores()->attach($stores->pluck('id'));

        $result = app(OptimizeDailyRoute::class)->execute($route, $admin, new Coordinate(-8.58, 116.10));

        $this->assertSame('local-fallback', $result->provider);
        $this->assertEqualsCanonicalizing($stores->pluck('id')->all(), $result->orderedStoreIds);
        $this->assertSame([1, 2, 3], $route->routeStores()->orderBy('optimized_order')->pluck('optimized_order')->all());
        $this->assertDatabaseHas('route_optimization_runs', ['daily_route_id' => $route->id, 'status' => 'completed']);
    }
}
