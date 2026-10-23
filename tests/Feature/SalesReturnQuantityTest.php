<?php

namespace Tests\Feature;

use App\Actions\AdjustStock;
use App\Actions\CompleteSalesPickup;
use App\Actions\RecordStoreTransaction;
use App\Actions\SubmitSalesReturn;
use App\Data\Coordinate;
use App\Enums\DailyRouteStatus;
use App\Enums\OptimizationStatus;
use App\Enums\StockTransferStatus;
use App\Enums\StockTransferType;
use App\Enums\UserRole;
use App\Filament\Resources\SalesStockTransfers\Pages\CreateSalesStockTransfer;
use App\Filament\Resources\SalesStockTransfers\Pages\ListSalesStockTransfers;
use App\Models\DailyRoute;
use App\Models\DailyRouteStore;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\StockBalance;
use App\Models\StockTransfer;
use App\Models\Store;
use App\Models\User;
use App\Services\StockLocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regresi alur distribusi: sisa stok yang dikembalikan HARUS dihitung sistem
 * dari saldo stok Sales, bukan diketik manual.
 *
 * Bug asli: form Distribusi menyediakan opsi "Sales → Gudang Admin" sehingga
 * admin bisa mengetik 40 bungkus padahal setelah titipan stok Sales tinggal 20.
 * Detail pengajuan pun tampil "40 / 40".
 */
class SalesReturnQuantityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $sales;

    private Product $product;

    private Store $store;

    private StockLocationService $locations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->sales = User::factory()->create(['role' => UserRole::Sales, 'name' => 'Sales Uji']);
        $this->product = Product::factory()->create([
            'is_active' => true,
            'pieces_per_renteng' => 12,
            'name' => 'Kopi Uji',
        ]);
        ProductPrice::factory()->create([
            'product_id' => $this->product->id,
            'price_per_renteng' => 20000,
            'is_active' => true,
        ]);
        $this->store = Store::factory()->create([
            'name' => 'Toko Uji',
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

    public function test_return_quantity_follows_remaining_sales_stock_after_deposit(): void
    {
        $pickup = $this->distribute(40);
        $this->depositToStore(20);

        // Saldo Sales memang berkurang menjadi 20.
        $this->assertSame(20, (int) StockBalance::query()
            ->where('stock_location_id', $this->locations->forSales($this->sales)->id)
            ->where('product_id', $this->product->id)
            ->value('quantity_pieces'));

        $settlement = app(SubmitSalesReturn::class)->execute($pickup, $this->sales);
        $return = $settlement->returnTransfer;

        $this->assertSame(20, (int) $return->items->sum('quantity_issued'), 'Sisa kembali harus 20 bungkus, bukan 40.');
        $this->assertSame(20, (int) $return->items->sum('quantity_entered'));
        $this->assertNotNull($settlement->returnSettlement ?? $settlement);
    }

    public function test_return_cannot_be_created_manually_through_the_form(): void
    {
        $this->distribute(40);
        $this->depositToStore(20);
        $this->actingAs($this->admin);

        // Opsi "Sales → Gudang Admin" tidak lagi tersedia pada form.
        $options = $this->creatableOptions();

        $this->assertArrayNotHasKey(StockTransferType::ReturnFromSales->value, $options,
            'Pengembalian tidak boleh dibuat manual karena jumlahnya harus dihitung sistem.');
        $this->assertArrayHasKey(StockTransferType::IssueToSales->value, $options);

        // Mencoba memaksa lewat form harus ditolak validasi.
        Livewire::test(CreateSalesStockTransfer::class)
            ->fillForm([
                'transfer_type' => StockTransferType::ReturnFromSales->value,
                'sales_id' => $this->sales->id,
                'items' => [[
                    'product_id' => $this->product->id,
                    'quantity_entered' => 40,
                    'quantity_unit' => 'piece',
                ]],
            ])
            ->call('create')
            ->assertHasFormErrors(['transfer_type']);

        $this->assertSame(0, StockTransfer::query()
            ->where('transfer_type', StockTransferType::ReturnFromSales)
            ->count());
    }

    public function test_admin_can_submit_the_closing_for_a_sales_and_stock_is_counted_by_system(): void
    {
        $pickup = $this->distribute(40);
        $this->depositToStore(20);
        $this->actingAs($this->admin);

        Livewire::test(ListSalesStockTransfers::class)
            ->set('activeTab', 'pickup')
            ->callTableAction('submitReturn', $pickup);

        $return = $pickup->fresh()->returnTransfer;

        $this->assertNotNull($return, 'Admin harus dapat mengajukan closing atas nama Sales.');
        $this->assertSame(20, (int) $return->items->sum('quantity_issued'));
        $this->assertSame(20, (int) $return->items->sum('quantity_entered'));
    }

    public function test_return_detail_modal_shows_expected_and_received_clearly(): void
    {
        $pickup = $this->distribute(40);
        $this->depositToStore(20);

        $settlement = app(SubmitSalesReturn::class)->execute($pickup, $this->sales);
        $return = $settlement->returnTransfer;

        $html = view('filament.stock-transfers.return-detail', [
            'transfer' => $return->load([
                'sales', 'items.product',
                'returnSettlement.dailyRoute.visits.store',
                'returnSettlement.dailyRoute.visits.items.product',
            ]),
        ])->render();

        $text = preg_replace('/\s+/', ' ', strip_tags($html));

        $this->assertStringContainsString('20 bungkus', $text);
        $this->assertStringContainsString('menunggu pemeriksaan Admin', $text);
        $this->assertStringNotContainsString('40 bungkus', $text,
            'Detail pengajuan tidak boleh menampilkan 40 bungkus karena sisa stok tinggal 20.');
    }

    /**
     * @return array<string, string>
     */
    private function creatableOptions(): array
    {
        $method = new \ReflectionMethod(\App\Filament\Resources\SalesStockTransfers\SalesStockTransferResource::class, 'creatableTransferTypeOptions');
        $method->setAccessible(true);

        return $method->invoke(null);
    }
}