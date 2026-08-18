<?php

namespace Database\Seeders;

use App\Actions\OptimizeDailyRoute;
use App\Enums\DailyRouteStatus;
use App\Enums\OptimizationStatus;
use App\Enums\StoreStatus;
use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Models\DailyRoute;
use App\Models\RouteArea;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class LocalDemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('role', UserRole::Admin)->firstOrFail();
        $sales = User::query()->updateOrCreate(
            ['email' => 'sales@pancormas.test'],
            [
                'username' => 'sales',
                'name' => 'Sales Demo',
                'password' => Hash::make('sales12345'),
                'role' => UserRole::Sales,
                'is_active' => true,
                'phone' => '081234567890',
            ],
        );

        $stores = collect([
            ['TKO-DEMO-001', 'Toko Maju Jaya', 'Jl. Panglima Sudirman, Kraksaan', '351314', 'KRAKSAAN', -7.75830, 113.41680],
            ['TKO-DEMO-002', 'Toko Sinar Baru', 'Jl. Raya Pajarakan', '351316', 'PAJARAKAN', -7.74720, 113.34610],
            ['TKO-DEMO-003', 'Toko Berkah', 'Jl. Raya Gending', '351318', 'GENDING', -7.77840, 113.30910],
            ['TKO-DEMO-004', 'Warung Ibu Sari', 'Jl. Raya Dringu', '351319', 'DRINGU', -7.78510, 113.24920],
            ['TKO-DEMO-005', 'Toko Pancor Indah', 'Jl. Raya Leces', '351305', 'LECES', -7.86550, 113.19070],
            ['TKO-DEMO-006', 'Kios Mandiri', 'Jl. Raya Maron', '351317', 'MARON', -7.83230, 113.38290],
            ['TKO-DEMO-007', 'Toko Bromo', 'Jl. Raya Sukapura', '351301', 'SUKAPURA', -7.92940, 113.01880],
            ['TKO-DEMO-008', 'Warung Sejahtera', 'Jl. Raya Paiton', '351312', 'PAITON', -7.71510, 113.49280],
        ])->map(function (array $item) use ($admin): Store {
            return Store::query()->updateOrCreate(
                ['code' => $item[0]],
                [
                    'name' => $item[1],
                    'address' => $item[2],
                    'province_code' => '35',
                    'city_code' => '3513',
                    'district_code' => $item[3],
                    'district' => $item[4],
                    'regency' => 'KABUPATEN PROBOLINGGO',
                    'province' => 'JAWA TIMUR',
                    'latitude' => $item[5],
                    'longitude' => $item[6],
                    'status' => StoreStatus::Active,
                    'verification_status' => VerificationStatus::Verified,
                    'is_active' => true,
                    'created_by' => $admin->id,
                    'verified_by' => $admin->id,
                    'verified_at' => now(),
                ],
            );
        });

        $this->seedTestingStores($admin);

        $area = RouteArea::query()->whereIn('code', ['AREA-PBL-01', 'AREA-MTR-01'])->first() ?? new RouteArea;
        $area->fill([
            'code' => 'AREA-PBL-01',
            'name' => 'Kabupaten Probolinggo',
            'description' => 'Area pengembangan awal Pancor Mas di Kabupaten Probolinggo.',
            'is_active' => true,
            'default_start_latitude' => -7.8669,
            'default_start_longitude' => 113.3090,
        ])->save();
        $area->stores()->sync($stores->pluck('id'));

        $route = DailyRoute::query()->updateOrCreate(
            ['sales_id' => $sales->id, 'route_date' => today()],
            [
                'route_area_id' => $area->id,
                'status' => DailyRouteStatus::Scheduled,
                'start_latitude' => -7.8669,
                'start_longitude' => 113.3090,
                'optimization_status' => OptimizationStatus::Pending,
                'created_by' => $admin->id,
            ],
        );
        $route->stores()->sync(
            $stores->values()->mapWithKeys(fn (Store $store, int $index) => [
                $store->id => ['planned_order' => $index + 1, 'priority' => 100],
            ])->all(),
        );

        app(OptimizeDailyRoute::class)->execute($route->fresh(), $admin);
    }

    private function seedTestingStores(User $admin): void
    {
        $groups = [
            [
                'prefix' => 'KRK',
                'district_code' => '351314',
                'district' => 'KRAKSAAN',
                'center_latitude' => -7.7583,
                'center_longitude' => 113.4168,
            ],
            [
                'prefix' => 'PTN',
                'district_code' => '351312',
                'district' => 'PAITON',
                'center_latitude' => -7.7151,
                'center_longitude' => 113.4928,
            ],
        ];

        foreach ($groups as $group) {
            for ($number = 1; $number <= 100; $number++) {
                $row = intdiv($number - 1, 10);
                $column = ($number - 1) % 10;
                $latitudeOffset = ($row - 4.5) * 0.0032 + sin($number * 1.7) * 0.0007;
                $longitudeOffset = ($column - 4.5) * 0.0035 + cos($number * 1.3) * 0.0007;
                $code = sprintf('DMY-%s-%03d', $group['prefix'], $number);

                Store::query()->updateOrCreate(
                    ['code' => $code],
                    [
                        'name' => sprintf('Toko Dummy %s %03d', ucfirst(strtolower($group['district'])), $number),
                        'owner_name' => sprintf('Pemilik %s %03d', $group['prefix'], $number),
                        'address' => sprintf('Lokasi uji %03d, Kecamatan %s', $number, ucfirst(strtolower($group['district']))),
                        'province_code' => '35',
                        'city_code' => '3513',
                        'district_code' => $group['district_code'],
                        'district' => $group['district'],
                        'regency' => 'KABUPATEN PROBOLINGGO',
                        'province' => 'JAWA TIMUR',
                        'latitude' => round($group['center_latitude'] + $latitudeOffset, 7),
                        'longitude' => round($group['center_longitude'] + $longitudeOffset, 7),
                        'status' => StoreStatus::Active,
                        'verification_status' => VerificationStatus::Verified,
                        'is_active' => true,
                        'created_by' => $admin->id,
                        'verified_by' => $admin->id,
                        'verified_at' => now(),
                        'notes' => 'Data dummy untuk pengujian pembuatan area.',
                    ],
                );
            }
        }
    }
}
