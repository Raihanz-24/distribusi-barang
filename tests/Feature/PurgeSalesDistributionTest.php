<?php

namespace Tests\Feature;

use App\Actions\AdjustStock;
use App\Actions\CompleteSalesPickup;
use App\Actions\PurgeSalesDistribution;
use App\Actions\RecordStoreTransaction;
use App\Actions\SubmitSalesReturn;
use App\Data\Coordinate;
use App\Enums\DailyRouteStatus;
use App\Enums\OptimizationStatus;
use App\Enums\StockTransferStatus;
use App\Enums\StockTransferType;
use App\Enums\UserRole;
use App\Filament\Resources\SalesStockTransfers\Pages\ListSalesStockTransfers;
use App\Models\DailyRoute;
use App\Models\DailyRouteStore;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\Store;
use App\Models\User;
use App\Services\StockLocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class PurgeSalesDistributionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $developer;

    private User $sales;

    private Product $product;

    private Store $store;

    private StockLocationService $locations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->developer = User::factory()->create(['role' => UserRole::Developer]);
        $this->sales = User::factory()->create(['role' => UserRole::Sales, 'name' => 'Sales Uji']);
        $this->product = Product::factory()->create(['is_active' => true, 'pieces_per_renteng' => 12]);
        ProductPrice::factory()->create([
            'product_id' => $this->product->id,
            'price_per_renteng' => 20000,
            'is_active' => true,
        ]);
        $this->store = Store::factory()->create([
            'latitude' => -7.7721411,
            'longitude' => 113.4157336,
            'created_by' => $this->admin->id,
        ]);

        $this->locations = app(StockLocationService::class);
        app(AdjustStock::class)->execute(
            $this->locations->adminWarehouse(),
            $this->product,
            100,
            'Persiapan',
            $this->admin,
        );
    }

    private function distribute(int $pieces = 40): StockTransfer
    {
        $route = DailyRoute::query()->create([
            'sales_id' => $this->sales->id,
            'route_date' => today()->toDateString(),
            'status' => DailyRouteStatus::Scheduled,
            'optimization_status' => OptimizationStatus::Pending,
            'created_by' => $this->admin->id,
        ]);
        DailyRouteStore::query()->create([
            'daily_route_id' => $route->id,
            'store_id' => $this->store->id,
            'planned_order' => 1,
        ]);

        $pickup = StockTransfer::create([
            'transfer_type' => StockTransferType::IssueToSales,
            'source_location_id' => $this->locations->adminWarehouse()->id,
            'destination_location_id' => $this->locations->forSales($this->sales)->id,
            'sales_id' => $this->sales->id,
            'daily_route_id' => $route->id,
            'status' => StockTransferStatus::Draft,
            'created_by' => $this->admin->id,
        ]);
        $pickup->items()->create([
            'product_id' => $this->product->id,
            'quantity_entered' => $pieces,
            'quantity_unit' => 'piece',
        ]);
        app(CompleteSalesPickup::class)->execute($pickup, $this->admin);

        return $pickup->fresh();
    }

    private function depositToStore(int $pieces = 20): void
    {
        app(RecordStoreTransaction::class)->execute(
            $this->sales,
            $this->store,
            new Coordinate((float) $this->store->latitude, (float) $this->store->longitude),
            [$this->product->id => ['sold' => 0, 'deposited' => $pieces, 'withdrawn' => 0]],
            null,
            0,
        );
    }

    private function sellToStore(int $pieces = 5): void
    {
        app(RecordStoreTransaction::class)->execute(
            $this->sales,
            $this->store,
            new Coordinate((float) $this->store->latitude, (float) $this->store->longitude),
            [$this->product->id => ['sold' => $pieces, 'deposited' => 0, 'withdrawn' => 0]],
            null,
        );
    }

    public function test_developer_purges_in_progress_distribution_and_every_related_record(): void
    {
        $pickup = $this->distribute(40);
        $this->depositToStore(20);
        $this->sellToStore(5);

        $salesLocation = $this->locations->forSales($this->sales);
        $storeLocation = $this->locations->forStore($this->store);
        $route = $pickup->dailyRoute;

        $this->assertSame(20, (int) StockBalance::where('stock_location_id', $salesLocation->id)->where('product_id', $this->product->id)->value('quantity_pieces'));
        $this->assertSame(15, (int) StockBalance::where('stock_location_id', $storeLocation->id)->where('product_id', $this->product->id)->value('quantity_pieces'));
        $this->assertDatabaseHas('store_visits', ['daily_route_id' => $route->id]);
        $this->assertDatabaseHas('store_account_entries', ['store_id' => $this->store->id]);
        $this->assertTrue(StockMovement::where('reference_type', StockTransfer::class)->where('reference_id', $pickup->id)->exists());

        app(PurgeSalesDistribution::class)->execute($pickup, $this->developer);

        $this->assertDatabaseMissing('stock_transfers', ['id' => $pickup->id]);
        $this->assertDatabaseMissing('daily_routes', ['id' => $route->id]);
        $this->assertDatabaseMissing('store_visits', ['daily_route_id' => $route->id]);
        $this->assertDatabaseMissing('store_account_entries', ['store_id' => $this->store->id]);

        $this->assertNull(StockBalance::where('stock_location_id', $salesLocation->id)->where('product_id', $this->product->id)->first());
        $this->assertNull(StockBalance::where('stock_location_id', $storeLocation->id)->where('product_id', $this->product->id)->first());

        $this->assertFalse(StockMovement::where('reference_type', StockTransfer::class)->where('reference_id', $pickup->id)->exists());
        $this->assertDatabaseHas('audit_logs', ['action' => 'distribution.purged', 'user_id' => $this->developer->id]);
    }

    public function test_developer_purges_distribution_with_pending_return(): void
    {
        $pickup = $this->distribute(40);
        $this->depositToStore(20);
        $settlement = app(SubmitSalesReturn::class)->execute($pickup->fresh(), $this->sales);
        $return = $settlement->returnTransfer;

        app(PurgeSalesDistribution::class)->execute($return, $this->developer);

        $this->assertDatabaseMissing('stock_transfers', ['id' => $pickup->id]);
        $this->assertDatabaseMissing('stock_transfers', ['id' => $return->id]);
        $this->assertDatabaseMissing('daily_sales_settlements', ['id' => $settlement->id]);
    }

    public function test_admin_cannot_purge_distribution(): void
    {
        $pickup = $this->distribute(40);

        $this->expectException(ValidationException::class);

        app(PurgeSalesDistribution::class)->execute($pickup, $this->admin);
    }

    public function test_developer_cannot_purge_draft_distribution(): void
    {
        $route = DailyRoute::query()->create([
            'sales_id' => $this->sales->id,
            'route_date' => today()->toDateString(),
            'status' => DailyRouteStatus::Scheduled,
            'optimization_status' => OptimizationStatus::Pending,
            'created_by' => $this->admin->id,
        ]);
        $draft = StockTransfer::create([
            'transfer_type' => StockTransferType::IssueToSales,
            'source_location_id' => $this->locations->adminWarehouse()->id,
            'destination_location_id' => $this->locations->forSales($this->sales)->id,
            'sales_id' => $this->sales->id,
            'daily_route_id' => $route->id,
            'status' => StockTransferStatus::Draft,
            'created_by' => $this->admin->id,
        ]);

        $this->expectException(ValidationException::class);

        app(PurgeSalesDistribution::class)->execute($draft, $this->developer);
    }

    public function test_developer_can_purge_from_distribution_page_and_admin_cannot_see_button(): void
    {
        $pickup = $this->distribute(40);

        $this->actingAs($this->developer);
        Livewire::test(ListSalesStockTransfers::class)
            ->call('purgeDistribution', $pickup->id);

        $this->assertDatabaseMissing('stock_transfers', ['id' => $pickup->id]);
    }
}
