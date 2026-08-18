<?php

namespace Database\Factories;

use App\Enums\StoreStatus;
use App\Enums\VerificationStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class StoreFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => 'TKO-'.$this->faker->unique()->numerify('######'),
            'name' => 'Toko '.$this->faker->company(),
            'owner_name' => $this->faker->name(),
            'phone' => $this->faker->phoneNumber(),
            'address' => $this->faker->address(),
            'province' => 'Nusa Tenggara Barat',
            'latitude' => $this->faker->latitude(-8.8, -8.3),
            'longitude' => $this->faker->longitude(115.8, 116.5),
            'status' => StoreStatus::Active,
            'verification_status' => VerificationStatus::Verified,
            'is_active' => true,
            'created_by' => User::factory()->admin(),
        ];
    }
}
