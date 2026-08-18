<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductPriceFactory extends Factory
{
    public function definition(): array
    {
        $rentengPrice = $this->faker->numberBetween(1, 5) * 10000;

        return [
            'product_id' => Product::factory(),
            'price_per_renteng' => $rentengPrice,
            'effective_from' => today(),
            'effective_until' => null,
            'is_active' => true,
            'created_by' => User::factory()->admin(),
        ];
    }
}
