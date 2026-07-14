<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class PurchasesFactory extends Factory
{
    public function definition(): array
    {
        $date = $this->faker->dateTimeBetween('-1 year', 'now');
        $grandTotal = $this->faker->randomFloat(2, 100, 5000);

        return [
            'total' => $grandTotal, // Asumiendo total es el grand total por como se usa
            'purchase_date' => $date->format('Y-m-d'),
            'purchase_note' => $this->faker->sentence(),
            'total_items' => $this->faker->numberBetween(1, 20),
            'status' => $this->faker->randomElement(['completed', 'completed', 'completed', 'cancelled']), // Bias towards completed
            'created_at' => $date,
            'updated_at' => $date,
        ];
    }
}
