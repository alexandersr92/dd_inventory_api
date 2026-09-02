<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Organization;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $permissionsData = [
            ['name' => 'inventory.movement', 'display_name' => 'Movimientos de Inventario', 'guard_name' => 'web'],
            ['name' => 'movement.index', 'display_name' => 'Listar Movimientos', 'guard_name' => 'web'],
            ['name' => 'movement.store', 'display_name' => 'Crear Movimiento', 'guard_name' => 'web'],
            ['name' => 'movement.delete', 'display_name' => 'Reversar/Anular Movimiento', 'guard_name' => 'web'],
        ];

        $createdPermissions = [];
        foreach ($permissionsData as $p) {
            $createdPermissions[] = Permission::firstOrCreate(['name' => $p['name']], $p);
        }

        // Attach permissions to Owner and Manager roles in all organizations
        $organizations = Organization::all();
        foreach ($organizations as $org) {
            $ownerRole = Role::where('name', 'Owner')
                ->where('organization_id', $org->id)
                ->first();

            if ($ownerRole) {
                foreach ($createdPermissions as $perm) {
                    if (!$ownerRole->hasPermissionTo($perm->name)) {
                        $ownerRole->givePermissionTo($perm);
                    }
                }
            }

            $managerRole = Role::where('name', 'Manager')
                ->where('organization_id', $org->id)
                ->first();

            if ($managerRole) {
                foreach ($createdPermissions as $perm) {
                    if (!$managerRole->hasPermissionTo($perm->name)) {
                        $managerRole->givePermissionTo($perm);
                    }
                }
            }
        }

        // Clear Spatie permission cache
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Keep permissions or remove if needed
    }
};
