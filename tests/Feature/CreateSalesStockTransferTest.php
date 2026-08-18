<?php

namespace Tests\Feature;

use App\Enums\DailyRouteStatus;
use App\Enums\StockTransferType;
use App\Enums\StockUnit;
use App\Filament\Resources\SalesStockTransfers\Pages\CreateSalesStockTransfer;
use App\Models\DailyRoute;
use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\User;
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
        $route = DailyRoute::factory()->create([
            'sales_id' => $sales->id,
            'route_date' => today()->addDay(),
            'status' => DailyRouteStatus::Scheduled,
            'created_by' => $admin->id,
        ]);
        $this->actingAs($admin);

        Livewire::test(CreateSalesStockTransfer::class)
            ->fillForm([
                'transfer_type' => StockTransferType::IssueToSales->value,
                'sales_id' => $sales->id,
                'daily_route_id' => $route->id,
                'items' => [[
                    'product_id' => $product->id,
                    'quantity_entered' => 5,
                    'quantity_unit' => StockUnit::Renteng->value,
                ]],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

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
}
