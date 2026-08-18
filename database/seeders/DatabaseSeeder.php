<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $adminPassword = env('SEED_ADMIN_PASSWORD');

        if (app()->environment('production') && blank($adminPassword)) {
            throw new RuntimeException('SEED_ADMIN_PASSWORD wajib diatur sebelum menjalankan seeder di production.');
        }

        User::query()->updateOrCreate(
            ['username' => env('SEED_ADMIN_USERNAME', 'admin')],
            [
                'name' => 'Administrator',
                'email' => env('SEED_ADMIN_EMAIL'),
                'password' => bcrypt($adminPassword ?: 'admin12345'),
                'role' => UserRole::Admin,
                'is_active' => true,
            ],
        );

        $this->call(ProductSeeder::class);
        $this->call(StockLocationSeeder::class);

        if (app()->environment('local')) {
            $this->call(LocalDemoSeeder::class);
        }
    }
}
