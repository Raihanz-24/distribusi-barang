<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class RouteAreaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => 'AREA-'.$this->faker->unique()->numerify('###'),
            'name' => 'Area '.$this->faker->city(),
            'is_active' => true,
        ];
    }
}
