<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Organization;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Seed Modules
        $modulesData = [
            [
                'name' => 'Productos',
                'slug' => 'products',
                'description' => 'Administración de productos, precios y catálogos.',
                'icon' => 'box',
                'path' => '/products',
                'status' => 'active',
            ],
            [
                'name' => 'Ventas y POS',
                'slug' => 'invoices',
                'description' => 'Módulo de facturación y punto de venta.',
                'icon' => 'shopping-cart',
                'path' => '/invoices',
                'status' => 'active',
            ],
            [
                'name' => 'Compras',
                'slug' => 'purchases',
                'description' => 'Registro de compras a proveedores y control de gastos.',
                'icon' => 'truck',
                'path' => '/purchases',
                'status' => 'active',
            ],
            [
                'name' => 'Inventario',
                'slug' => 'inventories',
                'description' => 'Control de stock físico, almacenes y ajustes de inventario.',
                'icon' => 'archive',
                'path' => '/inventories',
                'status' => 'active',
            ],
            [
                'name' => 'Créditos',
                'slug' => 'credits',
                'description' => 'Control de cuentas por cobrar y abonos de clientes.',
                'icon' => 'credit-card',
                'path' => '/credits',
                'status' => 'active',
            ],
            [
                'name' => 'Reportes',
                'slug' => 'reports',
                'description' => 'Reportes financieros, exportaciones a Excel e informes de stock.',
                'icon' => 'bar-chart-2',
                'path' => '/reports',
                'status' => 'active',
            ],
            [
                'name' => 'Vendedores',
                'slug' => 'sellers',
                'description' => 'Control de la fuerza de ventas y cajeros autorizados.',
                'icon' => 'users',
                'path' => '/sellers',
                'status' => 'active',
            ],
            [
                'name' => 'Configuración',
                'slug' => 'settings',
                'description' => 'Configuración global de la organización y sucursales.',
                'icon' => 'settings',
                'path' => '/settings',
                'status' => 'active',
            ]
        ];

        $modules = [];
        foreach ($modulesData as $data) {
            $modules[] = Module::firstOrCreate(
                ['slug' => $data['slug']],
                $data
            );
        }

        // 2. Seed Permissions
        $permissionsData = [
            // Clientes
            ['name' => 'client.index', 'display_name' => 'Listar Clientes', 'guard_name' => 'web'],
            ['name' => 'client.show', 'display_name' => 'Ver Cliente', 'guard_name' => 'web'],
            ['name' => 'client.store', 'display_name' => 'Crear Cliente', 'guard_name' => 'web'],
            ['name' => 'client.update', 'display_name' => 'Editar Cliente', 'guard_name' => 'web'],
            ['name' => 'client.delete', 'display_name' => 'Eliminar Cliente', 'guard_name' => 'web'],

            // Proveedores
            ['name' => 'supplier.index', 'display_name' => 'Listar Proveedores', 'guard_name' => 'web'],
            ['name' => 'supplier.show', 'display_name' => 'Ver Proveedor', 'guard_name' => 'web'],
            ['name' => 'supplier.store', 'display_name' => 'Crear Proveedor', 'guard_name' => 'web'],
            ['name' => 'supplier.update', 'display_name' => 'Editar Proveedor', 'guard_name' => 'web'],
            ['name' => 'supplier.delete', 'display_name' => 'Eliminar Proveedor', 'guard_name' => 'web'],

            // Sucursales
            ['name' => 'store.index', 'display_name' => 'Listar Sucursales', 'guard_name' => 'web'],
            ['name' => 'store.show', 'display_name' => 'Ver Sucursal', 'guard_name' => 'web'],
            ['name' => 'store.store', 'display_name' => 'Crear Sucursal', 'guard_name' => 'web'],
            ['name' => 'store.update', 'display_name' => 'Editar Sucursal', 'guard_name' => 'web'],
            ['name' => 'store.delete', 'display_name' => 'Eliminar Sucursal', 'guard_name' => 'web'],

            // Categorías y Etiquetas
            ['name' => 'category.index', 'display_name' => 'Listar Categorías', 'guard_name' => 'web'],
            ['name' => 'category.store', 'display_name' => 'Crear Categoría', 'guard_name' => 'web'],
            ['name' => 'category.update', 'display_name' => 'Editar Categoría', 'guard_name' => 'web'],
            ['name' => 'category.delete', 'display_name' => 'Eliminar Categoría', 'guard_name' => 'web'],
            ['name' => 'tag.index', 'display_name' => 'Listar Etiquetas', 'guard_name' => 'web'],
            ['name' => 'tag.store', 'display_name' => 'Crear Etiqueta', 'guard_name' => 'web'],
            ['name' => 'tag.update', 'display_name' => 'Editar Etiqueta', 'guard_name' => 'web'],
            ['name' => 'tag.delete', 'display_name' => 'Eliminar Etiqueta', 'guard_name' => 'web'],

            // Productos
            ['name' => 'product.index', 'display_name' => 'Listar Productos', 'guard_name' => 'web'],
            ['name' => 'product.show', 'display_name' => 'Ver Producto', 'guard_name' => 'web'],
            ['name' => 'product.store', 'display_name' => 'Crear Producto', 'guard_name' => 'web'],
            ['name' => 'product.update', 'display_name' => 'Editar Producto', 'guard_name' => 'web'],
            ['name' => 'product.delete', 'display_name' => 'Eliminar Producto', 'guard_name' => 'web'],

            // Inventarios
            ['name' => 'inventory.index', 'display_name' => 'Listar Inventarios', 'guard_name' => 'web'],
            ['name' => 'inventory.show', 'display_name' => 'Ver Inventario', 'guard_name' => 'web'],
            ['name' => 'inventory.store', 'display_name' => 'Crear Inventario', 'guard_name' => 'web'],
            ['name' => 'inventory.update', 'display_name' => 'Editar Inventario', 'guard_name' => 'web'],
            ['name' => 'inventory.delete', 'display_name' => 'Eliminar Inventario', 'guard_name' => 'web'],

            // Facturas
            ['name' => 'invoice.index', 'display_name' => 'Listar Facturas', 'guard_name' => 'web'],
            ['name' => 'invoice.show', 'display_name' => 'Ver Factura', 'guard_name' => 'web'],
            ['name' => 'invoice.store', 'display_name' => 'Crear Factura', 'guard_name' => 'web'],
            ['name' => 'invoice.delete', 'display_name' => 'Anular Factura', 'guard_name' => 'web'],

            // Compras
            ['name' => 'purchase.index', 'display_name' => 'Listar Compras', 'guard_name' => 'web'],
            ['name' => 'purchase.show', 'display_name' => 'Ver Compra', 'guard_name' => 'web'],
            ['name' => 'purchase.store', 'display_name' => 'Crear Compra', 'guard_name' => 'web'],
            ['name' => 'purchase.update', 'display_name' => 'Editar Compra', 'guard_name' => 'web'],
            ['name' => 'purchase.delete', 'display_name' => 'Anular Compra', 'guard_name' => 'web'],

            // Créditos
            ['name' => 'credit.index', 'display_name' => 'Listar Créditos', 'guard_name' => 'web'],
            ['name' => 'credit.show', 'display_name' => 'Ver Crédito', 'guard_name' => 'web'],
            ['name' => 'credit.payment', 'display_name' => 'Registrar Pago de Crédito', 'guard_name' => 'web'],

            // Vendedores
            ['name' => 'seller.index', 'display_name' => 'Listar Vendedores', 'guard_name' => 'web'],
            ['name' => 'seller.show', 'display_name' => 'Ver Vendedor', 'guard_name' => 'web'],
            ['name' => 'seller.store', 'display_name' => 'Crear Vendedor', 'guard_name' => 'web'],
            ['name' => 'seller.update', 'display_name' => 'Editar Vendedor', 'guard_name' => 'web'],
            ['name' => 'seller.delete', 'display_name' => 'Eliminar Vendedor', 'guard_name' => 'web'],

            // Reportes
            ['name' => 'report.index', 'display_name' => 'Listar Reportes', 'guard_name' => 'web'],
            ['name' => 'report.store', 'display_name' => 'Generar Reporte', 'guard_name' => 'web'],
            ['name' => 'report.delete', 'display_name' => 'Eliminar Reporte', 'guard_name' => 'web'],
            ['name' => 'report.download', 'display_name' => 'Descargar Reporte', 'guard_name' => 'web'],

            // Configuraciones
            ['name' => 'setting.index', 'display_name' => 'Ver Configuraciones', 'guard_name' => 'web'],
            ['name' => 'setting.store', 'display_name' => 'Guardar Configuración', 'guard_name' => 'web'],
            ['name' => 'setting.update', 'display_name' => 'Actualizar Configuración', 'guard_name' => 'web'],
            ['name' => 'setting.delete', 'display_name' => 'Borrar Configuración', 'guard_name' => 'web'],

            // Usuarios y Roles
            ['name' => 'user.index', 'display_name' => 'Listar Usuarios', 'guard_name' => 'web'],
            ['name' => 'user.show', 'display_name' => 'Ver Usuario', 'guard_name' => 'web'],
            ['name' => 'user.store', 'display_name' => 'Crear Usuario', 'guard_name' => 'web'],
            ['name' => 'user.update', 'display_name' => 'Editar Usuario', 'guard_name' => 'web'],
            ['name' => 'user.delete', 'display_name' => 'Eliminar Usuario', 'guard_name' => 'web'],
            ['name' => 'role.index', 'display_name' => 'Listar Roles', 'guard_name' => 'web'],
            ['name' => 'role.show', 'display_name' => 'Ver Rol', 'guard_name' => 'web'],
            ['name' => 'role.store', 'display_name' => 'Crear Rol', 'guard_name' => 'web'],
            ['name' => 'role.update', 'display_name' => 'Editar Rol', 'guard_name' => 'web'],
            ['name' => 'role.delete', 'display_name' => 'Eliminar Rol', 'guard_name' => 'web'],
        ];

        foreach ($permissionsData as $p) {
            Permission::firstOrCreate(['name' => $p['name']], $p);
        }

        // 3. Setup Default Roles & Modules for existing Organizations
        $organizations = Organization::all();
        $allPermissions = Permission::all();

        $sellerPermissions = [
            'invoice.index', 'invoice.show', 'invoice.store',
            'client.index', 'client.show', 'client.store', 'client.update',
            'product.index', 'product.show',
            'inventory.index', 'inventory.show',
            'credit.index', 'credit.show', 'credit.payment',
            'seller.index', 'seller.show',
        ];

        $managerPermissions = array_merge($sellerPermissions, [
            'product.store', 'product.update',
            'inventory.store', 'inventory.update',
            'supplier.index', 'supplier.show', 'supplier.store', 'supplier.update',
            'purchase.index', 'purchase.show', 'purchase.store',
            'report.index', 'report.store', 'report.download',
        ]);

        foreach ($organizations as $org) {
            // Assign all modules by default to avoid lockouts in production
            foreach ($modules as $mod) {
                $org->modules()->syncWithoutDetaching([$mod->id => ['status' => 'active']]);
            }

            // Create Owner Role (Full Access)
            $ownerRole = Role::firstOrCreate(
                ['name' => 'Owner', 'organization_id' => $org->id],
                ['guard_name' => 'web']
            );
            $ownerRole->syncPermissions($allPermissions);

            // Create Manager Role (Partial Admin)
            $managerRole = Role::firstOrCreate(
                ['name' => 'Manager', 'organization_id' => $org->id],
                ['guard_name' => 'web']
            );
            $managerRole->syncPermissions($managerPermissions);

            // Create Seller Role (Staff Access)
            $sellerRole = Role::firstOrCreate(
                ['name' => 'Seller', 'organization_id' => $org->id],
                ['guard_name' => 'web']
            );
            $sellerRole->syncPermissions($sellerPermissions);
        }
    }
}
