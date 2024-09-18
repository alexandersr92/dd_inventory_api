<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Inventory>
 */
class InventoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => \App\Models\Organization::factory(),
            'store_id' => \App\Models\Store::factory(),
            'name' => $this->faker->name,
            'description' => $this->faker->text,
            'status' => 'active',
            'address' => $this->faker->address,
        ];
    }
}
