<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Crear las Organizaciones Base Primero
        $ownerNike = \App\Models\User::factory()->create([
            'name' => 'Admin Nike',
            'email' => 'admin@nike.com',
        ]);

        $ownerAdidas = \App\Models\User::factory()->create([
            'name' => 'Admin Adidas',
            'email' => 'admin@adidas.com',
        ]);

        $sellerAdidas = \App\Models\User::factory()->create([
            'name' => 'Seller Adidas',
            'email' => 'seller@adidas.com',
        ]);

        $adidas = \App\Models\Organization::factory()->create([
            'name' => 'Adidas',
            'email' => 'info@adidas.com',
            'owner_id' => $ownerAdidas->id,
        ]);

        $nike = \App\Models\Organization::factory()->create([
            'name' => 'Nike',
            'email' => 'info@nike.com',
            'owner_id' => $ownerNike->id,
        ]);

        // Crear vendedores (Sellers) y relacionarlos con los usuarios
        $ownerNikeModel = \App\Models\Seller::factory()->create([
            'organization_id' => $nike->id,
            'name' => 'Admin Nike',
            'is_owner' => true,
        ]);

        $ownerAdidasModel = \App\Models\Seller::factory()->create([
            'organization_id' => $adidas->id,
            'name' => 'Admin Adidas',
            'is_owner' => true,
        ]);

        $sellerAdidasModel = \App\Models\Seller::factory()->create([
            'organization_id' => $adidas->id,
            'name' => 'Seller Adidas',
            'is_owner' => false,
        ]);

        // Editar usuarios para añadir organization_id y seller_id
        $ownerNike->update([
            'organization_id' => $nike->id,
            'seller_id' => $ownerNikeModel->id,
        ]);

        $ownerAdidas->update([
            'organization_id' => $adidas->id,
            'seller_id' => $ownerAdidasModel->id,
        ]);

        $sellerAdidas->update([
            'organization_id' => $adidas->id,
            'seller_id' => $sellerAdidasModel->id,
        ]);

        // 2. Crear las Tiendas Base
        $adidasStore = \App\Models\Store::factory()->create([
            'name' => 'Adidas Store',
            'organization_id' => $adidas->id,
            'invoice_prefix' => 'AD',
            'invoice_number' => 0,
        ]);

        $nikeStore = \App\Models\Store::factory()->create([
            'name' => 'Nike Store',
            'organization_id' => $nike->id,
            'invoice_prefix' => 'NK',
            'invoice_number' => 0,
        ]);

        // Vincular usuarios con sus respectivas tiendas
        $ownerAdidas->stores()->attach($adidasStore->id);
        $sellerAdidas->stores()->attach($adidasStore->id);
        $ownerNike->stores()->attach($nikeStore->id);

        // 3. Ejecutar catalogos globales, plantillas y generador de roles y permisos
        // RolesAndPermissionsSeeder ahora detectará las organizaciones Adidas y Nike ya creadas
        $this->call([
            RolesAndPermissionsSeeder::class,
            EmailTemplatesSeeder::class,
            NotificationEventsSeeder::class,
            PlansSeeder::class,
            PaymentProvidersSeeder::class,
        ]);

        // 4. Asignar Roles a los Usuarios despues de que RolesAndPermissionsSeeder los haya creado
        $ownerRoleAdidas = \App\Models\Role::where('name', 'Owner')->where('organization_id', $adidas->id)->first();
        $sellerRoleAdidas = \App\Models\Role::where('name', 'Seller')->where('organization_id', $adidas->id)->first();
        $ownerRoleNike = \App\Models\Role::where('name', 'Owner')->where('organization_id', $nike->id)->first();

        if ($ownerRoleAdidas) {
            $ownerAdidas->assignRole($ownerRoleAdidas);
        }
        if ($sellerRoleAdidas) {
            $sellerAdidas->assignRole($sellerRoleAdidas);
        }
        if ($ownerRoleNike) {
            $ownerNike->assignRole($ownerRoleNike);
        }

        // 5. Clientes Controlados: Exactamente 1 cliente por Organizacion
        $adidasClient = \App\Models\Client::factory()->create([
            'organization_id' => $adidas->id,
            'name' => 'Cliente Único Adidas',
            'email' => 'client.adidas@example.com',
            'phone' => '+1555123456',
        ]);
        $adidasClient->stores()->attach($adidasStore->id);

        $nikeClient = \App\Models\Client::factory()->create([
            'organization_id' => $nike->id,
            'name' => 'Cliente Único Nike',
            'email' => 'client.nike@example.com',
            'phone' => '+1555987654',
        ]);
        $nikeClient->stores()->attach($nikeStore->id);

        // 6. Proveedores, Categorias y Etiquetas Controladas (Adidas)
        $suppliers = \App\Models\Supplier::factory(2)->create([
            'organization_id' => $adidas->id,
        ]);
        foreach ($suppliers as $supplier) {
            \App\Models\SupplierContact::factory(1)->create([
                'supplier_id' => $supplier->id,
            ]);
        }

        $categories = \App\Models\Category::factory(2)->create([
            'organization_id' => $adidas->id,
        ]);

        $tags = \App\Models\Tag::factory(2)->create([
            'organization_id' => $adidas->id,
        ]);

        // 7. Productos Controlados: Exactamente 20 productos de inventario (Adidas)
        $products = \App\Models\Product::factory(20)->create([
            'organization_id' => $adidas->id,
        ]);

        $products->each(function ($product) use ($categories, $suppliers, $tags) {
            $product->categories()->attach($categories->random()->id);
            $product->suppliers()->attach($suppliers->random()->id);
            $product->tags()->attach($tags->random()->id);
        });

        // Crear Inventario y asociar productos
        $inventory = \App\Models\Inventory::factory()->create([
            'organization_id' => $adidas->id,
            'store_id' => $adidasStore->id,
            'name' => 'Inventario Adidas Principal',
        ]);

        foreach ($products as $product) {
            \App\Models\InventoryDetail::factory()->create([
                'inventory_id' => $inventory->id,
                'product_id' => $product->id,
                'quantity' => 100, // Stock fijo inicial
            ]);
        }

        // 8. Ventas (Facturas) y Creditos Controlados: 10 ventas, 10 creditos variados (Adidas)
        for ($i = 1; $i <= 10; $i++) {
            // A. Crear Factura tipo crédito
            $invoice = \App\Models\Invoice::factory()->create([
                'user_id' => $ownerAdidas->id,
                'organization_id' => $adidas->id,
                'client_id' => $adidasClient->id,
                'client_name' => $adidasClient->name,
                'store_id' => $adidasStore->id,
                'seller_id' => $sellerAdidasModel->id,
                'invoice_status' => 'credit',
                'invoice_type' => 'credit',
                'invoice_number' => 'AD-' . str_pad($i, 6, '0', STR_PAD_LEFT),
                'total' => 0,
                'grand_total' => 0,
                'discount' => 0,
                'tax' => 0,
            ]);

            // B. Detalles de Factura (1 a 3 productos)
            $numItems = rand(1, 3);
            $selectedProducts = $products->random($numItems);
            $invoiceTotal = 0;

            foreach ($selectedProducts as $index => $product) {
                $qty = rand(1, 5);
                $price = $product->price;
                $totalPrice = $price * $qty;
                $invoiceTotal += $totalPrice;

                \App\Models\InvoiceDetail::create([
                    'invoice_id' => $invoice->id,
                    'product_id' => $product->id,
                    'inventory_id' => $inventory->id,
                    'quantity' => $qty,
                    'price' => $price,
                    'total' => $totalPrice,
                    'discount' => 0,
                    'tax' => 0,
                    'grand_total' => $totalPrice,
                    'sort_order' => $index,
                ]);
            }

            $tax = $invoiceTotal * 0.15;
            $grandTotal = $invoiceTotal + $tax;

            $invoice->update([
                'total' => $numItems,
                'tax' => $tax,
                'grand_total' => $grandTotal,
            ]);

            // C. Crear registro de Credito asociado
            $credit = \App\Models\Credit::create([
                'user_id' => $ownerAdidas->id,
                'organization_id' => $adidas->id,
                'store_id' => $adidasStore->id,
                'client_id' => $adidasClient->id,
                'invoice_id' => $invoice->id,
                'total' => $grandTotal,
                'debt' => $grandTotal,
                'credit_status' => 'active',
            ]);

            // D. Abonos variados (CreditDetail)
            if ($i <= 2) {
                // Pagado al 100% (2 abonos)
                $pay1 = round($grandTotal / 2, 2);
                $pay2 = round($grandTotal - $pay1, 2);

                \App\Models\CreditDetail::create([
                    'credit_id' => $credit->id,
                    'seller_id' => $sellerAdidasModel->id,
                    'amount' => $pay1,
                    'date' => now()->subDays(10)->format('Y-m-d'),
                    'note' => 'Abono 1 de 2',
                ]);

                \App\Models\CreditDetail::create([
                    'credit_id' => $credit->id,
                    'seller_id' => $sellerAdidasModel->id,
                    'amount' => $pay2,
                    'date' => now()->subDays(5)->format('Y-m-d'),
                    'note' => 'Abono final cancelado',
                ]);

                $credit->update([
                    'debt' => 0,
                    'credit_status' => 'paid',
                ]);
            } elseif ($i <= 6) {
                // Parcialmente pagado (1 abono del 40%)
                $pay = round($grandTotal * 0.4, 2);

                \App\Models\CreditDetail::create([
                    'credit_id' => $credit->id,
                    'seller_id' => $sellerAdidasModel->id,
                    'amount' => $pay,
                    'date' => now()->subDays(2)->format('Y-m-d'),
                    'note' => 'Abono parcial de control',
                ]);

                $credit->update([
                    'debt' => round($grandTotal - $pay, 2),
                    'credit_status' => 'active',
                ]);
            } else {
                // Activo sin abonos (no se crea CreditDetail)
                // Se mantiene saldo original debt = grandTotal
            }
        }

        // 9. Compras y Gastos (Adidas): Seed 2 compras de ejemplo
        for ($i = 0; $i < 2; $i++) {
            $supplier = $suppliers->random();
            $purchase = \App\Models\Purchases::factory()->create([
                'user_id' => $ownerAdidas->id,
                'organization_id' => $adidas->id,
                'store_id' => $adidasStore->id,
                'supplier_id' => $supplier->id,
                'inventory_id' => $inventory->id,
            ]);

            \App\Models\PurchaseDetail::factory()->create([
                'purchase_id' => $purchase->id,
                'product_id' => $products->random()->id,
            ]);
        }

        // 10. Reportes Controlados: Seed 2 reportes de ejemplo (Adidas)
        \App\Models\Report::create([
            'organization_id' => $adidas->id,
            'store_id' => $adidasStore->id,
            'user_id' => $ownerAdidas->id,
            'name' => 'Cierre de Ventas Mensual',
            'type' => 'invoices',
            'file_path' => 'reports/cierre_ventas_mensual.xlsx',
            'status' => 'completed',
            'filters' => ['date_from' => now()->subDays(30)->format('Y-m-d'), 'date_to' => now()->format('Y-m-d')],
        ]);

        \App\Models\Report::create([
            'organization_id' => $adidas->id,
            'store_id' => $adidasStore->id,
            'user_id' => $ownerAdidas->id,
            'name' => 'Estado de Cuentas por Cobrar',
            'type' => 'credits',
            'file_path' => 'reports/estado_cuentas_cobrar.xlsx',
            'status' => 'completed',
            'filters' => ['status' => 'active'],
        ]);
    }
}
