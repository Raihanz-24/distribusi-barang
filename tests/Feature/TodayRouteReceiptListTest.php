<?php

namespace Tests\Feature;

use App\Actions\AdjustStock;
use App\Actions\RecordStoreTransaction;
use App\Data\Coordinate;
use App\Enums\DailyRouteStatus;
use App\Enums\OptimizationStatus;
use App\Enums\UserRole;
use App\Filament\Pages\Diagnostics;
use App\Filament\Pages\TodayRoute;
use App\Models\DailyRoute;
use App\Models\DailyRouteStore;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Store;
use App\Models\User;
use App\Services\StockLocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Setiap transaksi selesai harus dapat dicetak ulang, bukan hanya yang terakhir.
 */
class TodayRouteReceiptListTest extends TestCase
{
    use RefreshDatabase;

    private User $sales;

    private Store $store;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sales = User::factory()->create(['role' => UserRole::Sales]);
        $admin = User::factory()->admin()->create();

        $this->store = Store::factory()->create([
            'name' => 'Toko Struk',
            'latitude' => -7.7721411,
            'longitude' => 113.4157336,
        ]);

        $route = DailyRoute::query()->create([
            'sales_id' => $this->sales->id,
            'route_date' => now()->toDateString(),
            'status' => DailyRouteStatus::Scheduled,
            'optimization_status' => OptimizationStatus::Pending,
            'created_by' => $admin->id,
        ]);

        DailyRouteStore::query()->create([
            'daily_route_id' => $route->id,
            'store_id' => $this->store->id,
            'planned_order' => 1,
        ]);

        $this->product = Product::factory()->create(['is_active' => true, 'pieces_per_renteng' => 12]);
        ProductPrice::factory()->create([
            'product_id' => $this->product->id,
            'price_per_renteng' => 20000,
            'is_active' => true,
        ]);

        app(AdjustStock::class)->execute(
            app(StockLocationService::class)->forSales($this->sales),
            $this->product,
            240,
            'Stok awal pengujian',
            $admin,
        );
    }

    private function recordTransaction(): \App\Models\StoreVisit
    {
        return app(RecordStoreTransaction::class)->execute(
            $this->sales,
            $this->store,
            new Coordinate((float) $this->store->latitude, (float) $this->store->longitude),
            [$this->product->id => ['sold' => 0, 'deposited' => 3, 'withdrawn' => 0]],
            null,
            0,
            0,
            'GPS perangkat tidak tersedia; memakai koordinat toko',
        );
    }

    public function test_all_completed_visits_are_printable_not_only_the_last(): void
    {
        $first = $this->recordTransaction();
        $second = $this->recordTransaction();

        $this->actingAs($this->sales);

        $page = app(TodayRoute::class);
        $visits = $page->completedVisits;

        $this->assertCount(2, $visits);
        $ids = array_column($visits, 'id');
        $this->assertContains($first->id, $ids);
        $this->assertContains($second->id, $ids);

        foreach ($visits as $visit) {
            $this->assertNotEmpty($visit['escpos_url']);
            $this->assertNotEmpty($visit['receipt_url']);
            $this->assertStringContainsString((string) $visit['id'], $visit['escpos_url']);
        }
    }

    public function test_receipt_panel_renders_after_saving(): void
    {
        $this->actingAs($this->sales);

        $visit = $this->recordTransaction();

        Livewire::test(TodayRoute::class)
            ->call('selectStore', $this->store->id)
            ->assertSee('Transaksi selesai')
            ->assertSee($this->store->name)
            ->assertSee('Cetak');
    }

    public function test_diagnostics_page_is_hidden_from_navigation(): void
    {
        $this->assertFalse(Diagnostics::shouldRegisterNavigation());
    }
}
