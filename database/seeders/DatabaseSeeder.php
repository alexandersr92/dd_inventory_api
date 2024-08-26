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
        $ownerNike = \App\Models\User::factory(1)->create([
            'name' => 'Admin Nike',
            'email' => 'admin@nike.com',
        ])->first(); // Access the first item in the collection

        $ownerAdidas = \App\Models\User::factory(1)->create([
            'name' => 'Admin Adidas',
            'email' => 'admin@adidas.com',
        ])->first(); // Access the first item in the collection

        $adidas = \App\Models\Organization::factory(1)->create([
            'name' => 'Adidas',
            'email' => 'info@adidas.com',
            'owner_id' => $ownerAdidas->id,
        ])->first(); // Access the first item in the collection

        $nike = \App\Models\Organization::factory(1)->create([
            'name' => 'Nike',
            'email' => 'info@nike.com',
            'owner_id' => $ownerNike->id,
        ])->first(); // Access the first item in the collection





        \App\Models\Role::factory(1)->create([
            'name' => 'Admin',
            'description' => 'Admin role',
            'is_active' => true,
            'organization_id' => $adidas->id,
        ]);

        \App\Models\Role::factory(1)->create([
            'name' => 'Seller',
            'description' => 'Seller role',
            'is_active' => true,
            'organization_id' => $adidas->id,
        ]);

        \App\Models\Role::factory(1)->create([
            'name' => 'Admin',
            'description' => 'Admin role',
            'is_active' => true,
            'organization_id' => $nike->id,
        ]);

        \App\Models\Role::factory(1)->create([
            'name' => 'Seller',
            'description' => 'Seller role',
            'is_active' => true,
            'organization_id' => $nike->id,
        ]);

        \App\Models\Module::factory(1)->create([
            'name' => 'Clientes',
            'description' => 'Modulo de Clientes',
            'is_active' => true,
        ]);
        \App\Models\Module::factory(1)->create([
            'name' => 'Proveedores',
            'description' => 'Modulo de Clientes',
            'is_active' => true,
        ]);
        \App\Models\Module::factory(1)->create([
            'name' => 'Venta',
            'description' => 'Modulo de Clientes',
            'is_active' => true,
        ]);



        \App\Models\Client::factory(125)->create([
            'organization_id' => $adidas->id,
        ]);
        \App\Models\Client::factory(121)->create([
            'organization_id' => $nike->id,
        ]);
    }
}
