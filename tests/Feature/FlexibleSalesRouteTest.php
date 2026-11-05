<?php

namespace Tests\Feature;

use App\Actions\ManageSalesRoute;
use App\Actions\RecordStoreVisit;
use App\Data\Coordinate;
use App\Enums\StockTransferStatus;
use App\Enums\StockTransferType;
use App\Enums\UserRole;
use App\Enums\VisitStatus;
use App\Filament\Pages\TodayRoute;
use App\Filament\Pages\VisitCoverage;
use App\Models\DailyRoute;
use App\Models\StockTransfer;
use App\Models\Store;
use App\Models\User;
use App\Services\StockLocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class FlexibleSalesRouteTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $sales;

    private Store $first;

    private Store $second;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->sales = User::factory()->create(['role' => UserRole::Sales, 'name' => 'Sales Lapangan']);
        $this->first = Store::factory()->create([
            'name' => 'Toko Mawar',
            'address' => 'Jalan Mawar 1',
            'latitude' => -7.7721000,
            'longitude' => 113.4157000,
            'is_active' => true,
        ]);
        $this->second = Store::factory()->create([
            'name' => 'Toko Melati',
            'address' => 'Jalan Melati 2',
            'latitude' => -7.7722000,
            'longitude' => 113.4158000,
            'is_active' => true,
        ]);
    }

    public function test_sales_workspace_creates_its_own_empty_route_for_today(): void
    {
        $this->actingAs($this->sales);

        Livewire::test(TodayRoute::class)
            ->assertSee('Rencana Lapangan Saya')
            ->assertSee('Cari toko untuk dikunjungi');

        $this->assertTrue(DailyRoute::query()
            ->where('sales_id', $this->sales->id)
            ->whereDate('route_date', today())
            ->where('created_by', $this->sales->id)
            ->exists());
    }

    public function test_sales_can_search_store_and_see_last_visit_from_another_sales(): void
    {
        $otherSales = User::factory()->create(['role' => UserRole::Sales, 'name' => 'Budi']);
        $route = DailyRoute::factory()->create([
            'sales_id' => $otherSales->id,
            'route_date' => today()->subDays(5),
            'created_by' => $this->admin->id,
        ]);
        $route->routeStores()->create(['store_id' => $this->first->id, 'planned_order' => 1]);

        $this->travelTo(now()->subDays(5));
        app(RecordStoreVisit::class)->execute(
            $otherSales,
            $this->first,
            new Coordinate((float) $this->first->latitude, (float) $this->first->longitude),
            VisitStatus::Visited,
        );
        $this->travelBack();

        $this->actingAs($this->sales);

        Livewire::test(TodayRoute::class)
            ->set('storeSearch', 'Mawar')
            ->assertSee('Toko Mawar')
            ->assertSee('Terakhir dikunjungi 5 hari lalu')
            ->assertSee('oleh Budi');
    }

    public function test_selecting_store_outside_route_adds_it_automatically(): void
    {
        $route = app(ManageSalesRoute::class)->today($this->sales);
        $locations = app(StockLocationService::class);
        StockTransfer::query()->create([
            'transfer_type' => StockTransferType::IssueToSales,
            'daily_route_id' => $route->id,
            'sales_id' => $this->sales->id,
            'source_location_id' => $locations->adminWarehouse()->id,
            'destination_location_id' => $locations->forSales($this->sales)->id,
            'status' => StockTransferStatus::Received,
            'created_by' => $this->admin->id,
            'received_at' => now(),
        ]);
        $this->actingAs($this->sales);

        $component = Livewire::test(TodayRoute::class)
            ->call('selectStore', $this->first->id)
            ->assertSet('selectedStoreId', $this->first->id)
            ->assertSee('Transaksi toko');

        $this->assertDatabaseHas('daily_route_stores', [
            'daily_route_id' => $route->id,
            'store_id' => $this->first->id,
        ]);
    }

    public function test_sales_can_reorder_and_remove_unvisited_stores(): void
    {
        $route = app(ManageSalesRoute::class)->today($this->sales);
        $first = app(ManageSalesRoute::class)->addStore($route, $this->first, $this->sales);
        $second = app(ManageSalesRoute::class)->addStore($route, $this->second, $this->sales);

        app(ManageSalesRoute::class)->move($second, 'up', $this->sales);

        $this->assertSame(
            [$this->second->id, $this->first->id],
            $route->routeStores()->orderBy('planned_order')->pluck('store_id')->all(),
        );

        app(ManageSalesRoute::class)->removeStore($first->fresh(), $this->sales);

        $this->assertDatabaseMissing('daily_route_stores', ['id' => $first->id]);
        $this->assertDatabaseHas('daily_route_stores', ['id' => $second->id, 'planned_order' => 1]);
    }

    public function test_visit_coverage_filters_visited_and_unvisited_stores_this_month(): void
    {
        $route = app(ManageSalesRoute::class)->today($this->sales);
        app(ManageSalesRoute::class)->addStore($route, $this->first, $this->sales);
        app(RecordStoreVisit::class)->execute(
            $this->sales,
            $this->first,
            new Coordinate((float) $this->first->latitude, (float) $this->first->longitude),
            VisitStatus::Visited,
        );

        $this->actingAs($this->sales);

        Livewire::test(VisitCoverage::class)
            ->set('status', 'visited')
            ->assertSee('Toko Mawar')
            ->assertDontSee('Toko Melati')
            ->set('status', 'unvisited')
            ->assertSee('Toko Melati')
            ->assertDontSee('Toko Mawar');
    }

    public function test_non_transaction_visit_requires_a_reason(): void
    {
        $route = app(ManageSalesRoute::class)->today($this->sales);
        app(ManageSalesRoute::class)->addStore($route, $this->first, $this->sales);

        $this->expectException(ValidationException::class);

        app(RecordStoreVisit::class)->execute(
            $this->sales,
            $this->first,
            new Coordinate((float) $this->first->latitude, (float) $this->first->longitude),
            VisitStatus::Closed,
        );
    }
}
