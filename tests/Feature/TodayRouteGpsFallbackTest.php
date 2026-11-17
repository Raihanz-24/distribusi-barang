<?php

namespace Tests\Feature;

use App\Actions\AdjustStock;
use App\Actions\RecordStoreTransaction;
use App\Data\Coordinate;
use App\Enums\DailyRouteStatus;
use App\Enums\OptimizationStatus;
use App\Enums\StockTransferStatus;
use App\Enums\StockTransferType;
use App\Enums\UserRole;
use App\Models\DailyRoute;
use App\Models\DailyRouteStore;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\StockTransfer;
use App\Models\Store;
use App\Models\StoreVisit;
use App\Models\User;
use App\Services\StockLocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Regresi aturan radius: data toko (kunjungan/transaksi) hanya boleh direkam
 * bila Sales berada dalam radius tertentu dari lokasi toko. Di luar radius,
 * pencatatan ditolak. Koordinat toko tidak lagi dapat dipakai sebagai pengganti
 * GPS perangkat.
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

        $locations = app(StockLocationService::class);
        StockTransfer::query()->create([
            'transfer_type' => StockTransferType::IssueToSales,
            'daily_route_id' => $route->id,
            'sales_id' => $sales->id,
            'source_location_id' => $locations->adminWarehouse()->id,
            'destination_location_id' => $locations->forSales($sales)->id,
            'status' => StockTransferStatus::Received,
            'created_by' => $route->created_by,
            'received_at' => now(),
        ]);

        return $store;
    }

    private function makeProductWithPrice(): Product
    {
        $product = Product::factory()->create(['is_active' => true, 'pieces_per_renteng' => 12]);
        ProductPrice::factory()->create([
            'product_id' => $product->id,
            'price_per_renteng' => 20000,
            'is_active' => true,
        ]);

        return $product;
    }

    public function test_transaction_within_radius_is_recorded(): void
    {
        $sales = User::factory()->create(['role' => UserRole::Sales]);
        $store = $this->makeStoreAndRoute($sales);
        $product = $this->makeProductWithPrice();

        app(AdjustStock::class)->execute(
            app(StockLocationService::class)->forSales($sales),
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
            null,
        );

        $this->assertInstanceOf(StoreVisit::class, $visit);
        $this->assertSame(0, $visit->distance_to_store_meters);
    }

    public function test_far_position_is_rejected_even_with_note(): void
    {
        $sales = User::factory()->create(['role' => UserRole::Sales]);
        $store = $this->makeStoreAndRoute($sales);
        $product = $this->makeProductWithPrice();

        $this->expectException(ValidationException::class);

        app(RecordStoreTransaction::class)->execute(
            $sales,
            $store,
            // ~11 km dari toko.
            new Coordinate(-7.87, 113.42),
            [$product->id => ['sold' => 0, 'deposited' => 3, 'withdrawn' => 0]],
            null,
            0,
            0,
            'GPS perangkat tidak tersedia; memakai koordinat toko',
        );
    }

    public function test_store_without_coordinates_is_rejected(): void
    {
        $sales = User::factory()->create(['role' => UserRole::Sales]);
        // Store tanpa koordinat (latitude/longitude null).
        $store = Store::factory()->create([
            'name' => 'Toko Tanpa Koordinat',
            'latitude' => null,
            'longitude' => null,
        ]);
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
        $locations = app(StockLocationService::class);
        StockTransfer::query()->create([
            'transfer_type' => StockTransferType::IssueToSales,
            'daily_route_id' => $route->id,
            'sales_id' => $sales->id,
            'source_location_id' => $locations->adminWarehouse()->id,
            'destination_location_id' => $locations->forSales($sales)->id,
            'status' => StockTransferStatus::Received,
            'created_by' => $route->created_by,
            'received_at' => now(),
        ]);
        $product = $this->makeProductWithPrice();

        $this->expectException(ValidationException::class);

        app(RecordStoreTransaction::class)->execute(
            $sales,
            $store,
            new Coordinate(-7.7721411, 113.4157336),
            [$product->id => ['sold' => 0, 'deposited' => 3, 'withdrawn' => 0]],
            null,
            0,
            0,
            null,
        );
    }
}
