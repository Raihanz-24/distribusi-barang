<?php

namespace Tests\Feature;

use App\Actions\AdjustStock;
use App\Actions\RecordStoreTransaction;
use App\Actions\RecordStoreVisit;
use App\Actions\ReverseStoreTransaction;
use App\Data\Coordinate;
use App\Enums\VisitStatus;
use App\Enums\StockTransferStatus;
use App\Enums\StockTransferType;
use App\Models\DailyRoute;
use App\Models\Product;
use App\Models\Store;
use App\Models\StoreAccountEntry;
use App\Models\StockTransfer;
use App\Models\User;
use App\Services\StockLocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StoreVisitTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_can_deviate_from_optimized_order_and_actual_order_is_recorded(): void
    {
        $sales = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $route = DailyRoute::factory()->create([
            'sales_id' => $sales->id, 'created_by' => $admin->id, 'route_date' => today(),
        ]);
        [$first, $second] = Store::factory()->count(2)->create([
            'created_by' => $admin->id, 'latitude' => -8.58, 'longitude' => 116.10,
        ]);
        $route->stores()->attach($first->id, ['optimized_order' => 1]);
        $route->stores()->attach($second->id, ['optimized_order' => 2]);
        $action = app(RecordStoreVisit::class);

        $action->execute($sales, $second, new Coordinate(-8.58, 116.10), VisitStatus::Visited);
        $action->execute($sales, $first, new Coordinate(-8.58, 116.10), VisitStatus::Closed);

        $this->assertSame(1, $route->routeStores()->where('store_id', $second->id)->value('actual_order'));
        $this->assertSame(2, $route->routeStores()->where('store_id', $first->id)->value('actual_order'));
        $this->assertDatabaseHas('daily_route_stores', ['store_id' => $first->id, 'visit_status' => 'closed']);
    }

    public function test_outside_route_visit_requires_reason(): void
    {
        $sales = User::factory()->create();
        $store = Store::factory()->create(['latitude' => -8.58, 'longitude' => 116.10]);

        $this->expectException(ValidationException::class);
        app(RecordStoreVisit::class)->execute(
            $sales, $store, new Coordinate(-8.58, 116.10), VisitStatus::Visited
        );
    }

    public function test_active_delivery_route_remains_available_after_midnight(): void
    {
        $sales = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $route = DailyRoute::factory()->create([
            'sales_id' => $sales->id,
            'created_by' => $admin->id,
            'route_date' => today()->subDay(),
        ]);
        $store = Store::factory()->create([
            'created_by' => $admin->id,
            'latitude' => -8.58,
            'longitude' => 116.10,
        ]);
        $route->stores()->attach($store->id, ['planned_order' => 1]);
        $locations = app(StockLocationService::class);
        StockTransfer::create([
            'transfer_type' => StockTransferType::IssueToSales,
            'daily_route_id' => $route->id,
            'sales_id' => $sales->id,
            'source_location_id' => $locations->adminWarehouse()->id,
            'destination_location_id' => $locations->forSales($sales)->id,
            'status' => StockTransferStatus::Received,
            'created_by' => $admin->id,
            'received_at' => now()->subDay()->endOfDay(),
        ]);

        $visit = app(RecordStoreVisit::class)->execute(
            $sales,
            $store,
            new Coordinate(-8.58, 116.10),
            VisitStatus::Closed,
        );

        $this->assertSame($route->id, $visit->daily_route_id);
        $this->assertSame(1, $route->routeStores()->where('store_id', $store->id)->value('actual_order'));
    }

    public function test_partial_store_payment_creates_a_receivable_ledger_balance(): void
    {
        $sales = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $store = Store::factory()->create([
            'created_by' => $admin->id,
            'latitude' => -8.58,
            'longitude' => 116.10,
        ]);
        $product = Product::factory()->create(['pieces_per_renteng' => 12]);
        $product->prices()->create([
            'price_per_renteng' => 20_000,
            'effective_from' => today(),
            'is_active' => true,
            'created_by' => $admin->id,
        ]);
        $route = DailyRoute::factory()->create([
            'sales_id' => $sales->id,
            'created_by' => $admin->id,
            'route_date' => today(),
        ]);
        $route->stores()->attach($store->id, ['planned_order' => 1]);
        app(AdjustStock::class)->execute(
            app(StockLocationService::class)->forStore($store),
            $product,
            12,
            'Stok titipan pengujian',
            $admin,
        );

        $visit = app(RecordStoreTransaction::class)->execute(
            $sales,
            $store,
            new Coordinate(-8.58, 116.10),
            [$product->id => ['sold' => 3, 'deposited' => 0, 'withdrawn' => 2]],
            5,
            3_000,
        );

        $this->assertSame(5_000, $visit->amount_due);
        $this->assertSame(3_000, $visit->amount_paid);
        $this->assertSame(2_000, $visit->debt_amount);
        $this->assertSame(2, $visit->items->first()->withdrawn_pieces);
        $this->assertSame(2_000, $store->fresh()->outstanding_debt);
        $this->assertSame(7, (int) app(StockLocationService::class)->forStore($store)->balances()->where('product_id', $product->id)->value('quantity_pieces'));
        $this->assertSame(2, (int) app(StockLocationService::class)->forSales($sales)->balances()->where('product_id', $product->id)->value('quantity_pieces'));
        $this->assertDatabaseHas('store_account_entries', [
            'store_visit_id' => $visit->id,
            'entry_type' => 'charge',
            'amount' => 5_000,
        ]);
        $this->assertDatabaseHas('store_account_entries', [
            'store_visit_id' => $visit->id,
            'entry_type' => 'payment',
            'amount' => -3_000,
        ]);
    }

    public function test_sales_can_collect_old_debt_separately_from_the_current_transaction(): void
    {
        $sales = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $store = Store::factory()->create([
            'created_by' => $admin->id,
            'latitude' => -8.58,
            'longitude' => 116.10,
        ]);
        $product = Product::factory()->create(['pieces_per_renteng' => 12]);
        $product->prices()->create([
            'price_per_renteng' => 20_000,
            'effective_from' => today(),
            'is_active' => true,
            'created_by' => $admin->id,
        ]);
        $route = DailyRoute::factory()->create([
            'sales_id' => $sales->id,
            'created_by' => $admin->id,
            'route_date' => today(),
        ]);
        $route->stores()->attach($store->id, ['planned_order' => 1]);
        app(AdjustStock::class)->execute(
            app(StockLocationService::class)->forStore($store),
            $product,
            12,
            'Stok titipan pengujian',
            $admin,
        );
        StoreAccountEntry::query()->create([
            'store_id' => $store->id,
            'sales_id' => $sales->id,
            'entry_type' => 'charge',
            'amount' => 10_000,
            'notes' => 'Hutang sebelumnya',
            'occurred_at' => now()->subDay(),
            'recorded_by' => $sales->id,
        ]);

        $visit = app(RecordStoreTransaction::class)->execute(
            $sales,
            $store,
            new Coordinate(-8.58, 116.10),
            [$product->id => ['sold' => 3, 'deposited' => 0, 'withdrawn' => 0]],
            5,
            5_000,
            4_000,
        );

        $this->assertSame(10_000, $visit->opening_debt);
        $this->assertSame(5_000, $visit->current_payment_amount);
        $this->assertSame(4_000, $visit->debt_payment_amount);
        $this->assertSame(9_000, $visit->amount_paid);
        $this->assertSame(6_000, $visit->closing_debt);
        $this->assertSame(6_000, $store->fresh()->outstanding_debt);
    }

    public function test_admin_can_reverse_latest_transaction_before_closing_without_deleting_history(): void
    {
        $sales = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $store = Store::factory()->create(['created_by' => $admin->id, 'latitude' => -8.58, 'longitude' => 116.10]);
        $product = Product::factory()->create(['pieces_per_renteng' => 12]);
        $product->prices()->create([
            'price_per_renteng' => 20_000,
            'effective_from' => today(),
            'is_active' => true,
            'created_by' => $admin->id,
        ]);
        $route = DailyRoute::factory()->create(['sales_id' => $sales->id, 'created_by' => $admin->id, 'route_date' => today()]);
        $route->stores()->attach($store->id, ['planned_order' => 1]);
        $locations = app(StockLocationService::class);
        app(AdjustStock::class)->execute($locations->forStore($store), $product, 12, 'Stok toko', $admin);
        app(AdjustStock::class)->execute($locations->forSales($sales), $product, 10, 'Stok Sales', $admin);

        $visit = app(RecordStoreTransaction::class)->execute(
            $sales,
            $store,
            new Coordinate(-8.58, 116.10),
            [$product->id => ['sold' => 3, 'deposited' => 2, 'withdrawn' => 1]],
            5,
            5_000,
        );

        app(ReverseStoreTransaction::class)->execute($visit, $admin, 'Sales salah memilih toko transaksi.');

        $this->assertNotNull($visit->fresh()->reversed_at);
        $this->assertSame(12, (int) $locations->forStore($store)->balances()->where('product_id', $product->id)->value('quantity_pieces'));
        $this->assertSame(10, (int) $locations->forSales($sales)->balances()->where('product_id', $product->id)->value('quantity_pieces'));
        $this->assertSame(0, (int) $store->fresh()->outstanding_debt);
        $this->assertDatabaseCount('store_visits', 1);
        $this->assertDatabaseHas('daily_route_stores', [
            'daily_route_id' => $route->id,
            'store_id' => $store->id,
            'visit_status' => 'pending',
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'store.transaction_reversed']);
    }
}
