<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserManagementSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_can_open_user_and_audit_management(): void
    {
        $admin = User::factory()->admin()->create();
        $sales = User::factory()->create();

        $this->actingAs($admin)->get('/admin/users')->assertOk();
        $this->actingAs($admin)->get('/admin/audit-logs')->assertOk();
        $this->actingAs($sales)->get('/admin/users')->assertForbidden();
        $this->actingAs($sales)->get('/admin/audit-logs')->assertForbidden();
    }

    public function test_inactive_user_is_rejected_by_internal_endpoints(): void
    {
        $sales = User::factory()->create(['is_active' => false]);

        $this->actingAs($sales)
            ->getJson('/internal/nearby-stores?latitude=-7.75&longitude=113.41')
            ->assertForbidden();
    }

    public function test_username_is_normalized_before_storage(): void
    {
        $user = User::factory()->create(['username' => '  SALES_BARU  ']);

        $this->assertSame('sales_baru', $user->fresh()->username);
    }

    public function test_last_active_admin_cannot_be_disabled_or_downgraded(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);
        Livewire::test(EditUser::class, ['record' => $admin->getRouteKey()])
            ->fillForm([
                'name' => $admin->name,
                'username' => $admin->username,
                'email' => null,
                'role' => UserRole::Sales->value,
                'is_active' => true,
            ])
            ->call('save')
            ->assertHasErrors(['data.role']);

        $this->assertTrue($admin->fresh()->isAdmin());
        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_web_responses_include_security_headers(): void
    {
        $this->get('/')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'geolocation=(self), camera=(), microphone=()');
    }
}
