<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Setting;
use App\Models\User;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SellerSessionModeSettingTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $cashierUser;
    protected Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create([
            'status' => 'active',
            'is_lifetime' => true,
        ]);

        TenantManager::setTenant($this->organization);

        $this->adminUser = User::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $this->setupTenantUser($this->adminUser, $this->organization);

        // Cashier user without setting.index permission
        $this->cashierUser = User::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_cashier_can_query_seller_session_mode_setting_without_admin_permissions(): void
    {
        Setting::create([
            'organization_id' => $this->organization->id,
            'type' => 'global',
            'entity_id' => null,
            'key' => 'seller_session_mode',
            'value' => 'PERSISTENT',
            'options' => '[]',
        ]);

        Sanctum::actingAs($this->cashierUser);

        $response = $this->getJson('/api/v1/settings?key=seller_session_mode');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $this->assertEquals('seller_session_mode', $data[0]['key']);
        $this->assertEquals('PERSISTENT', $data[0]['value']);
    }

    public function test_admin_can_create_seller_session_mode(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->postJson('/api/v1/settings', [
            'type' => 'global',
            'entity_id' => null,
            'key' => 'seller_session_mode',
            'value' => 'PERSISTENT',
            'options' => '[]',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('settings', [
            'organization_id' => $this->organization->id,
            'key' => 'seller_session_mode',
            'value' => 'PERSISTENT',
        ]);
    }

    public function test_validation_rejects_invalid_seller_session_mode_on_store_and_update(): void
    {
        Sanctum::actingAs($this->adminUser);

        // Store with invalid value
        $storeRes = $this->postJson('/api/v1/settings', [
            'type' => 'global',
            'entity_id' => null,
            'key' => 'seller_session_mode',
            'value' => 'INVALID_MODE',
            'options' => '[]',
        ]);
        $storeRes->assertStatus(422)
            ->assertJsonValidationErrors(['value']);

        // Create valid
        $setting = Setting::create([
            'organization_id' => $this->organization->id,
            'type' => 'global',
            'entity_id' => null,
            'key' => 'seller_session_mode',
            'value' => 'AFTER_EACH_SALE',
            'options' => '[]',
        ]);

        // Update with invalid value
        $updateRes = $this->putJson("/api/v1/settings/{$setting->id}", [
            'value' => 'SOME_INVALID_VAL',
            'options' => '[]',
        ]);
        $updateRes->assertStatus(422)
            ->assertJsonValidationErrors(['value']);

        // Update with valid value
        $validUpdate = $this->putJson("/api/v1/settings/{$setting->id}", [
            'value' => 'PERSISTENT',
            'options' => '[]',
        ]);
        $validUpdate->assertStatus(200);
        $this->assertEquals('PERSISTENT', $setting->fresh()->value);
    }
}
