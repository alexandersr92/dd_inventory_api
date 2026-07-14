<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CreditFactory extends Factory
{
    public function definition(): array
    {
        $total = $this->faker->randomFloat(2, 500, 5000);
        $debt = $this->faker->randomFloat(2, 0, $total);

        return [
            'user_id' => User::factory(),
            'organization_id' => Organization::factory(),
            'store_id' => Store::factory(),
            'client_id' => Client::factory(),
            'invoice_id' => Invoice::factory(),
            'total' => $total,
            'debt' => $debt,
            'credit_status' => $this->faker->randomElement(['active', 'paid', 'overdue']),
        ];
    }
}
