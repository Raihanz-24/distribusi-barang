<?php

namespace Tests\Feature;

use App\Actions\SyncDailyRouteStores;
use App\Enums\VisitStatus;
use App\Models\DailyRoute;
use App\Models\RouteArea;
use App\Models\Store;
use App\Models\StoreVisit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncDailyRouteStoresTest extends TestCase
{
    use RefreshDatabase;

    public function test_route_uses_area_stores_and_only_allows_previous_day_closed_store_as_extra(): void
    {
        $admin = User::factory()->admin()->create();
        $sales = User::factory()->create();
        $area = RouteArea::create(['name' => 'Kraksaan', 'is_active' => true]);
        $secondArea = RouteArea::create(['name' => 'Paiton', 'is_active' => true]);
        $areaStores = Store::factory()->count(2)->create(['created_by' => $admin->id]);
        $secondAreaStore = Store::factory()->create(['created_by' => $admin->id]);
        $closedStore = Store::factory()->create(['created_by' => $admin->id]);
        $invalidStore = Store::factory()->create(['created_by' => $admin->id]);
        $area->stores()->attach($areaStores->pluck('id'));
        $secondArea->stores()->attach($secondAreaStore);
        $route = DailyRoute::factory()->create([
            'route_area_id' => $area->id,
            'sales_id' => $sales->id,
            'route_date' => '2026-08-05',
            'created_by' => $admin->id,
        ]);
        StoreVisit::create([
            'store_id' => $closedStore->id,
            'sales_id' => $sales->id,
            'visit_number' => 1,
            'visit_status' => VisitStatus::Closed,
            'arrived_at' => '2026-08-01 10:00:00',
            'completed_at' => '2026-08-01 10:05:00',
            'latitude' => $closedStore->latitude,
            'longitude' => $closedStore->longitude,
        ]);

        app(SyncDailyRouteStores::class)->execute(
            $route,
            [$area->id, $secondArea->id],
            [$closedStore->id, $invalidStore->id],
        );

        $this->assertEqualsCanonicalizing(
            [...$areaStores->pluck('id')->all(), $secondAreaStore->id, $closedStore->id],
            $route->routeStores()->pluck('store_id')->all(),
        );
        $this->assertEqualsCanonicalizing(
            [$area->id, $secondArea->id],
            $route->areas()->pluck('route_areas.id')->all(),
        );
        $this->assertDatabaseHas('daily_route_stores', [
            'daily_route_id' => $route->id,
            'store_id' => $closedStore->id,
            'scheduled_reason' => 'reschedule_closed_store',
        ]);
        $this->assertDatabaseMissing('daily_route_stores', [
            'daily_route_id' => $route->id,
            'store_id' => $invalidStore->id,
        ]);
    }
}
