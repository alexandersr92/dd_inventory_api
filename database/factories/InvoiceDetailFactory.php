<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceDetailFactory extends Factory
{
    public function definition(): array
    {
        $qty = $this->faker->numberBetween(1, 10);
        $price = $this->faker->randomFloat(2, 10, 500);
        $total = $qty * $price;
        $tax = $total * 0.15;
        $grandTotal = $total + $tax;

        return [
            'quantity' => $qty,
            'price' => $price,
            'total' => $total,
            'discount' => 0,
            'tax' => $tax,
            'grand_total' => $grandTotal,
        ];
    }
}
