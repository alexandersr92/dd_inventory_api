<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        $date = $this->faker->dateTimeBetween('-1 year', 'now');
        $grandTotal = $this->faker->randomFloat(2, 50, 2000);
        $tax = $grandTotal * 0.15;
        $total = $grandTotal - $tax;

        return [
            'user_id' => \App\Models\User::factory(),
            'organization_id' => \App\Models\Organization::factory(),
            'client_id' => \App\Models\Client::factory(),
            'store_id' => \App\Models\Store::factory(),
            'invoice_number' => 'INV-' . $this->faker->unique()->numberBetween(100000, 999999),
            'invoice_date' => $date->format('Y-m-d'),
            'invoice_note' => $this->faker->sentence(),
            'client_name' => $this->faker->name(),
            'total' => $total,
            'discount' => 0,
            'tax' => $tax,
            'grand_total' => $grandTotal,
            'payment_method' => $this->faker->randomElement(['cash', 'credit_card', 'transfer']),
            'payment_date' => $date->format('Y-m-d'),
            'invoice_status' => $this->faker->randomElement(['completed', 'completed', 'completed', 'credit', 'canceled']), // Bias towards completed
            'invoice_type' => 'cash',
            'created_at' => $date,
            'updated_at' => $date,
        ];
    }
}
