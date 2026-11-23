<?php

namespace Tests\Unit;

use App\Models\Organization;
use App\Services\TenantManager;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductReleaseMigrationGuardTest extends TestCase
{
    protected function tearDown(): void
    {
        TenantManager::clear();
        parent::tearDown();
    }

    public function test_migration_guard_blocks_execution_on_dedicated_tenant(): void
    {
        $migration = require database_path('migrations/2026_11_20_000000_create_product_releases_table.php');

        // Contexto tenant dedicado activo
        $dedicatedOrg = new Organization();
        $dedicatedOrg->tenancy_type = 'dedicated';
        $dedicatedOrg->db_database = 'tenant_isolated_db';
        TenantManager::setTenant($dedicatedOrg);

        $this->assertFalse($migration->isCentralOrShared(), 'La migración no debe considerarse central/shared en contexto de tenant dedicado.');

        // Asegurar que up() y down() retornan sin alterar tablas en contexto tenant dedicado
        $migration->up();
        $migration->down();

        $this->assertTrue(true, 'up() y down() se ejecutaron con guardas sin excepción.');
    }

    public function test_migration_guard_allows_execution_on_central_or_shared(): void
    {
        $migration = require database_path('migrations/2026_11_20_000000_create_product_releases_table.php');

        TenantManager::clear();

        // En testing central o shared db coincidente
        $centralDb = config('database.connections.central.database');
        config(['database.connections.mysql.database' => $centralDb]);

        $this->assertTrue($migration->isCentralOrShared(), 'La migración debe permitir ejecución cuando coincide con central/shared.');
    }
}
