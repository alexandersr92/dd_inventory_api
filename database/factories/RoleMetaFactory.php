<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RoleMeta>
 */
class RoleMetaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'role_id' => $this->faker->uuid,
            'module_id' => 1,
            'read' => $this->faker->boolean,
            'create' => $this->faker->boolean,
            'update' => $this->faker->boolean,
            'delete' => $this->faker->boolean,
            'is_active' => $this->faker->boolean,
        ];
    }
}
