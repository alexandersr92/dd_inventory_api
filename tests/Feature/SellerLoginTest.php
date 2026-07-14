<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use App\Models\User;
use App\Models\Organization;
use App\Models\Store;
use App\Models\Seller;
use App\Models\Setting;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

class SellerLoginTest extends TestCase
{
    use DatabaseTransactions;

    public function test_seller_can_login_with_code_and_pin_default(): void
    {
        // 1. Setup dependencies
        $user = User::factory()->create();
        $org = Organization::factory()->create(['owner_id' => $user->id]);
        $user->update(['organization_id' => $org->id]);

        $this->setupTenantUser($user, $org);

        $store = Store::factory()->create(['organization_id' => $org->id]);
        
        $seller = Seller::factory()->create([
            'organization_id' => $org->id,
            'code' => 'VEND-01',
            'pin_hash' => Hash::make('1234'),
            'status' => 'active'
        ]);
        
        // Assign seller to store with active status in pivot
        $seller->stores()->attach($store->id, ['status' => 'active', 'organization_id' => $org->id]);

        Sanctum::actingAs($user);

        // Test login with valid code and pin
        $response = $this->postJson('/api/v1/sellers/seller-login', [
            'store_id' => $store->id,
            'code' => 'VEND-01',
            'pin' => '1234'
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'seller' => [
                'id' => $seller->id,
                'name' => $seller->name,
                'code' => 'VEND-01'
            ],
            'store' => [
                'id' => $store->id
            ]
        ]);

        // Test login with wrong PIN
        $responseWrong = $this->postJson('/api/v1/sellers/seller-login', [
            'store_id' => $store->id,
            'code' => 'VEND-01',
            'pin' => '9999'
        ]);

        $responseWrong->assertStatus(422);
    }

    public function test_seller_can_login_with_pin_only_mode(): void
    {
        // 1. Setup dependencies
        $user = User::factory()->create();
        $org = Organization::factory()->create(['owner_id' => $user->id]);
        $user->update(['organization_id' => $org->id]);

        $this->setupTenantUser($user, $org);

        $store = Store::factory()->create(['organization_id' => $org->id]);
        
        $seller = Seller::factory()->create([
            'organization_id' => $org->id,
            'code' => 'VEND-02',
            'pin_hash' => Hash::make('5678'),
            'status' => 'active'
        ]);
        
        $seller->stores()->attach($store->id, ['status' => 'active', 'organization_id' => $org->id]);

        // Enable PIN_ONLY mode
        Setting::create([
            'organization_id' => $org->id,
            'key' => 'seller_login_mode',
            'value' => 'PIN_ONLY',
            'type' => 'global',
            'options' => '[]'
        ]);

        Sanctum::actingAs($user);

        // Test login with PIN only (no code sent)
        $response = $this->postJson('/api/v1/sellers/seller-login', [
            'store_id' => $store->id,
            'pin' => '5678'
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'seller' => [
                'id' => $seller->id,
                'name' => $seller->name,
                'code' => 'VEND-02'
            ],
            'store' => [
                'id' => $store->id
            ]
        ]);

        // Test login with wrong PIN in PIN_ONLY mode
        $responseWrong = $this->postJson('/api/v1/sellers/seller-login', [
            'store_id' => $store->id,
            'pin' => '9999'
        ]);

        $responseWrong->assertStatus(422);
    }

    public function test_cannot_create_seller_with_duplicate_pin_in_same_organization(): void
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create(['owner_id' => $user->id]);
        $user->update(['organization_id' => $org->id]);

        $this->setupTenantUser($user, $org);

        $store = Store::factory()->create(['organization_id' => $org->id]);

        // Create first seller with PIN 1234
        Seller::factory()->create([
            'organization_id' => $org->id,
            'code' => 'V1',
            'pin_hash' => Hash::make('1234'),
            'status' => 'active'
        ]);

        Sanctum::actingAs($user);

        // Attempt to create second seller with same PIN 1234
        $response = $this->postJson('/api/v1/sellers', [
            'name' => 'Second Seller',
            'code' => 'V2',
            'pin' => '1234',
            'stores' => [$store->id]
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['pin']);
    }

    public function test_can_update_seller_without_changing_pin(): void
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create(['owner_id' => $user->id]);
        $user->update(['organization_id' => $org->id]);

        $this->setupTenantUser($user, $org);

        $store = Store::factory()->create(['organization_id' => $org->id]);

        $seller = Seller::factory()->create([
            'organization_id' => $org->id,
            'code' => 'V1',
            'pin_hash' => Hash::make('1234'),
            'status' => 'active'
        ]);
        $seller->stores()->attach($store->id, ['status' => 'active', 'organization_id' => $org->id]);

        Sanctum::actingAs($user);

        // Update name, leaving PIN unchanged (or sending the same PIN)
        $response = $this->putJson("/api/v1/sellers/{$seller->id}", [
            'name' => 'Updated Name',
            'pin' => '1234'
        ]);

        $response->assertStatus(200);
    }

    public function test_cannot_create_seller_with_duplicate_code_in_same_organization(): void
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create(['owner_id' => $user->id]);
        $user->update(['organization_id' => $org->id]);

        $this->setupTenantUser($user, $org);

        $store = Store::factory()->create(['organization_id' => $org->id]);

        // Create first seller with code 'V1'
        Seller::factory()->create([
            'organization_id' => $org->id,
            'code' => 'V1',
            'pin_hash' => Hash::make('1234'),
            'status' => 'active'
        ]);

        Sanctum::actingAs($user);

        // Attempt to create second seller with same code 'V1'
        $response = $this->postJson('/api/v1/sellers', [
            'name' => 'Second Seller',
            'code' => 'V1',
            'pin' => '5678',
            'stores' => [$store->id]
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['code']);
    }

    public function test_can_update_seller_without_changing_code(): void
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create(['owner_id' => $user->id]);
        $user->update(['organization_id' => $org->id]);

        $this->setupTenantUser($user, $org);

        $store = Store::factory()->create(['organization_id' => $org->id]);

        $seller = Seller::factory()->create([
            'organization_id' => $org->id,
            'code' => 'V1',
            'pin_hash' => Hash::make('1234'),
            'status' => 'active'
        ]);
        $seller->stores()->attach($store->id, ['status' => 'active', 'organization_id' => $org->id]);

        Sanctum::actingAs($user);

        // Update name, leaving code unchanged (or sending the same code)
        $response = $this->putJson("/api/v1/sellers/{$seller->id}", [
            'name' => 'Updated Name',
            'code' => 'V1'
        ]);

        $response->assertStatus(200);
    }

    public function test_deleting_seller_releases_its_code_for_reuse(): void
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create(['owner_id' => $user->id]);
        $user->update(['organization_id' => $org->id]);

        $this->setupTenantUser($user, $org);

        $store = Store::factory()->create(['organization_id' => $org->id]);

        $seller = Seller::factory()->create([
            'organization_id' => $org->id,
            'code' => 'V1',
            'status' => 'active'
        ]);

        Sanctum::actingAs($user);

        // Delete the seller
        $deleteResponse = $this->deleteJson("/api/v1/sellers/{$seller->id}");
        $deleteResponse->assertStatus(204);

        // Verify the deleted seller's code was renamed
        $seller->refresh();
        $this->assertStringContainsString('_DEL_', $seller->code);

        // We should now be able to register a new active seller with the same code 'V1'
        $createResponse = $this->postJson('/api/v1/sellers', [
            'name' => 'New Seller V1',
            'code' => 'V1',
            'pin' => '5678',
            'stores' => [$store->id]
        ]);

        $createResponse->assertStatus(201);
    }
}
