<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Module>
 */
class ModuleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->word(),
            'icon' => 'https://via.placeholder.com/150',
            'path' => $this->faker->word(),
            'description' => $this->faker->sentence(),
            'status' => 'active',

        ];
    }
}
