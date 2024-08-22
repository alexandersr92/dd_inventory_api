<?php

namespace Database\Seeders;



// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        \App\Models\User::factory(1)->create([
            'name' => 'Admin',
            'email' => 'user@dipledev.net',
            'is_seller' => false,
        ]);

        \App\Models\User::factory(1)->create([
            'name' => 'Seller',
            'email' => 'seller@dipledev.net',
            'is_seller' => true,
        ]);

        \App\Models\Organization::factory(1)->create([
            'name' => 'Diple Dev',
            'email' => 'info@dipledev.net',
            'owner_id' => 1,
        ]);




        \App\Models\Role::factory(1)->create([
            'name' => 'Admin',
            'description' => 'Admin role',
            'is_active' => true,
            'organization_id' => 1,
        ]);

        \App\Models\Role::factory(1)->create([
            'name' => 'Seller',
            'description' => 'Seller role',
            'is_active' => true,
            'organization_id' => 1,
        ]);

        \App\Models\Module::factory(1)->create([
            'name' => 'Clientes',
            'description' => 'Modulo de Clientes',
            'is_active' => true,
            'organization_id' => 1,
        ]);

        \App\Models\RoleMeta::factory(1)->create([
            'role_id' => 1,
            'module_id' => 1,
        ]);

        \App\Models\Member::factory(1)->create([
            'user_id' => 2,
            'organization_id' => 1,
            'role_id' => 2,
        ]);

        \App\Models\Client::factory(10)->create([
            'organization_id' => 1,
        ]);

        \App\Models\OrganizationModule::factory(1)->create([
            'organization_id' => 1,
            'module_id' => 1,
            'is_active' => true,
        ]);
    }
}
