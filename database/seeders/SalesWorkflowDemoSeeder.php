<?php

namespace Database\Seeders;

use App\Actions\CompleteSalesPickup;
use App\Actions\MoveStock;
use App\Actions\RecordStoreTransaction;
use App\Actions\RecordStoreVisit;
use App\Actions\SubmitSalesReturn;
use App\Data\Coordinate;
use App\Enums\DailyRouteStatus;
use App\Enums\OptimizationStatus;
use App\Enums\StockMovementType;
use App\Enums\StockTransferStatus;
use App\Enums\StockTransferType;
use App\Enums\StockUnit;
use App\Enums\VisitStatus;
use App\Models\DailyRoute;
use App\Models\DailyRouteStore;
use App\Models\Product;
use App\Models\RouteArea;
use App\Models\StockTransfer;
use App\Models\Store;
use App\Models\User;
use App\Services\StockLocationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SalesWorkflowDemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('username', 'admin')->firstOrFail();
        $sales = User::query()->where('username', 'sales')->firstOrFail();
        $products = Product::query()->where('is_active', true)->orderBy('id')->take(2)->get();

        if ($products->count() < 2) {
            throw new RuntimeException('Minimal dua produk aktif diperlukan untuk membuat data dummy Sales.');
        }

        $stores = Store::query()
            ->where('is_active', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderBy('id')
            ->take(20)
            ->get();

        if ($stores->count() < 20) {
            throw new RuntimeException('Minimal 20 toko aktif dengan koordinat diperlukan.');
        }

        DB::transaction(function () use ($admin, $sales, $products, $stores): void {
            $area = RouteArea::query()->where('is_active', true)->first();
            $todayRoute = $this->route($sales, $admin, today()->toDateString(), $area, $stores->take(12));
            $todayPickup = StockTransfer::query()->where('daily_route_id', $todayRoute->id)->first();

            if (! $todayPickup) {
                $todayPickup = $this->pickup($todayRoute, $sales, $admin, $products, 10);
                app(CompleteSalesPickup::class)->execute($todayPickup, $admin);
                $this->seedTodayVisits($todayRoute, $sales, $admin, $products, $stores->take(12));
                app(SubmitSalesReturn::class)->execute($todayPickup->fresh(), $sales);
            }

            $tomorrowRoute = $this->route($sales, $admin, today()->addDay()->toDateString(), $area, $stores->slice(12, 8));

            if (! StockTransfer::query()->where('daily_route_id', $tomorrowRoute->id)->exists()) {
                $this->pickup($tomorrowRoute, $sales, $admin, $products, 5);
            }
        });
    }

    private function route(User $sales, User $admin, string $date, ?RouteArea $area, $stores): DailyRoute
    {
        $route = DailyRoute::query()->updateOrCreate(
            ['sales_id' => $sales->id, 'route_date' => $date],
            [
                'route_area_id' => $area?->id,
                'status' => DailyRouteStatus::Scheduled,
                'start_latitude' => -7.7583,
                'start_longitude' => 113.4168,
                'optimization_status' => OptimizationStatus::Pending,
                'created_by' => $admin->id,
            ],
        );

        if ($area) {
            $route->areas()->syncWithoutDetaching([$area->id => ['area_order' => 1]]);
        }

        foreach ($stores->values() as $index => $store) {
            DailyRouteStore::query()->updateOrCreate(
                ['daily_route_id' => $route->id, 'store_id' => $store->id],
                ['planned_order' => $index + 1, 'priority' => 100, 'visit_status' => VisitStatus::Pending],
            );
        }

        return $route;
    }

    private function pickup(DailyRoute $route, User $sales, User $admin, $products, int $rentengPerProduct): StockTransfer
    {
        $locations = app(StockLocationService::class);
        $transfer = StockTransfer::create([
            'transfer_type' => StockTransferType::IssueToSales,
            'source_location_id' => $locations->adminWarehouse()->id,
            'destination_location_id' => $locations->forSales($sales)->id,
            'sales_id' => $sales->id,
            'daily_route_id' => $route->id,
            'status' => StockTransferStatus::Draft,
            'notes' => 'Data dummy pengiriman stok untuk pengujian jadwal Sales.',
            'created_by' => $admin->id,
        ]);

        foreach ($products as $product) {
            $transfer->items()->create([
                'product_id' => $product->id,
                'quantity_entered' => $rentengPerProduct,
                'quantity_unit' => StockUnit::Renteng,
            ]);
        }

        return $transfer->load('items');
    }

    private function seedTodayVisits(DailyRoute $route, User $sales, User $admin, $products, $stores): void
    {
        $locations = app(StockLocationService::class);
        $salesLocation = $locations->forSales($sales);

        foreach ($stores->take(3)->values() as $storeIndex => $store) {
            $storeLocation = $locations->forStore($store);

            foreach ($products as $product) {
                app(MoveStock::class)->execute(
                    $product,
                    $salesLocation,
                    $storeLocation,
                    12,
                    StockMovementType::DepositToStore,
                    $admin,
                    notes: 'Titipan awal dummy sebelum transaksi kunjungan.',
                );
            }

            $quantities = match ($storeIndex) {
                0 => [
                    $products[0]->id => ['sold' => 12, 'deposited' => 12],
                    $products[1]->id => ['sold' => 6, 'deposited' => 12],
                ],
                1 => [
                    $products[0]->id => ['sold' => 3, 'deposited' => 12],
                    $products[1]->id => ['sold' => 9, 'deposited' => 0],
                ],
                default => [
                    $products[0]->id => ['sold' => 10, 'deposited' => 0],
                    $products[1]->id => ['sold' => 5, 'deposited' => 12],
                ],
            };

            app(RecordStoreTransaction::class)->execute(
                $sales,
                $store,
                new Coordinate((float) $store->latitude, (float) $store->longitude),
                $quantities,
                5,
            );
        }

        $closedStore = $stores->get(3);
        app(RecordStoreVisit::class)->execute(
            $sales,
            $closedStore,
            new Coordinate((float) $closedStore->latitude, (float) $closedStore->longitude),
            VisitStatus::Closed,
            ['location_accuracy' => 5],
        );
    }
}
