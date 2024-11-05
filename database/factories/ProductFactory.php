<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name,
            'barcode' => $this->faker->ean13,
            'organization_id' => \App\Models\Organization::factory(),
            'sku' => $this->faker->ean8,
            'unit_of_measure' => $this->faker->randomElement(['kg', 'g', 'l', 'ml', 'pcs']),
            'image' => $this->faker->imageUrl(),
            'description' => $this->faker->text,
            'price' => $this->faker->randomFloat(2, 1, 100),
            'min_stock' => $this->faker->randomNumber(2),
            'status' => $this->faker->randomElement(['active', 'inactive']),
            'cost' => $this->faker->randomFloat(2, 1, 100),


        ];
    }
}
