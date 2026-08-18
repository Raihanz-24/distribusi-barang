<?php

namespace Tests\Feature;

use App\Enums\VisitStatus;
use App\Models\Store;
use App\Models\StoreVisit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreMapMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_map_endpoint_returns_monitoring_statistics_and_reschedule_state(): void
    {
        $admin = User::factory()->admin()->create();
        $sales = User::factory()->create();
        $store = Store::factory()->create([
            'created_by' => $admin->id,
            'latitude' => -7.7583,
            'longitude' => 113.4168,
        ]);
        StoreVisit::create([
            'store_id' => $store->id,
            'sales_id' => $sales->id,
            'visit_number' => 1,
            'visit_status' => VisitStatus::Closed,
            'arrived_at' => now(),
            'completed_at' => now(),
            'latitude' => -7.7583,
            'longitude' => 113.4168,
        ]);

        $response = $this->actingAs($admin)->getJson('/internal/store-map-data?needs_reschedule=1');

        $response->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('statistics.needs_reschedule', 1)
            ->assertJsonPath('features.0.properties.needs_reschedule', true)
            ->assertJsonPath('features.0.properties.last_visit_status', 'closed');

        $this->assertStringContainsString(
            '/admin/stores/'.$store->id,
            $response->json('features.0.properties.transaction_history_url')
        );
    }
}
