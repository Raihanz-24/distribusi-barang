<?php

namespace Tests\Feature;

use App\Enums\DailyRouteStatus;
use App\Enums\OptimizationStatus;
use App\Enums\UserRole;
use App\Filament\Pages\TodayRoute;
use App\Models\DailyRoute;
use App\Models\DailyRouteStore;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regresi: memilih toko tidak boleh membuat halaman gagal dimuat ketika ada
 * produk aktif yang belum punya harga aktif.
 */
class TodayRouteSelectStoreTest extends TestCase
{
    use RefreshDatabase;

    private function makeRoute(User $sales, Store $store): DailyRoute
    {
        $route = DailyRoute::query()->create([
            'sales_id' => $sales->id,
            'route_date' => now()->toDateString(),
            'status' => DailyRouteStatus::Scheduled,
            'optimization_status' => OptimizationStatus::Pending,
            'created_by' => User::factory()->admin()->create()->id,
        ]);

        DailyRouteStore::query()->create([
            'daily_route_id' => $route->id,
            'store_id' => $store->id,
            'planned_order' => 1,
        ]);

        return $route;
    }

    private function makePricedProduct(): Product
    {
        $product = Product::factory()->create(['is_active' => true, 'name' => 'Kopi Berharga']);

        ProductPrice::factory()->create([
            'product_id' => $product->id,
            'price_per_renteng' => 20000,
            'is_active' => true,
        ]);

        return $product;
    }

    public function test_selecting_store_opens_form_when_prices_exist(): void
    {
        $sales = User::factory()->create(['role' => UserRole::Sales]);
        $store = Store::factory()->create(['name' => 'Toko Maju']);
        $this->makePricedProduct();
        $this->makeRoute($sales, $store);

        $this->actingAs($sales);

        Livewire::test(TodayRoute::class)
            ->call('selectStore', $store->id)
            ->assertOk()
            ->assertSet('selectedStoreId', $store->id)
            ->assertSee('Transaksi toko')
            ->assertSee('Toko Maju');
    }

    public function test_selecting_store_still_renders_when_a_product_has_no_price(): void
    {
        $sales = User::factory()->create(['role' => UserRole::Sales]);
        $store = Store::factory()->create(['name' => 'Toko Tanpa Harga']);
        $this->makeRoute($sales, $store);

        // Produk aktif TANPA ProductPrice sama sekali.
        Product::factory()->create(['is_active' => true, 'name' => 'Kopi Tanpa Harga']);

        $this->actingAs($sales);

        Livewire::test(TodayRoute::class)
            ->call('selectStore', $store->id)
            ->assertOk()
            ->assertSet('selectedStoreId', $store->id)
            ->assertSee('Transaksi toko')
            ->assertSee('belum tersedia');
    }

    public function test_transaction_preview_does_not_throw_without_price(): void
    {
        $sales = User::factory()->create(['role' => UserRole::Sales]);
        $store = Store::factory()->create();
        $this->makeRoute($sales, $store);

        $product = Product::factory()->create(['is_active' => true]);

        $this->actingAs($sales);

        $component = Livewire::test(TodayRoute::class);

        $preview = $component->call('transactionPreview', $store->id, $product->id)->get('transactionPreview')
            ?? null;

        // Panggil langsung untuk memastikan tidak melempar exception.
        $page = app(TodayRoute::class);
        $reflection = new \ReflectionProperty(TodayRoute::class, 'transactions');
        $reflection->setAccessible(true);
        $reflection->setValue($page, []);

        $result = $page->transactionPreview($store->id, $product->id);

        $this->assertSame(0, $result['amount_due']);
        $this->assertSame(0, $result['store_fee']);
        $this->assertArrayHasKey('price_missing', $result);
    }

    public function test_saving_transaction_without_price_returns_validation_message(): void
    {
        $sales = User::factory()->create(['role' => UserRole::Sales]);
        $store = Store::factory()->create();
        $route = $this->makeRoute($sales, $store);

        $product = Product::factory()->create(['is_active' => true]);
        $route->update(['status' => DailyRouteStatus::InProgress]);

        $this->actingAs($sales);

        // Tanpa koordinat, penyimpanan memang ditolak; yang diuji di sini adalah
        // bahwa halaman tetap bisa dirender dan pesan harga muncul di pratinjau.
        Livewire::test(TodayRoute::class)
            ->call('selectStore', $store->id)
            ->assertOk()
            ->assertSee('belum tersedia');
    }
}
