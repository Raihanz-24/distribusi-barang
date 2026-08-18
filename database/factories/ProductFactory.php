<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = 'Kopi '.$this->faker->unique()->word();

        return [
            'code' => Str::upper($this->faker->unique()->bothify('??##')),
            'name' => $name,
            'slug' => Str::slug($name).'-'.$this->faker->unique()->numberBetween(1, 9999),
            'unit_name' => 'bungkus',
            'pieces_per_renteng' => 12,
            'is_active' => true,
        ];
    }
}
