<?php

namespace Tests\Feature;

use App\Enums\DailyRouteStatus;
use App\Enums\OptimizationStatus;
use App\Enums\StockTransferStatus;
use App\Enums\StockTransferType;
use App\Enums\UserRole;
use App\Filament\Pages\TodayRoute;
use App\Models\DailyRoute;
use App\Models\DailyRouteStore;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\StockTransfer;
use App\Models\Store;
use App\Models\User;
use App\Services\StockLocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regresi untuk kasus server: memilih toko gagal (500) karena ada tabel/kolom
 * yang belum dimigrasikan, padahal halaman GET biasa tetap tampil.
 */
class TodayRouteResilienceTest extends TestCase
{
    use RefreshDatabase;

    private function makeRoute(User $sales, Store $store): void
    {
        $admin = User::factory()->admin()->create();
        $route = DailyRoute::query()->create([
            'sales_id' => $sales->id,
            'route_date' => now()->toDateString(),
            'status' => DailyRouteStatus::Scheduled,
            'optimization_status' => OptimizationStatus::Pending,
            'created_by' => $admin->id,
        ]);

        DailyRouteStore::query()->create([
            'daily_route_id' => $route->id,
            'store_id' => $store->id,
            'planned_order' => 1,
        ]);

        $locations = app(StockLocationService::class);
        StockTransfer::query()->create([
            'transfer_type' => StockTransferType::IssueToSales,
            'daily_route_id' => $route->id,
            'sales_id' => $sales->id,
            'source_location_id' => $locations->adminWarehouse()->id,
            'destination_location_id' => $locations->forSales($sales)->id,
            'status' => StockTransferStatus::Received,
            'created_by' => $admin->id,
            'received_at' => now(),
        ]);
    }

    public function test_select_store_survives_missing_account_ledger_table(): void
    {
        $sales = User::factory()->create(['role' => UserRole::Sales]);
        $store = Store::factory()->create(['name' => 'Toko Ledger Hilang']);
        $this->makeRoute($sales, $store);

        Product::factory()->create(['is_active' => true]);
        ProductPrice::factory()->create([
            'product_id' => Product::first()->id,
            'price_per_renteng' => 20000,
            'is_active' => true,
        ]);

        // Tiru server yang migrasinya tertinggal: tabel ledger tidak ada.
        Schema::drop('store_account_entries');

        $this->actingAs($sales);

        Livewire::test(TodayRoute::class)
            ->call('selectStore', $store->id)
            ->assertOk()
            ->assertSet('selectedStoreId', $store->id)
            ->assertSee('Transaksi toko');
    }

    public function test_transaction_summary_returns_zero_debt_without_ledger(): void
    {
        $sales = User::factory()->create(['role' => UserRole::Sales]);
        $store = Store::factory()->create();
        $this->makeRoute($sales, $store);

        Schema::drop('store_account_entries');

        $this->actingAs($sales);

        $page = app(TodayRoute::class);
        $summary = $page->transactionSummary($store->id);

        $this->assertSame(0, $summary['opening_debt']);
        $this->assertSame(0, $summary['closing_debt']);
    }
}
