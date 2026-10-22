<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\VisitStatus;
use App\Models\Store;
use App\Models\StoreVisit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesReceiptTest extends TestCase
{
    use RefreshDatabase;

    private function makeVisit(User $sales): StoreVisit
    {
        $store = Store::factory()->create(['name' => 'Toko Uji Struk']);

        return StoreVisit::query()->create([
            'store_id' => $store->id,
            'sales_id' => $sales->id,
            'visit_number' => (int) StoreVisit::where('sales_id', $sales->id)->max('visit_number') + 1,
            'visit_status' => VisitStatus::Visited,
            'arrived_at' => now(),
            'latitude' => -7.7583,
            'longitude' => 113.4168,
            'amount_due' => 50000,
            'amount_paid' => 50000,
            'debt_amount' => 0,
            'completed_at' => now(),
        ]);
    }

    public function test_guest_is_redirected_to_filament_login_not_error(): void
    {
        $visit = $this->makeVisit(User::factory()->create());

        $this->get("/sales/receipts/{$visit->id}")
            ->assertRedirect(route('filament.admin.auth.login'));
    }

    public function test_owning_sales_can_open_receipt(): void
    {
        $sales = User::factory()->create(['role' => UserRole::Sales]);
        $visit = $this->makeVisit($sales);

        $this->actingAs($sales)
            ->get("/sales/receipts/{$visit->id}")
            ->assertOk()
            ->assertSee('Toko Uji Struk');
    }

    public function test_admin_can_open_any_receipt(): void
    {
        $visit = $this->makeVisit(User::factory()->create());

        $this->actingAs(User::factory()->admin()->create())
            ->get("/sales/receipts/{$visit->id}")
            ->assertOk();
    }

    public function test_other_sales_cannot_open_receipt(): void
    {
        $visit = $this->makeVisit(User::factory()->create());

        $this->actingAs(User::factory()->create(['role' => UserRole::Sales]))
            ->get("/sales/receipts/{$visit->id}")
            ->assertForbidden();
    }

    public function test_receipt_uses_58mm_paper_by_default(): void
    {
        $sales = User::factory()->create(['role' => UserRole::Sales]);
        $visit = $this->makeVisit($sales);

        $this->actingAs($sales)
            ->get("/sales/receipts/{$visit->id}")
            ->assertOk()
            ->assertSee('width:58mm', false)
            ->assertSee('size:58mm auto', false);
    }

    public function test_receipt_follows_configured_80mm_paper(): void
    {
        config(['sales.receipt.paper_width_mm' => 80]);

        $sales = User::factory()->create(['role' => UserRole::Sales]);
        $visit = $this->makeVisit($sales);

        $this->actingAs($sales)
            ->get("/sales/receipts/{$visit->id}")
            ->assertOk()
            ->assertSee('width:80mm', false)
            ->assertSee('size:80mm auto', false);
    }

    public function test_receipt_rejects_unsupported_paper_width(): void
    {
        config(['sales.receipt.paper_width_mm' => 999]);

        $sales = User::factory()->create(['role' => UserRole::Sales]);
        $visit = $this->makeVisit($sales);

        $this->actingAs($sales)
            ->get("/sales/receipts/{$visit->id}")
            ->assertOk()
            ->assertSee('width:58mm', false);
    }

    public function test_receipt_prints_store_brand_from_config(): void
    {
        config([
            'sales.receipt.store_name' => 'TOKO UJI',
            'sales.receipt.store_tagline' => 'Tagline Uji',
            'sales.receipt.footer' => 'Footer Uji',
        ]);

        $sales = User::factory()->create(['role' => UserRole::Sales]);
        $visit = $this->makeVisit($sales);

        $this->actingAs($sales)
            ->get("/sales/receipts/{$visit->id}")
            ->assertOk()
            ->assertSee('TOKO UJI')
            ->assertSee('Tagline Uji')
            ->assertSee('Footer Uji');
    }
}
