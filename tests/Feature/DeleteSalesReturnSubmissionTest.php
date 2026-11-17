<?php

namespace Tests\Feature;

use App\Actions\DeleteSalesReturnSubmission;
use App\Enums\DailyRouteStatus;
use App\Enums\SalesSettlementStatus;
use App\Enums\StockTransferStatus;
use App\Enums\StockTransferType;
use App\Enums\UserRole;
use App\Filament\Resources\SalesStockTransfers\Pages\ListSalesStockTransfers;
use App\Models\DailyRoute;
use App\Models\DailySalesSettlement;
use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\User;
use App\Services\ActiveSalesStock;
use App\Services\StockLocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class DeleteSalesReturnSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_developer_can_delete_pending_sales_submission_and_reopen_the_route(): void
    {
        [$developer, $sales, $pickup, $return, $settlement, $route, $item] = $this->pendingSubmission();

        app(DeleteSalesReturnSubmission::class)->execute($return, $developer);

        $this->assertDatabaseMissing('stock_transfers', ['id' => $return->id]);
        $this->assertDatabaseMissing('stock_transfer_items', ['id' => $item->id]);
        $this->assertDatabaseMissing('daily_sales_settlements', ['id' => $settlement->id]);
        $this->assertSame(DailyRouteStatus::Scheduled, $route->fresh()->status);
        $this->assertTrue(app(ActiveSalesStock::class)->find($sales)?->is($pickup) ?? false);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'distribution.closing_submission_deleted',
            'user_id' => $developer->id,
        ]);
    }

    public function test_developer_cannot_delete_submission_that_is_already_approved(): void
    {
        [$developer, , , $return] = $this->pendingSubmission();
        $return->update(['status' => StockTransferStatus::Received]);

        $this->expectException(ValidationException::class);

        app(DeleteSalesReturnSubmission::class)->execute($return, $developer);
    }

    public function test_admin_cannot_delete_sales_submission(): void
    {
        [, , , $return] = $this->pendingSubmission();
        $admin = User::factory()->admin()->create();

        $this->expectException(ValidationException::class);

        app(DeleteSalesReturnSubmission::class)->execute($return, $admin);
    }

    public function test_developer_can_delete_from_distribution_page(): void
    {
        [$developer, , , $return] = $this->pendingSubmission();
        $this->actingAs($developer);

        Livewire::test(ListSalesStockTransfers::class)
            ->set('activeTab', 'proposal')
            ->assertSee('Hapus Pengajuan')
            ->call('deleteSubmission', $return->id);

        $this->assertDatabaseMissing('stock_transfers', ['id' => $return->id]);
    }

    public function test_admin_does_not_see_delete_button_on_distribution_page(): void
    {
        [, , , $return] = $this->pendingSubmission();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);
        Livewire::test(ListSalesStockTransfers::class)
            ->set('activeTab', 'proposal')
            ->assertDontSee('Hapus Pengajuan');
    }

    public function test_admin_calling_delete_endpoint_does_not_remove_the_submission(): void
    {
        [, , , $return] = $this->pendingSubmission();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        Livewire::test(ListSalesStockTransfers::class)
            ->call('deleteSubmission', $return->id);

        $this->assertDatabaseHas('stock_transfers', ['id' => $return->id]);
    }

    /**
     * @return array{0: User, 1: User, 2: StockTransfer, 3: StockTransfer, 4: DailySalesSettlement, 5: DailyRoute, 6: StockTransferItem}
     */
    private function pendingSubmission(): array
    {
        $admin = User::factory()->admin()->create();
        $developer = User::factory()->create(['role' => UserRole::Developer]);
        $sales = User::factory()->create();
        $product = Product::factory()->create();
        $locations = app(StockLocationService::class);

        $route = DailyRoute::factory()->create([
            'sales_id' => $sales->id,
            'route_date' => today(),
            'status' => DailyRouteStatus::PendingReview,
            'created_by' => $admin->id,
        ]);

        $pickup = StockTransfer::create([
            'transfer_type' => StockTransferType::IssueToSales,
            'source_location_id' => $locations->adminWarehouse()->id,
            'destination_location_id' => $locations->forSales($sales)->id,
            'sales_id' => $sales->id,
            'daily_route_id' => $route->id,
            'status' => StockTransferStatus::Received,
            'received_at' => now(),
            'created_by' => $admin->id,
        ]);

        $return = StockTransfer::create([
            'transfer_type' => StockTransferType::ReturnFromSales,
            'pickup_transfer_id' => $pickup->id,
            'source_location_id' => $locations->forSales($sales)->id,
            'destination_location_id' => $locations->adminWarehouse()->id,
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

        return [$developer, $sales, $pickup, $return, $settlement, $route, $item];
    }
}
