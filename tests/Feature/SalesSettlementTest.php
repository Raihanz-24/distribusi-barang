<?php

namespace Tests\Feature;

use App\Actions\AdjustStock;
use App\Actions\ApproveSalesReturn;
use App\Actions\CompleteSalesPickup;
use App\Actions\SubmitSalesReturn;
use App\Enums\DailyRouteStatus;
use App\Enums\SalesSettlementStatus;
use App\Enums\StockTransferStatus;
use App\Enums\StockTransferType;
use App\Enums\VisitStatus;
use App\Models\DailyRoute;
use App\Models\DailyRouteStore;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\StockTransfer;
use App\Models\Store;
use App\Models\StoreVisit;
use App\Models\User;
use App\Services\StockLocationService;
use App\Services\StoreSettlementCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SalesSettlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_fee_and_amount_due_follow_piece_tiers(): void
    {
        $product = Product::factory()->create(['pieces_per_renteng' => 12]);
        $product->prices()->create([
            'price_per_renteng' => 20_000,
            'effective_from' => today(),
            'is_active' => true,
            'created_by' => User::factory()->admin()->create()->id,
        ]);
        $calculator = app(StoreSettlementCalculator::class);

        $this->assertSame(['store_fee' => 1_000, 'amount_due' => 5_000], collect($calculator->calculate($product->fresh(), 3))->only(['store_fee', 'amount_due'])->all());
        $this->assertSame(['store_fee' => 2_000, 'amount_due' => 10_000], collect($calculator->calculate($product->fresh(), 6))->only(['store_fee', 'amount_due'])->all());
        $this->assertSame(['store_fee' => 2_000, 'amount_due' => 16_000], collect($calculator->calculate($product->fresh(), 9))->only(['store_fee', 'amount_due'])->all());
        $this->assertSame(['store_fee' => 3_000, 'amount_due' => 17_000], collect($calculator->calculate($product->fresh(), 10))->only(['store_fee', 'amount_due'])->all());
        $this->assertSame(['store_fee' => 4_000, 'amount_due' => 20_000], collect($calculator->calculate($product->fresh(), 12))->only(['store_fee', 'amount_due'])->all());
    }

    public function test_sales_daily_fee_is_based_on_final_collection_equivalent_renteng(): void
    {
        $result = app(StoreSettlementCalculator::class)->salesFee(100_000);

        $this->assertSame(5, $result['equivalent_renteng']);
        $this->assertSame(17_500, $result['fee']);
    }

    public function test_sales_return_submission_and_admin_approval_finalize_stock_and_daily_fee(): void
    {
        $admin = User::factory()->admin()->create();
        $sales = User::factory()->create();
        $product = Product::factory()->create();
        $store = Store::factory()->create(['created_by' => $admin->id]);
        $locations = app(StockLocationService::class);
        $adminWarehouse = $locations->adminWarehouse();
        $salesLocation = $locations->forSales($sales);
        app(AdjustStock::class)->execute($adminWarehouse, $product, 60, 'Persiapan', $admin);

        $pickup = StockTransfer::create([
            'transfer_type' => StockTransferType::IssueToSales,
            'source_location_id' => $adminWarehouse->id,
            'destination_location_id' => $salesLocation->id,
            'sales_id' => $sales->id,
            'status' => StockTransferStatus::Draft,
            'created_by' => $admin->id,
        ]);
        $pickup->items()->create(['product_id' => $product->id, 'quantity_issued' => 60]);
        app(CompleteSalesPickup::class)->execute($pickup, $admin);

        $route = DailyRoute::factory()->create([
            'sales_id' => $sales->id,
            'route_date' => today(),
            'status' => DailyRouteStatus::Scheduled,
            'created_by' => $admin->id,
        ]);
        $pickup->update(['daily_route_id' => $route->id]);
        $visit = StoreVisit::create([
            'daily_route_id' => $route->id,
            'store_id' => $store->id,
            'sales_id' => $sales->id,
            'visit_number' => 1,
            'visit_status' => VisitStatus::Visited,
            'amount_due' => 100_000,
            'amount_paid' => 100_000,
            'debt_amount' => 0,
            'arrived_at' => now(),
            'completed_at' => now(),
            'latitude' => -7.7,
            'longitude' => 113.4,
        ]);
        $visit->items()->create([
            'product_id' => $product->id,
            'sold_pieces' => 60,
            'deposited_pieces' => 0,
            'retail_price_per_piece' => 2_000,
            'price_per_renteng' => 20_000,
            'store_fee' => 20_000,
            'amount_due' => 100_000,
        ]);

        $settlement = app(SubmitSalesReturn::class)->execute($pickup->fresh(), $sales);

        $this->assertSame(SalesSettlementStatus::Pending, $settlement->status);
        $this->assertSame(100_000, $settlement->total_collection);
        $this->assertSame(17_500, $settlement->sales_fee);
        $this->assertSame(StockTransferStatus::PendingApproval, $settlement->returnTransfer->status);

        $approved = app(ApproveSalesReturn::class)->execute($settlement->returnTransfer, $admin);

        $this->assertSame(SalesSettlementStatus::Approved, $approved->status);
        $this->assertSame(0, (int) StockBalance::where('stock_location_id', $salesLocation->id)->where('product_id', $product->id)->value('quantity_pieces'));
        $this->assertSame(60, (int) StockBalance::where('stock_location_id', $adminWarehouse->id)->where('product_id', $product->id)->value('quantity_pieces'));
    }

    public function test_sales_cannot_finish_distribution_while_a_scheduled_store_is_unprocessed(): void
    {
        $admin = User::factory()->admin()->create();
        $sales = User::factory()->create();
        $locations = app(StockLocationService::class);
        $route = DailyRoute::factory()->create([
            'sales_id' => $sales->id,
            'route_date' => today(),
            'created_by' => $admin->id,
        ]);
        DailyRouteStore::create([
            'daily_route_id' => $route->id,
            'store_id' => Store::factory()->create(['created_by' => $admin->id])->id,
            'planned_order' => 1,
        ]);
        $pickup = StockTransfer::create([
            'transfer_type' => StockTransferType::IssueToSales,
            'source_location_id' => $locations->adminWarehouse()->id,
            'destination_location_id' => $locations->forSales($sales)->id,
            'sales_id' => $sales->id,
            'status' => StockTransferStatus::Received,
            'daily_route_id' => $route->id,
            'created_by' => $admin->id,
        ]);

        try {
            app(SubmitSalesReturn::class)->execute($pickup, $sales);
            $this->fail('Pengajuan seharusnya ditolak selama masih ada toko yang belum diproses.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('1 toko yang belum diproses', $exception->errors()['route'][0]);
        }

        $this->assertDatabaseMissing('stock_transfers', ['pickup_transfer_id' => $pickup->id]);
        $this->assertDatabaseMissing('daily_sales_settlements', ['pickup_transfer_id' => $pickup->id]);
    }

    public function test_return_submission_uses_the_pickup_schedule_even_after_the_route_date(): void
    {
        $admin = User::factory()->admin()->create();
        $sales = User::factory()->create();
        $locations = app(StockLocationService::class);
        $route = DailyRoute::factory()->create([
            'sales_id' => $sales->id,
            'route_date' => today()->subDay(),
            'created_by' => $admin->id,
        ]);
        $pickup = StockTransfer::create([
            'transfer_type' => StockTransferType::IssueToSales,
            'source_location_id' => $locations->adminWarehouse()->id,
            'destination_location_id' => $locations->forSales($sales)->id,
            'sales_id' => $sales->id,
            'daily_route_id' => $route->id,
            'status' => StockTransferStatus::Received,
            'created_by' => $admin->id,
        ]);

        $settlement = app(SubmitSalesReturn::class)->execute($pickup, $sales);

        $this->assertTrue($settlement->settlement_date->isSameDay($route->route_date));
        $this->assertSame(DailyRouteStatus::PendingReview, $route->fresh()->status);
    }

    public function test_admin_closing_records_cash_and_stock_variances(): void
    {
        $admin = User::factory()->admin()->create();
        $sales = User::factory()->create();
        $product = Product::factory()->create();
        $locations = app(StockLocationService::class);
        $salesLocation = $locations->forSales($sales);
        $adminWarehouse = $locations->adminWarehouse();
        app(AdjustStock::class)->execute($salesLocation, $product, 12, 'Stok closing', $admin);
        $route = DailyRoute::factory()->create([
            'sales_id' => $sales->id,
            'route_date' => today(),
            'status' => DailyRouteStatus::PendingReview,
            'created_by' => $admin->id,
        ]);
        $pickup = StockTransfer::create([
            'transfer_type' => StockTransferType::IssueToSales,
            'source_location_id' => $adminWarehouse->id,
            'destination_location_id' => $salesLocation->id,
            'sales_id' => $sales->id,
            'daily_route_id' => $route->id,
            'status' => StockTransferStatus::Received,
            'created_by' => $admin->id,
        ]);
        $return = StockTransfer::create([
            'transfer_type' => StockTransferType::ReturnFromSales,
            'pickup_transfer_id' => $pickup->id,
            'source_location_id' => $salesLocation->id,
            'destination_location_id' => $adminWarehouse->id,
            'sales_id' => $sales->id,
            'status' => StockTransferStatus::PendingApproval,
            'created_by' => $sales->id,
        ]);
        $item = $return->items()->create([
            'product_id' => $product->id,
            'quantity_issued' => 12,
        ]);
        $settlement = $return->returnSettlement()->create([
            'pickup_transfer_id' => $pickup->id,
            'daily_route_id' => $route->id,
            'sales_id' => $sales->id,
            'settlement_date' => today(),
            'status' => SalesSettlementStatus::Pending,
            'total_collection' => 10_000,
            'submitted_at' => now(),
        ]);

        $approved = app(ApproveSalesReturn::class)->execute(
            $return,
            $admin,
            8_000,
            [['item_id' => $item->id, 'quantity_received' => 10]],
            'Uang kurang Rp2.000 dan stok kurang 2 bungkus.',
        );

        $this->assertSame(SalesSettlementStatus::Approved, $approved->status);
        $this->assertSame(8_000, $approved->actual_cash_received);
        $this->assertSame(-2_000, $approved->cash_difference);
        $this->assertSame(2, $approved->stock_shortage_pieces);
        $this->assertSame(0, (int) StockBalance::where('stock_location_id', $salesLocation->id)->where('product_id', $product->id)->value('quantity_pieces'));
        $this->assertSame(10, (int) StockBalance::where('stock_location_id', $adminWarehouse->id)->where('product_id', $product->id)->value('quantity_pieces'));
        $this->assertSame(DailyRouteStatus::Completed, $route->fresh()->status);
        $this->assertDatabaseHas('stock_movements', [
            'movement_type' => 'loss',
            'quantity_pieces' => 2,
            'reference_id' => $return->id,
        ]);
        $this->assertSame($settlement->id, $approved->id);
    }

    public function test_sales_receipt_is_visible_only_to_its_sales_owner_and_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create();
        $otherSales = User::factory()->create();
        $store = Store::factory()->create(['created_by' => $admin->id]);
        $visit = StoreVisit::create([
            'store_id' => $store->id,
            'sales_id' => $owner->id,
            'visit_number' => 1,
            'visit_status' => VisitStatus::Visited,
            'arrived_at' => now(),
            'completed_at' => now(),
            'latitude' => -7.7,
            'longitude' => 113.4,
        ]);

        $this->actingAs($owner)->get(route('sales.receipts.show', $visit))
            ->assertOk()
            ->assertSee('PANCOR MAS')
            ->assertSee($store->name);
        $this->actingAs($admin)->get(route('sales.receipts.show', $visit))->assertOk();
        $this->actingAs($otherSales)->get(route('sales.receipts.show', $visit))->assertForbidden();
    }
}
