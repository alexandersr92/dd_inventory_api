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

        $sellerAdidas = \App\Models\User::factory()->create([
            'name' => 'Seller Adidas',
            'email' => 'seller@adidas.com',
        ]);

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

        //edit user to add organization_id
        $ownerNike->update([
            'organization_id' => $nike->id,
        ]);

        $ownerAdidas->update([
            'organization_id' => $adidas->id,
        ]);

        $sellerAdidas->update([
            'organization_id' => $adidas->id,
        ]);



        $adidasStore = \App\Models\Store::factory()->create([
            'name' => 'Adidas Store',

            'organization_id' => $adidas->id,
        ]);

        $nikeStore = \App\Models\Store::factory()->create([
            'name' => 'Nike Store',
            'organization_id' => $nike->id,
        ]);




        //Se crean los roles en cada organizacion




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
            if ($client->organization_id == $adidas->id) {

                $numRandom = rand(1, 5);
                if ($numRandom == 2) {
                    $client->stores()->attach($adidasStore->id);
                }
            } else {
                $numRandom = rand(1, 5);
                if ($numRandom == 2) {
                    $client->stores()->attach($nikeStore->id);
                }
            }
        });

        $adidasSupplier = \App\Models\Supplier::factory(10)->create([
            'organization_id' => $adidas->id,
        ]);

        foreach ($adidasSupplier as $supplier) {

            $numRandom = rand(2, 8);

            \App\Models\SupplierContact::factory($numRandom)->create([
                'supplier_id' => $supplier->id,
            ]);
        }

        $owner =  \App\Models\Role::create(['name' => 'Owner', 'organization_id' => $adidas->id]);
        $seller = \App\Models\Role::create(['name' => 'Seller', 'organization_id' => $adidas->id]);

        \App\Models\Permission::create(['name' => 'client.index', 'display_name' => "Lista de clientes"])->assignRole($owner);
        \App\Models\Permission::create(['name' => 'client.show',  'display_name' => "Ver un Cliente"])->assignRole($seller);
        \App\Models\Permission::create(['name' => 'client.store',  'display_name' => "Crear un Cliente"])->assignRole($seller);
        \App\Models\Permission::create(['name' => 'client.update',  'display_name' => "Editar un Cliente"])->assignRole($seller);
        \App\Models\Permission::create(['name' => 'client.delete',  'display_name' => "Eliminar un Cliente"])->assignRole($seller);

        $ownerAdidas->assignRole($owner);
        $sellerAdidas->assignRole($seller);

        \App\Models\Category::factory(10)->create([
            'organization_id' => $adidas->id,

        ]);

        \App\Models\Tag::factory(20)->create([
            'organization_id' => $adidas->id,
        ]);

        \App\Models\Product::factory(100)->create([
            'organization_id' => $adidas->id,
        ]);



        //asignar productos a categorias
        $products = \App\Models\Product::all();
        $products->each(function ($product) use ($adidas) {
            $numRandom = rand(1, 5);
            if ($numRandom == 2) {
                $category = \App\Models\Category::where('organization_id', $adidas->id)->inRandomOrder()->first();
                $product->categories()->attach($category->id);

                $product->suppliers()->attach(\App\Models\Supplier::where('organization_id', $adidas->id)->inRandomOrder()->first()->id);
            }
        });


        $products->each(function ($product) use ($adidas) {
            $numRandom = rand(1, 5);
            if ($numRandom == 2) {
                $tag = \App\Models\Tag::where('organization_id', $adidas->id)->inRandomOrder()->first();
                $product->tags()->attach($tag->id);
            }
        });

        \App\Models\Inventory::factory(1)->create([
            'organization_id' => $adidas->id,
            'store_id' => $adidasStore->id,
        ]);

        $inventory = \App\Models\Inventory::first();

        foreach ($products as $product) {
            \App\Models\InventoryDetail::factory(1)->create([
                'inventory_id' => $inventory->id,
                'product_id' => $product->id,
            ]);
        }
    }
}
