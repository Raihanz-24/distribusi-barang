<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FilamentPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_render_phase_one_pages(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/stores')->assertOk();
        $this->actingAs($admin)->get('/admin/stores/create')->assertOk();
        $this->actingAs($admin)->get('/admin/route-areas')->assertOk();
        $this->actingAs($admin)->get('/admin/route-areas/create')->assertOk();
        $this->actingAs($admin)->get('/admin/daily-routes')->assertOk();
        $this->actingAs($admin)->get('/admin/daily-routes/create')->assertOk();
        $this->actingAs($admin)->get('/admin/store-map')->assertOk();
        $this->actingAs($admin)->get('/admin/sales-deposits')
            ->assertOk()
            ->assertSee('Rekap Setoran Sales')
            ->assertSee('Export Excel')
            ->assertDontSee('Fee Sales');
        $this->actingAs($admin)->get('/admin/sales-stock-transfers')
            ->assertOk()
            ->assertDontSee('Rekap Fee Bulan Ini')
            ->assertDontSee('Fee Sales');
        $this->actingAs($admin)->get('/admin')
            ->assertOk()
            ->assertSee('Kontrol operasional')
            ->assertSee('Gudang');
    }

    public function test_sales_can_render_today_route_but_not_admin_map(): void
    {
        $sales = User::factory()->create(['role' => UserRole::Sales]);

        $this->actingAs($sales)->get('/admin/today-route')->assertOk();
        $this->actingAs($sales)->get('/admin/store-map')->assertForbidden();
        $this->actingAs($sales)->get('/admin/sales-deposits')->assertForbidden();
        $this->actingAs($sales)->get('/admin/sales-stock-transfers')
            ->assertOk()
            ->assertDontSee('Rekap Fee Bulan Ini')
            ->assertDontSee('Fee Sales');
        $this->actingAs($sales)->get('/admin')
            ->assertOk()
            ->assertSee('Jadwal dan progres saya')
            ->assertSee('Mulai transaksi toko');
    }
}
