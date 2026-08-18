<?php

namespace Tests\Feature;

use App\Enums\VisitStatus;
use App\Livewire\Admin\DailyStoreMonitoringTable;
use App\Models\DailyRoute;
use App\Models\DailyRouteStore;
use App\Models\Store;
use App\Models\StoreVisit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DailyStoreMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_monitor_scheduled_visited_and_unvisited_stores_for_today(): void
    {
        $admin = User::factory()->admin()->create();
        $sales = User::factory()->create(['name' => 'Sales Monitoring']);
        $visitedStore = Store::factory()->create(['name' => 'Toko Sudah', 'created_by' => $admin->id]);
        $unvisitedStore = Store::factory()->create(['name' => 'Toko Belum', 'created_by' => $admin->id]);
        $route = DailyRoute::factory()->create([
            'sales_id' => $sales->id,
            'route_date' => today(),
            'created_by' => $admin->id,
        ]);
        $visitedRouteStore = DailyRouteStore::create([
            'daily_route_id' => $route->id,
            'store_id' => $visitedStore->id,
            'planned_order' => 1,
            'visit_status' => VisitStatus::Visited,
        ]);
        DailyRouteStore::create([
            'daily_route_id' => $route->id,
            'store_id' => $unvisitedStore->id,
            'planned_order' => 2,
            'visit_status' => VisitStatus::Pending,
        ]);
        StoreVisit::create([
            'daily_route_id' => $route->id,
            'daily_route_store_id' => $visitedRouteStore->id,
            'store_id' => $visitedStore->id,
            'sales_id' => $sales->id,
            'visit_number' => 1,
            'visit_status' => VisitStatus::Visited,
            'arrived_at' => now()->subMinutes(5),
            'completed_at' => now(),
            'latitude' => -7.7,
            'longitude' => 113.4,
        ]);

        $this->actingAs($admin);

        $this->get('/admin/sales-stock-transfers')
            ->assertOk()
            ->assertSee('Monitoring Kunjungan Toko Hari Ini');

        Livewire::test(DailyStoreMonitoringTable::class)
            ->assertCanSeeTableRecords([$visitedRouteStore])
            ->assertSee('Toko Sudah')
            ->assertSee('Toko Belum')
            ->assertSee('Sudah dikunjungi')
            ->assertSee('Belum dikunjungi')
            ->filterTable('visit_monitoring', 'unvisited')
            ->assertSee('Toko Belum')
            ->assertDontSee('Toko Sudah');
    }
}
