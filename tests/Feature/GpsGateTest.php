<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GpsGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_sees_gps_gate_and_admin_does_not(): void
    {
        $sales = User::factory()->create(['role' => UserRole::Sales]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($sales)->get('/admin')
            ->assertOk()
            ->assertSee('pancor-gps-gate')
            ->assertSee('GPS Belum Diaktifkan');

        $this->actingAs($admin)->get('/admin')
            ->assertOk()
            ->assertDontSee('pancor-gps-gate')
            ->assertDontSee('GPS Belum Diaktifkan');
    }

    public function test_gps_gate_is_present_on_sales_today_route_page(): void
    {
        $sales = User::factory()->create(['role' => UserRole::Sales]);

        $this->actingAs($sales)->get('/admin/today-route')
            ->assertOk()
            ->assertSee('GPS Belum Diaktifkan')
            ->assertSee('Coba Lagi');
    }
}
