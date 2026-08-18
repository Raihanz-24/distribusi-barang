<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        collect([
            ['code' => 'P', 'name' => 'Kopi Premium'],
            ['code' => 'J', 'name' => 'Kopi Jahe'],
        ])->each(fn (array $product) => Product::query()->updateOrCreate(
            ['code' => $product['code']],
            [
                'name' => $product['name'],
                'slug' => Str::slug($product['name']),
                'unit_name' => 'bungkus',
                'pieces_per_renteng' => 12,
                'is_active' => true,
            ],
        ));
    }
}
