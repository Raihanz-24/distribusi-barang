<?php

namespace Tests\Feature;

use App\Enums\StockTransferStatus;
use App\Enums\StockTransferType;
use App\Models\DailyRoute;
use App\Models\StockTransfer;
use App\Models\User;
use App\Services\StockLocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesLocationTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_can_save_and_refresh_their_latest_gps_location(): void
    {
        $sales = User::factory()->create();
        $this->createActiveDelivery($sales);

        $this->actingAs($sales)->postJson(route('internal.sales-location.update'), [
            'latitude' => -7.7583,
            'longitude' => 113.4158,
            'accuracy' => 12.4,
            'speed' => 4.2,
        ])->assertOk()->assertJson(['saved' => true]);

        $this->actingAs($sales)->postJson(route('internal.sales-location.update'), [
            'latitude' => -7.7600,
            'longitude' => 113.4200,
            'accuracy' => 8,
        ])->assertOk();

        $this->assertDatabaseCount('sales_locations', 1);
        $this->assertDatabaseHas('sales_locations', [
            'user_id' => $sales->id,
            'latitude' => -7.7600000,
            'longitude' => 113.4200000,
            'accuracy_meters' => 8,
        ]);
    }

    public function test_location_is_not_recorded_without_an_active_delivery(): void
    {
        $sales = User::factory()->create();

        $this->actingAs($sales)->postJson(route('internal.sales-location.update'), [
            'latitude' => -7.7583,
            'longitude' => 113.4158,
        ])->assertStatus(409)->assertJson(['saved' => false]);

        $this->assertDatabaseCount('sales_locations', 0);
    }

    public function test_admin_cannot_write_a_sales_gps_location(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->postJson(route('internal.sales-location.update'), [
                'latitude' => -7.7583,
                'longitude' => 113.4158,
            ])
            ->assertForbidden();
    }

    public function test_active_sales_distribution_is_labelled_in_transit(): void
    {
        $transfer = new StockTransfer([
            'transfer_type' => StockTransferType::IssueToSales,
            'status' => StockTransferStatus::Received,
        ]);
        $transfer->setRelation('returnTransfer', null);

        $this->assertSame('Dalam perjalanan', $transfer->displayStatusLabel());
    }

    private function createActiveDelivery(User $sales): StockTransfer
    {
        $admin = User::factory()->admin()->create();
        $route = DailyRoute::factory()->create([
            'sales_id' => $sales->id,
            'created_by' => $admin->id,
        ]);
        $locations = app(StockLocationService::class);

        return StockTransfer::create([
            'transfer_type' => StockTransferType::IssueToSales,
            'daily_route_id' => $route->id,
            'sales_id' => $sales->id,
            'source_location_id' => $locations->adminWarehouse()->id,
            'destination_location_id' => $locations->forSales($sales)->id,
            'status' => StockTransferStatus::Received,
            'created_by' => $admin->id,
            'received_at' => now(),
        ]);
    }
}
