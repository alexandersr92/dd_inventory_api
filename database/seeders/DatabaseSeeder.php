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

        $adidasStore = \App\Models\Store::factory()->create([
            'name' => 'Adidas Store',
        ]);

        $nikeStore = \App\Models\Store::factory()->create([
            'name' => 'Nike Store',
        ]);




        //Se crean los roles en cada organizacion

        \App\Models\Role::factory()->create([
            'name' => 'Owner',
            'organization_id' => $adidas->id,
        ]);

        $adidasSellerRole = \App\Models\Role::factory()->create([
            'name' => 'Seller Adidas',
            'organization_id' => $adidas->id,
        ]);

        \App\Models\Role::factory()->create([
            'name' => 'Owner',
            'organization_id' => $nike->id,
        ]);

        \App\Models\Role::factory()->create([
            'name' => 'Seller Nike',
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
        ]);

        \App\Models\RolePermission::factory()->create([
            'role_id' => $adidasSellerRole->id,
            'module_id' => $clientModule->id,
            'store_id' => $adidasStore->id,
            'read' => true,
            'create' => true,
            'update' => false,
            'delete' => false,

        ]);



        \App\Models\Client::factory(125)->create([
            'organization_id' => $adidas->id,
        ]);
        \App\Models\Client::factory(121)->create([
            'organization_id' => $nike->id,
        ]);

        //asignar cliente a tienda
        $clients = \App\Models\Client::all();
        $clients->each(function ($client) use ($adidasStore, $nikeStore) {
            $adidas = \App\Models\Organization::where('name', 'Adidas')->first();
            if ($client->organization_id == $adidas->id)
                \App\Models\StoreClient::factory()->create([
                    'store_id' => $adidasStore->id,
                    'client_id' => $client->id,
                ]);
            else
                \App\Models\StoreClient::factory()->create([
                    'store_id' => $nikeStore->id,
                    'client_id' => $client->id,
                ]);
        });
    }
}
