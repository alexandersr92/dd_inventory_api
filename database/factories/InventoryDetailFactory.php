<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InventoryDetail>
 */
class InventoryDetailFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'inventory_id' => \App\Models\Inventory::factory(),
            'product_id' => \App\Models\Product::factory(),
            'quantity' => $this->faker->randomNumber(),
            'status' => 'active',
            'description' => $this->faker->text,
            'address' => $this->faker->address,
            'price' => $this->faker->randomFloat(2, 0, 1000),
        ];
    }
}
