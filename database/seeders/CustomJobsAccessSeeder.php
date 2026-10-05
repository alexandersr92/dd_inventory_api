<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use App\Support\CustomJobs;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class CustomJobsAccessSeeder extends Seeder
{
    public function run(): void
    {
        Module::firstOrCreate(['slug' => CustomJobs::MODULE], [
            'name' => 'Trabajos personalizados',
            'description' => 'Cotizaciones, producción y cobros de trabajos personalizados.',
            'icon' => 'hammer',
            'path' => '/trabajos-personalizados',
            'status' => 'active',
        ]);
        $permissions = [];
        foreach (CustomJobs::PERMISSIONS as $name => $label) {
            $permissions[] = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'], [
                'display_name' => $label,
            ]);
        }
        // Additive: do not reset any role or activate any organization module.
        foreach (Role::where('name', 'Owner')->whereNotNull('organization_id')->get() as $role) {
            $role->givePermissionTo($permissions);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
