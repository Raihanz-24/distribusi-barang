<?php

namespace Tests\Feature;

use App\Enums\StockTransferType;
use App\Enums\StockTransferStatus;
use App\Enums\StockUnit;
use App\Filament\Resources\SalesStockTransfers\Pages\CreateSalesStockTransfer;
use App\Models\DailyRoute;
use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\User;
use App\Services\StockLocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CreateSalesStockTransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_must_link_sales_stock_delivery_to_assigned_daily_route(): void
    {
        $admin = User::factory()->admin()->create();
        $sales = User::factory()->create();
        $product = Product::factory()->create();
        $this->actingAs($admin);

        Livewire::test(CreateSalesStockTransfer::class)
            ->fillForm([
                'transfer_type' => StockTransferType::IssueToSales->value,
                'sales_id' => $sales->id,
                'items' => [[
                    'product_id' => $product->id,
                    'quantity_entered' => 5,
                    'quantity_unit' => StockUnit::Renteng->value,
                ]],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        // Rute kerja hari ini dibuat otomatis untuk Sales dan dikaitkan.
        $route = DailyRoute::query()
            ->where('sales_id', $sales->id)
            ->whereDate('route_date', today())
            ->firstOrFail();

        $this->assertDatabaseHas('stock_transfers', [
            'transfer_type' => StockTransferType::IssueToSales->value,
            'sales_id' => $sales->id,
            'daily_route_id' => $route->id,
        ]);

        $transfer = StockTransfer::query()->where('daily_route_id', $route->id)->firstOrFail();

        $this->get('/admin/sales-stock-transfers/'.$transfer->id)
            ->assertOk()
            ->assertSee($transfer->number)
            ->assertSee($sales->name)
            ->assertSee('Jadwal kerja')
            ->assertSee($product->name);
    }

    public function test_admin_can_create_new_distribution_after_previous_distribution_is_returned(): void
    {
        $admin = User::factory()->admin()->create();
        $sales = User::factory()->create();
        $product = Product::factory()->create();
        $route = DailyRoute::query()->create([
            'sales_id' => $sales->id,
            'route_date' => today(),
            'status' => 'completed',
            'optimization_status' => 'completed',
            'created_by' => $admin->id,
        ]);
        $locations = app(StockLocationService::class);

        $previousPickup = StockTransfer::query()->create([
            'transfer_type' => StockTransferType::IssueToSales,
            'daily_route_id' => $route->id,
            'sales_id' => $sales->id,
            'source_location_id' => $locations->adminWarehouse()->id,
            'destination_location_id' => $locations->forSales($sales)->id,
            'status' => StockTransferStatus::Received,
            'created_by' => $admin->id,
        ]);
        StockTransfer::query()->create([
            'transfer_type' => StockTransferType::ReturnFromSales,
            'pickup_transfer_id' => $previousPickup->id,
            'sales_id' => $sales->id,
            'source_location_id' => $locations->forSales($sales)->id,
            'destination_location_id' => $locations->adminWarehouse()->id,
            'status' => StockTransferStatus::Received,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin);

        Livewire::test(CreateSalesStockTransfer::class)
            ->fillForm([
                'transfer_type' => StockTransferType::IssueToSales->value,
                'sales_id' => $sales->id,
                'items' => [[
                    'product_id' => $product->id,
                    'quantity_entered' => 5,
                    'quantity_unit' => StockUnit::Renteng->value,
                ]],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(2, StockTransfer::query()
            ->where('transfer_type', StockTransferType::IssueToSales)
            ->where('sales_id', $sales->id)
            ->count());
    }
}
