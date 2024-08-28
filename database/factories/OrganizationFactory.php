<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Organization>
 */
class OrganizationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'website' => $this->faker->url(),
            'logo' => $this->faker->imageUrl(),
            'description' => $this->faker->sentence(),
            'status' => 'active',
            'owner_id' => \App\Models\User::factory(),
        ];
    }
}
