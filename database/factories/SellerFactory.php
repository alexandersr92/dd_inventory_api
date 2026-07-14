<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class SellerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => $this->faker->name(),
            'code' => $this->faker->unique()->bothify('SELL-####'),
            'status' => 'active',
            'pin_hash' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
            'is_owner' => false,
        ];
    }
}
