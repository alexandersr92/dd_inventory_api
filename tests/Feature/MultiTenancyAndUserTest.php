<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Role;
use App\Models\Store;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MultiTenancyAndUserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantManager::clear();
    }

    /**
     * Test logical multi-tenancy isolation.
     */
    public function test_shared_multi_tenancy_isolation(): void
    {
        // Create Org A
        $orgA = Organization::factory()->create([
            'name' => 'Organization A',
            'email' => 'a@org.com',
            'phone' => '111111111',
            'tenancy_type' => 'shared',
            'owner_id' => User::factory()->create()->id,
        ]);

        // Create Org B
        $orgB = Organization::factory()->create([
            'name' => 'Organization B',
            'email' => 'b@org.com',
            'phone' => '222222222',
            'tenancy_type' => 'shared',
            'owner_id' => User::factory()->create()->id,
        ]);

        // Set active tenant to Org A
        TenantManager::setTenant($orgA);

        // Create product for Org A
        $productA = Product::factory()->create([
            'name' => 'Product A',
            'price' => 10.00,
        ]);

        $this->assertEquals($orgA->id, $productA->organization_id);

        // Switch tenant to Org B
        TenantManager::setTenant($orgB);

        // Create product for Org B
        $productB = Product::factory()->create([
            'name' => 'Product B',
            'price' => 20.00,
        ]);

        $this->assertEquals($orgB->id, $productB->organization_id);

        // Query products under Org B - should only see Product B
        $products = Product::all();
        $this->assertCount(1, $products);
        $this->assertEquals('Product B', $products->first()->name);

        // Switch back to Org A
        TenantManager::setTenant($orgA);
        $products = Product::all();
        $this->assertCount(1, $products);
        $this->assertEquals('Product A', $products->first()->name);
    }

    /**
     * Test User CRUD endpoints.
     */
    public function test_user_management_endpoints(): void
    {
        $owner = User::factory()->create([
            'name' => 'Org Owner',
            'email' => 'owner@myorg.com',
        ]);

        $org = Organization::factory()->create([
            'name' => 'My Org',
            'email' => 'my@org.com',
            'phone' => '333333333',
            'tenancy_type' => 'shared',
            'owner_id' => $owner->id,
        ]);

        $owner->update(['organization_id' => $org->id]);

        $this->setupTenantUser($owner, $org);

        Sanctum::actingAs($owner);

        // 1. List users (should see exactly 1 user - the owner)
        $response = $this->getJson('/api/v1/users');
        $response->assertStatus(200);
        $response->assertJsonCount(1);

        // 2. Create a new user
        $response = $this->postJson('/api/v1/users', [
            'name' => 'New Employee',
            'email' => 'employee@myorg.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', [
            'email' => 'employee@myorg.com',
            'organization_id' => $org->id,
        ], 'central');

        $employeeId = $response->json('id');

        // 3. Update the user
        $response = $this->putJson("/api/v1/users/{$employeeId}", [
            'name' => 'Employee Updated',
        ]);
        $response->assertStatus(200);
        $this->assertDatabaseHas('users', [
            'id' => $employeeId,
            'name' => 'Employee Updated',
        ], 'central');

        // 4. Delete the user
        $response = $this->deleteJson("/api/v1/users/{$employeeId}");
        $response->assertStatus(204); // HTTP NO CONTENT (204)
        $this->assertDatabaseMissing('users', [
            'id' => $employeeId,
        ], 'central');
    }

    /**
     * Test dynamic connection switching for dedicated tenants.
     */
    public function test_tenant_database_switcher_middleware(): void
    {
        // Se usa la propia BD de testing como "dedicada": desde que el switch de
        // tenant corre antes del route-model-binding, la petición ejecuta queries
        // reales contra la BD dedicada, por lo que debe existir. El nombre real es
        // indiferente para la aserción (verificamos que la config cambió).
        $dedicatedDb = config('database.connections.mysql.database');

        $org = Organization::factory()->create([
            'name' => 'Dedicated Org',
            'email' => 'dedicated@org.com',
            'phone' => '444444444',
            'tenancy_type' => 'dedicated',
            'db_database' => $dedicatedDb,
            'owner_id' => User::factory()->create()->id,
        ]);

        $user = User::factory()->create([
            'organization_id' => $org->id,
            'email' => 'user@dedicated.com',
        ]);

        $this->setupTenantUser($user, $org);

        Sanctum::actingAs($user);

        // Forzar un valor distinto para comprobar que el switcher lo sobreescribe.
        config(['database.connections.mysql.database' => 'placeholder_db']);

        // Send a request to any authenticated route
        $response = $this->getJson('/api/v1/users');

        $response->assertStatus(200);

        // Assert that the connection has switched to the dedicated database name
        $this->assertEquals($dedicatedDb, config('database.connections.mysql.database'));
    }
}
