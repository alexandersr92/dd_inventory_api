<?php

namespace Database\Factories;

use App\Models\Credit;
use App\Models\Seller;
use Illuminate\Database\Eloquent\Factories\Factory;

class CreditDetailFactory extends Factory
{
    public function definition(): array
    {
        return [
            'credit_id' => Credit::factory(),
            'seller_id' => Seller::factory(),
            'amount' => $this->faker->randomFloat(2, 50, 500),
            'date' => $this->faker->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
            'note' => $this->faker->sentence(),
        ];
    }
}
