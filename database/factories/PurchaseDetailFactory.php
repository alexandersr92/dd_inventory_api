<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseDetailFactory extends Factory
{
    public function definition(): array
    {
        return [
            'quantity' => $this->faker->numberBetween(1, 50),
            'price' => $this->faker->randomFloat(2, 5, 200),
        ];
    }
}
