<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\StockLocationService;
use Illuminate\Database\Seeder;

class StockLocationSeeder extends Seeder
{
    public function run(): void
    {
        $locations = app(StockLocationService::class);
        $locations->mainWarehouse();
        $locations->adminWarehouse();

        User::query()
            ->where('role', UserRole::Sales)
            ->where('is_active', true)
            ->each(fn (User $sales) => $locations->forSales($sales));
    }
}
