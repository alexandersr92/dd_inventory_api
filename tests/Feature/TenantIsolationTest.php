<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Verifica que un tenant NO puede acceder a los recursos de otro por ID directo.
 *
 * Regresión del fallo de IDOR (2026-07-18): el route-model-binding resolvía el
 * modelo antes de que el middleware de tenant activara el scope, así que
 * GET/PUT/DELETE /recurso/{id} cruzaba organizaciones (leía y borraba datos ajenos).
 */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantManager::clear();
    }

    // Emails/teléfonos los genera el factory (únicos). RefreshDatabase no
    // revierte la conexión 'central', así que valores fijos colisionarían
    // entre tests.
    private function makeTenant(string $suffix): array
    {
        $owner = User::factory()->create();
        $org = Organization::factory()->create([
            'name' => "Org {$suffix}",
            'tenancy_type' => 'shared',
            'owner_id' => $owner->id,
        ]);
        $owner->update(['organization_id' => $org->id]);
        $this->setupTenantUser($owner, $org);

        return [$owner, $org];
    }

    /**
     * Crea un recurso perteneciente a $org usando el scope de ese tenant.
     */
    private function withTenant(Organization $org, callable $fn)
    {
        TenantManager::setTenant($org);
        $result = $fn();
        TenantManager::clear();

        return $result;
    }

    public function test_tenant_cannot_read_or_delete_another_tenants_client(): void
    {
        [$ownerA, $orgA] = $this->makeTenant('a');
        [$ownerB, $orgB] = $this->makeTenant('b');

        $clientA = $this->withTenant($orgA, fn () => Client::factory()->create(['name' => 'Cliente A']));

        // El tenant B intenta acceder al cliente del tenant A por ID.
        Sanctum::actingAs($ownerB);

        $this->getJson("/api/v1/clients/{$clientA->id}")->assertNotFound();
        $this->putJson("/api/v1/clients/{$clientA->id}", ['name' => 'Hackeado'])->assertNotFound();
        $this->deleteJson("/api/v1/clients/{$clientA->id}")->assertNotFound();

        // El cliente de A sigue intacto y accesible por su dueño.
        $this->assertDatabaseHas('clients', ['id' => $clientA->id, 'name' => 'Cliente A']);

        Sanctum::actingAs($ownerA);
        $this->getJson("/api/v1/clients/{$clientA->id}")->assertOk();
    }

    public function test_tenant_cannot_read_or_delete_another_tenants_product(): void
    {
        [$ownerA, $orgA] = $this->makeTenant('a');
        [$ownerB, $orgB] = $this->makeTenant('b');

        $productA = $this->withTenant($orgA, fn () => Product::factory()->create(['name' => 'Producto A']));

        Sanctum::actingAs($ownerB);

        $this->getJson("/api/v1/products/{$productA->id}")->assertNotFound();
        $this->deleteJson("/api/v1/products/{$productA->id}")->assertNotFound();

        $this->assertDatabaseHas('products', ['id' => $productA->id]);
    }

    public function test_tenant_cannot_access_another_tenants_store(): void
    {
        [$ownerA, $orgA] = $this->makeTenant('a');
        [$ownerB, $orgB] = $this->makeTenant('b');

        $storeA = $this->withTenant($orgA, fn () => Store::factory()->create(['name' => 'Tienda A']));

        Sanctum::actingAs($ownerB);

        $this->getJson("/api/v1/stores/{$storeA->id}")->assertNotFound();
        $this->deleteJson("/api/v1/stores/{$storeA->id}")->assertNotFound();
    }

    public function test_tenant_listing_only_returns_own_records(): void
    {
        [$ownerA, $orgA] = $this->makeTenant('a');
        [$ownerB, $orgB] = $this->makeTenant('b');

        $this->withTenant($orgA, fn () => Client::factory()->create(['name' => 'Solo de A']));

        Sanctum::actingAs($ownerB);
        $response = $this->getJson('/api/v1/clients');
        $response->assertOk();
        $this->assertCount(0, $response->json('data'));

        Sanctum::actingAs($ownerA);
        $response = $this->getJson('/api/v1/clients');
        $this->assertCount(1, $response->json('data'));
    }
}
