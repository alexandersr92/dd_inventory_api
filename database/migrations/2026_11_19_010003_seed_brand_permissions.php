<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use App\Models\Permission;
use App\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('central')->hasTable('permissions') || !Schema::connection('central')->hasTable('roles')) {
            return;
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissionsData = [
            ['name' => 'brand.index', 'display_name' => 'Listar Marcas', 'guard_name' => 'web'],
            ['name' => 'brand.show', 'display_name' => 'Ver Marca', 'guard_name' => 'web'],
            ['name' => 'brand.store', 'display_name' => 'Crear Marca', 'guard_name' => 'web'],
            ['name' => 'brand.update', 'display_name' => 'Editar Marca', 'guard_name' => 'web'],
            ['name' => 'brand.delete', 'display_name' => 'Eliminar Marca', 'guard_name' => 'web'],
        ];

        foreach ($permissionsData as $perm) {
            Permission::firstOrCreate(
                ['name' => $perm['name'], 'guard_name' => $perm['guard_name']],
                $perm
            );
        }

        $ownerPermissions = [
            'brand.index',
            'brand.show',
            'brand.store',
            'brand.update',
            'brand.delete',
        ];

        $managerPermissions = [
            'brand.index',
            'brand.show',
            'brand.store',
            'brand.update',
        ];

        $ownerRoles = Role::whereIn('name', ['Owner', 'owner', 'super-admin', 'admin'])->get();
        foreach ($ownerRoles as $role) {
            $role->givePermissionTo($ownerPermissions);
        }

        $managerRoles = Role::whereIn('name', ['Manager', 'manager'])->get();
        foreach ($managerRoles as $role) {
            $role->givePermissionTo($managerPermissions);
        }
    }

    public function down(): void
    {
        // No-op para preservar permisos y módulos existentes
    }
};
