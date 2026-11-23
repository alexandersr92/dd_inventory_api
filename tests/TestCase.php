<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

use App\Services\TenantManager;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        TenantManager::clear();

        try {
            \Illuminate\Support\Facades\DB::connection('central')->setPdo(
                \Illuminate\Support\Facades\DB::connection('mysql')->getPdo()
            );
        } catch (\Throwable $e) {
            // ignore if not configured
        }
    }

    protected function setupTenantUser(\App\Models\User $user, \App\Models\Organization $org): void
    {
        // Run the seeder to set up modules, permissions, and roles
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        // Assign the Owner role to the user
        $user->assignRole('Owner');
    }
}
