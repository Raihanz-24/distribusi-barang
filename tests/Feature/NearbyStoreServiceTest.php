<?php

namespace Tests\Feature;

use App\Data\Coordinate;
use App\Models\DailyRoute;
use App\Models\Store;
use App\Models\User;
use App\Services\NearbyStoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NearbyStoreServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_stores_inside_route_are_prioritized_then_sorted_by_distance(): void
    {
        $sales = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $position = new Coordinate(-8.5800, 116.1000);
        $outsideNear = Store::factory()->create([
            'created_by' => $admin->id, 'latitude' => -8.5801, 'longitude' => 116.1000,
        ]);
        $insideFarther = Store::factory()->create([
            'created_by' => $admin->id, 'latitude' => -8.5805, 'longitude' => 116.1000,
        ]);
        Store::factory()->create([
            'created_by' => $admin->id, 'latitude' => -8.6000, 'longitude' => 116.1000,
        ]);
        $route = DailyRoute::factory()->create([
            'sales_id' => $sales->id, 'created_by' => $admin->id, 'route_date' => today(),
        ]);
        $route->stores()->attach($insideFarther->id);

        $results = app(NearbyStoreService::class)->find($sales, $position, 150, 5);

        $this->assertSame($insideFarther->id, $results->first()->id);
        $this->assertTrue($results->first()->in_today_route);
        $this->assertSame($outsideNear->id, $results->get(1)->id);
        $this->assertCount(2, $results);
    }
}
