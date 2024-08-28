<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RoleMeta>
 */
class RolePermissionFactory extends Factory
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
            'module_id' => \App\Models\Module::factory(),
            'store_id' => \App\Models\Store::factory(),
            'read' => $this->faker->boolean,
            'create' => $this->faker->boolean,
            'update' => $this->faker->boolean,
            'delete' => $this->faker->boolean,

        ];
    }
}
