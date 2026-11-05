<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Pages\DeveloperDatabase;
use App\Filament\Resources\Users\UserResource;
use App\Models\Product;
use App\Models\User;
use App\Services\DatabaseMaintenance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class DeveloperDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_cannot_see_developer_user_or_database_tools(): void
    {
        $admin = User::factory()->admin()->create();
        $developer = User::factory()->create(['role' => UserRole::Developer]);

        $this->actingAs($admin);

        $this->assertFalse(DeveloperDatabase::canAccess());
        $this->assertFalse(UserResource::getEloquentQuery()->whereKey($developer)->exists());
        $this->assertFalse($admin->can('view', $developer));
        $this->get(route('developer.database.backup'))->assertForbidden();
    }

    public function test_developer_can_open_database_tools_and_download_backup(): void
    {
        $developer = User::factory()->create(['role' => UserRole::Developer]);
        $this->actingAs($developer);

        Livewire::test(DeveloperDatabase::class)
            ->assertSee('Pemeliharaan Database')
            ->assertSee('users')
            ->assertSee('DILINDUNGI');

        $response = $this->get(route('developer.database.backup'));

        $response->assertOk()
            ->assertHeader('content-type', 'application/sql; charset=UTF-8');
        $this->assertStringContainsString('CREATE TABLE', $response->streamedContent());
        $this->assertStringContainsString('INSERT INTO', $response->streamedContent());
    }

    public function test_developer_can_clear_business_table_but_not_users_or_migrations(): void
    {
        User::factory()->create(['role' => UserRole::Developer]);
        Product::factory()->count(2)->create();
        $maintenance = app(DatabaseMaintenance::class);

        $this->assertSame(2, $maintenance->clear('products'));
        $this->assertDatabaseCount('products', 0);

        foreach (['users', 'migrations'] as $protectedTable) {
            try {
                $maintenance->clear($protectedTable);
                $this->fail("Tabel {$protectedTable} seharusnya dilindungi.");
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('table', $exception->errors());
            }
        }

        $this->assertDatabaseCount('users', 1);
    }

    public function test_create_developer_command_is_available(): void
    {
        $this->artisan('app:create-developer', [
            'username' => 'devops',
            '--name' => 'Developer Utama',
            '--email' => 'developer@example.test',
            '--password' => 'StrongDeveloper123',
        ])->assertSuccessful();

        $this->assertDatabaseHas('users', [
            'username' => 'devops',
            'role' => UserRole::Developer->value,
            'is_active' => true,
        ]);
    }
}
