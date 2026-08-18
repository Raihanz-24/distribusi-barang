<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UsernameLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_uses_custom_coffee_design_and_username_field(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('Pancor Mas')
            ->assertSee('Masuk ke akun Anda')
            ->assertSee('Username')
            ->assertSee('Unduh Aplikasi')
            ->assertDontSee('Alamat email');
    }

    public function test_active_user_can_login_with_username_instead_of_email(): void
    {
        $user = User::factory()->admin()->create([
            'username' => 'admin_kopi',
            'password' => 'rahasia-kopi',
        ]);

        Livewire::test(Login::class)
            ->fillForm([
                'username' => 'ADMIN_KOPI',
                'password' => 'rahasia-kopi',
                'remember' => false,
            ])
            ->call('authenticate');

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_failure_does_not_reveal_whether_username_exists(): void
    {
        User::factory()->admin()->create(['username' => 'admin_kopi']);

        Livewire::test(Login::class)
            ->fillForm([
                'username' => 'admin_kopi',
                'password' => 'salah',
            ])
            ->call('authenticate')
            ->assertHasFormErrors(['username']);
    }
}
