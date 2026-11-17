<?php

namespace Tests\Feature;

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
}
