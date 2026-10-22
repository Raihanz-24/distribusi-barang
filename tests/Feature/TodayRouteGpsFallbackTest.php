<?php

namespace Tests\Feature;

use App\Actions\RecordStoreTransaction;
use App\Data\Coordinate;
use App\Enums\UserRole;
use App\Filament\Pages\TodayRoute;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Store;
use App\Models\StoreVisit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regresi GPS: transaksi harus tetap dapat disimpan walau GPS perangkat tidak
 * tersedia atau akurasinya buruk (mis. lokasi berbasis IP dengan galat puluhan
 * kilometer, seperti yang terlihat pada data nyata).
 */
class TodayRouteGpsFallbackTest extends TestCase
{
    use RefreshDatabase;

    private function makeStoreAndRoute(User $sales): Store
    {
        $store = Store::factory()->create([
            'latitude' => -7.7721411,
            'longitude' => 113.4157336,
            'name' => 'Toko GPS',
        ]);

        $route = \App\Models\DailyRoute::query()->create([
            'sales_id' => $sales->id,
            'route_date' => now()->toDateString(),
            'status' => \App\Enums\DailyRouteStatus::Scheduled,
            'optimization_status' => \App\Enums\OptimizationStatus::Pending,
            'created_by' => User::factory()->admin()->create()->id,
        ]);

        \App\Models\DailyRouteStore::query()->create([
            'daily_route_id' => $route->id,
            'store_id' => $store->id,
            'planned_order' => 1,
        ]);

        return $store;
    }

    public function test_use_store_location_unlocks_saving(): void
    {
        $sales = User::factory()->create(['role' => UserRole::Sales]);
        $store = $this->makeStoreAndRoute($sales);

        $this->actingAs($sales);

        Livewire::test(TodayRoute::class)
            ->call('selectStore', $store->id)
            ->assertSet('currentLatitude', null)
            ->call('useStoreLocation', $store->id)
            ->assertSet('currentLatitude', $store->latitude + 0.0)
            ->assertSet('locationNote', 'GPS perangkat tidak tersedia; memakai koordinat toko');
    }

    public function test_imprecise_accuracy_is_flagged_and_recorded(): void
    {
        $sales = User::factory()->create(['role' => UserRole::Sales]);
        $store = $this->makeStoreAndRoute($sales);

        $this->actingAs($sales);

        $page = app(TodayRoute::class);
        // Akurasi 46.932 m seperti pada data nyata.
        $page->loadNearby(-7.7266944, 113.4952448, 46932.17);

        $this->assertNotNull($page->locationNote);
        $this->assertStringContainsString('Akurasi GPS rendah', $page->locationNote);
    }

    public function test_transaction_saves_with_store_coordinates_and_logs_note(): void
    {
        $sales = User::factory()->create(['role' => UserRole::Sales]);
        $store = $this->makeStoreAndRoute($sales);

        $product = Product::factory()->create(['is_active' => true, 'pieces_per_renteng' => 12]);
        ProductPrice::factory()->create([
            'product_id' => $product->id,
            'price_per_renteng' => 20000,
            'is_active' => true,
        ]);

        // Stok sales harus ada sebelum barang dititipkan ke toko.
        app(\App\Actions\AdjustStock::class)->execute(
            app(\App\Services\StockLocationService::class)->forSales($sales),
            $product,
            12,
            'Stok awal pengujian',
            User::factory()->admin()->create(),
        );

        $visit = app(RecordStoreTransaction::class)->execute(
            $sales,
            $store,
            new Coordinate((float) $store->latitude, (float) $store->longitude),
            [$product->id => ['sold' => 0, 'deposited' => 3, 'withdrawn' => 0]],
            null,
            0,
            0,
            'GPS perangkat tidak tersedia; memakai koordinat toko',
        );

        $this->assertInstanceOf(StoreVisit::class, $visit);
        $this->assertSame('GPS perangkat tidak tersedia; memakai koordinat toko', $visit->exception_reason);
        $this->assertNull($visit->location_accuracy);
        $this->assertSame(0, $visit->distance_to_store_meters);
    }

    public function test_far_position_without_note_is_still_rejected(): void
    {
        $sales = User::factory()->create(['role' => UserRole::Sales]);
        $store = $this->makeStoreAndRoute($sales);

        $product = Product::factory()->create(['is_active' => true, 'pieces_per_renteng' => 12]);
        ProductPrice::factory()->create([
            'product_id' => $product->id,
            'price_per_renteng' => 20000,
            'is_active' => true,
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(RecordStoreTransaction::class)->execute(
            $sales,
            $store,
            // ~11 km dari toko, tanpa catatan lokasi.
            new Coordinate(-7.87, 113.42),
            [$product->id => ['sold' => 0, 'deposited' => 3, 'withdrawn' => 0]],
            null,
            0,
            0,
            null,
        );
    }
}
