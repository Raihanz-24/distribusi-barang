<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\DailyRoute;
use App\Models\RouteArea;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouteAndAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_sales_cannot_have_two_routes_on_same_date(): void
    {
        $sales = User::factory()->create();
        DailyRoute::factory()->create(['sales_id' => $sales->id, 'route_date' => '2026-08-03']);

        $this->expectException(QueryException::class);
        DailyRoute::factory()->create(['sales_id' => $sales->id, 'route_date' => '2026-08-03']);
    }

    public function test_route_can_contain_many_unique_stores(): void
    {
        $route = DailyRoute::factory()->create();
        $stores = Store::factory()->count(3)->create();
        $route->stores()->attach($stores->pluck('id'));

        $this->assertCount(3, $route->fresh()->stores);
    }

    public function test_sales_cannot_view_another_sales_route_or_unassigned_store(): void
    {
        $sales = User::factory()->create(['role' => UserRole::Sales]);
        $other = User::factory()->create(['role' => UserRole::Sales]);
        $route = DailyRoute::factory()->create(['sales_id' => $other->id]);
        $store = Store::factory()->create();

        $this->assertFalse($sales->can('view', $route));
        $this->assertFalse($sales->can('view', $store));
        $this->assertTrue($other->can('view', $route));
    }

    public function test_sales_cannot_verify_store_but_admin_can(): void
    {
        $sales = User::factory()->create(['role' => UserRole::Sales]);
        $admin = User::factory()->admin()->create();
        $store = Store::factory()->create();

        $this->assertFalse($sales->can('verify', $store));
        $this->assertTrue($admin->can('verify', $store));
    }

    public function test_route_area_code_is_generated_automatically(): void
    {
        $first = RouteArea::create(['name' => 'Kraksaan', 'is_active' => true]);
        $second = RouteArea::create(['name' => 'Paiton', 'is_active' => true]);

        $this->assertSame('AREA-0001', $first->code);
        $this->assertSame('AREA-0002', $second->code);
    }
}
