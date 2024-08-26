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
        //Se crean los usuarios
        $ownerNike = \App\Models\User::factory()->create([
            'name' => 'Admin Nike',
            'email' => 'admin@nike.com',
        ]); // Access the first item in the collection

        $ownerAdidas = \App\Models\User::factory()->create([
            'name' => 'Admin Adidas',
            'email' => 'admin@adidas.com',
        ]); // Access the first item in the collection

        //Se crean las organizaciones

        $adidas = \App\Models\Organization::factory()->create([
            'name' => 'Adidas',
            'email' => 'info@adidas.com',
            'owner_id' => $ownerAdidas->id,
        ]); // Access the first item in the collection

        $nike = \App\Models\Organization::factory()->create([
            'name' => 'Nike',
            'email' => 'info@nike.com',
            'owner_id' => $ownerNike->id,
        ]); // Access the first item in the collection




        //Se crean los roles en cada organizacion

        \App\Models\Role::factory()->create([
            'name' => 'Owner',
            'description' => 'Owner role',
            'is_active' => true,
            'organization_id' => $adidas->id,
        ]);

        $adidasSellerRole = \App\Models\Role::factory()->create([
            'name' => 'Seller Adidas',
            'description' => 'Seller role',
            'is_active' => true,
            'organization_id' => $adidas->id,
        ]);

        \App\Models\Role::factory()->create([
            'name' => 'Owner',
            'description' => 'Owner role',
            'is_active' => true,
            'organization_id' => $nike->id,
        ]);

        \App\Models\Role::factory()->create([
            'name' => 'Seller Nike',
            'description' => 'Seller role',
            'is_active' => true,
            'organization_id' => $nike->id,
        ]);

        //Se crea un usuario Seller para una organizacion


        \App\Models\User::factory()->create([
            'name' => 'Seller Adidas',
            'email' => 'seller@adidas.com',
            'organization_id' => $adidas->id,
            'role_id' => $adidasSellerRole->id,

        ]);

        $clientModule =  \App\Models\Module::factory()->create([
            'name' => 'Clientes',
            'description' => 'Modulo de Clientes',
            'is_active' => true,
        ]);

        \App\Models\RoleMeta::factory()->create([
            'role_id' => $adidasSellerRole->id,
            'module_id' => $clientModule->id,
            'read' => true,
            'create' => true,
            'update' => false,
            'delete' => false,
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
