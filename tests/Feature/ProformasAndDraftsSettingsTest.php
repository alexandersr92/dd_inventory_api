<?php

namespace Tests\Feature;

use App\Models\CashSession;
use App\Models\Client;
use App\Models\Inventory;
use App\Models\InventoryDetail;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProformasAndDraftsSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $cashierUser;
    protected Organization $organization;
    protected Store $store;
    protected Inventory $inventory;
    protected Product $product;
    protected InventoryDetail $inventoryDetail;
    protected Client $client;

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

        $this->store = Store::factory()->create([
            'organization_id' => $this->organization->id,
            'invoice_prefix' => 'TS',
            'invoice_number' => 10,
        ]);

        $this->adminUser->stores()->attach($this->store->id);

        $this->inventory = Inventory::factory()->create([
            'organization_id' => $this->organization->id,
            'store_id' => $this->store->id,
            'name' => 'Inventario Test',
        ]);

        $this->product = Product::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Producto Test',
            'price' => 100.00,
            'cost' => 50.00,
        ]);

        $this->inventoryDetail = InventoryDetail::factory()->create([
            'inventory_id' => $this->inventory->id,
            'product_id' => $this->product->id,
            'quantity' => 20,
        ]);

        $this->client = Client::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Cliente Test',
        ]);
    }

    public function test_cashier_can_query_settings_without_admin_permissions(): void
    {
        Setting::create([
            'organization_id' => $this->organization->id,
            'type' => 'global',
            'entity_id' => null,
            'key' => 'allow_proformas',
            'value' => 'true',
            'options' => '[]',
        ]);

        Setting::create([
            'organization_id' => $this->organization->id,
            'type' => 'global',
            'entity_id' => null,
            'key' => 'allow_invoice_drafts',
            'value' => 'false',
            'options' => '[]',
        ]);

        Sanctum::actingAs($this->cashierUser);

        $proformasRes = $this->getJson('/api/v1/settings?key=allow_proformas');
        $proformasRes->assertStatus(200);
        $this->assertEquals('allow_proformas', $proformasRes->json('data.0.key'));
        $this->assertEquals('true', $proformasRes->json('data.0.value'));

        $draftsRes = $this->getJson('/api/v1/settings?key=allow_invoice_drafts');
        $draftsRes->assertStatus(200);
        $this->assertEquals('allow_invoice_drafts', $draftsRes->json('data.0.key'));
        $this->assertEquals('false', $draftsRes->json('data.0.value'));
    }

    public function test_validation_rejects_invalid_values_on_store_and_update(): void
    {
        Sanctum::actingAs($this->adminUser);

        // Store invalid allow_proformas
        $resProformaInvalid = $this->postJson('/api/v1/settings', [
            'type' => 'global',
            'entity_id' => null,
            'key' => 'allow_proformas',
            'value' => 'not_boolean',
            'options' => '[]',
        ]);
        $resProformaInvalid->assertStatus(422)->assertJsonValidationErrors(['value']);

        // Store valid allow_proformas
        $resProformaValid = $this->postJson('/api/v1/settings', [
            'type' => 'global',
            'entity_id' => null,
            'key' => 'allow_proformas',
            'value' => 'true',
            'options' => '[]',
        ]);
        $resProformaValid->assertStatus(201);
        $settingId = $resProformaValid->json('setting.id');

        // Update with invalid value
        $updateInvalid = $this->putJson("/api/v1/settings/{$settingId}", [
            'value' => 'invalid_val',
            'options' => '[]',
        ]);
        $updateInvalid->assertStatus(422)->assertJsonValidationErrors(['value']);

        // Update with valid value
        $updateValid = $this->putJson("/api/v1/settings/{$settingId}", [
            'value' => 'false',
            'options' => '[]',
        ]);
        $updateValid->assertStatus(200);

        // Store invalid allow_invoice_drafts
        $resDraftInvalid = $this->postJson('/api/v1/settings', [
            'type' => 'global',
            'entity_id' => null,
            'key' => 'allow_invoice_drafts',
            'value' => 'yes',
            'options' => '[]',
        ]);
        $resDraftInvalid->assertStatus(422)->assertJsonValidationErrors(['value']);
    }

    public function test_invoice_controller_proforma_blocked_when_disabled_and_allowed_when_enabled(): void
    {
        Sanctum::actingAs($this->adminUser);

        $payload = [
            'store_id' => $this->store->id,
            'client_id' => $this->client->id,
            'client_name' => $this->client->name,
            'invoice_date' => now()->toDateString(),
            'total' => 100,
            'discount' => 0,
            'tax' => 0,
            'grand_total' => 100,
            'payment_method' => 'CASH',
            'payment_date' => now()->toDateString(),
            'is_proforma' => true,
            'products' => [
                [
                    'product_id' => $this->product->id,
                    'inventory_id' => $this->inventory->id,
                    'quantity' => 1,
                    'price' => 100,
                    'total' => 100,
                ]
            ]
        ];

        // 1. By default or disabled -> 422
        $resBlocked = $this->postJson('/api/v1/invoices', $payload);
        $resBlocked->assertStatus(422)
            ->assertJson(['message' => 'La organización no tiene habilitada la creación de proformas.']);

        // 2. Enable allow_proformas
        Setting::create([
            'organization_id' => $this->organization->id,
            'type' => 'global',
            'key' => 'allow_proformas',
            'value' => 'true',
            'options' => '[]',
        ]);

        $resAllowed = $this->postJson('/api/v1/invoices', $payload);
        $resAllowed->assertStatus(201);
        $this->assertEquals('proforma', $resAllowed->json('data.invoice_status'));
    }

    public function test_invoice_controller_draft_blocked_when_disabled_and_allowed_when_enabled(): void
    {
        Sanctum::actingAs($this->adminUser);

        $payload = [
            'store_id' => $this->store->id,
            'client_id' => $this->client->id,
            'client_name' => $this->client->name,
            'invoice_date' => now()->toDateString(),
            'total' => 100,
            'discount' => 0,
            'tax' => 0,
            'grand_total' => 100,
            'payment_method' => 'CASH',
            'payment_date' => now()->toDateString(),
            'is_draft' => true,
            'products' => [
                [
                    'product_id' => $this->product->id,
                    'inventory_id' => $this->inventory->id,
                    'quantity' => 1,
                    'price' => 100,
                    'total' => 100,
                ]
            ]
        ];

        // 1. Disabled by default -> 422
        $resBlocked = $this->postJson('/api/v1/invoices', $payload);
        $resBlocked->assertStatus(422)
            ->assertJson(['message' => 'La organización no tiene habilitada la creación de borradores.']);

        // 2. Enable allow_invoice_drafts
        Setting::create([
            'organization_id' => $this->organization->id,
            'type' => 'global',
            'key' => 'allow_invoice_drafts',
            'value' => 'true',
            'options' => '[]',
        ]);

        $resAllowed = $this->postJson('/api/v1/invoices', $payload);
        $resAllowed->assertStatus(201);
        $this->assertEquals('draft', $resAllowed->json('data.invoice_status'));
    }

    public function test_invoice_draft_controller_blocked_when_disabled_and_allowed_when_enabled(): void
    {
        Sanctum::actingAs($this->adminUser);

        $payload = [
            'store_id' => $this->store->id,
            'client_id' => $this->client->id,
            'client_name' => $this->client->name,
            'invoice_date' => now()->toDateString(),
            'invoice_note' => 'Test draft',
            'payment_method' => 'CASH',
            'products' => [
                [
                    'product_id' => $this->product->id,
                    'inventory_id' => $this->inventory->id,
                    'quantity' => 1,
                    'price' => 100,
                    'discount' => 0,
                    'tax' => 0,
                    'grand_total' => 100,
                ]
            ]
        ];

        // 1. Store blocked when disabled
        $storeBlocked = $this->postJson('/api/v1/invoices/drafts', $payload);
        $storeBlocked->assertStatus(422)
            ->assertJson(['message' => 'La organización no tiene habilitada la gestión de borradores.']);

        // Enable setting
        $setting = Setting::create([
            'organization_id' => $this->organization->id,
            'type' => 'global',
            'key' => 'allow_invoice_drafts',
            'value' => 'true',
            'options' => '[]',
        ]);

        // 2. Store allowed when enabled
        $storeAllowed = $this->postJson('/api/v1/invoices/drafts', $payload);
        $storeAllowed->assertStatus(201);
        $draftId = $storeAllowed->json('data.id');

        // 3. Disable setting again
        $setting->update(['value' => 'false']);

        // 4. Update blocked when disabled
        $updateBlocked = $this->putJson("/api/v1/invoices/drafts/{$draftId}", [
            'invoice_note' => 'Updated note',
        ]);
        $updateBlocked->assertStatus(422)
            ->assertJson(['message' => 'La organización no tiene habilitada la gestión de borradores.']);

        // 5. Convert blocked when disabled
        $convertBlocked = $this->postJson("/api/v1/invoices/drafts/{$draftId}/convert", [
            'payment_method' => 'CASH',
        ]);
        $convertBlocked->assertStatus(422)
            ->assertJson(['message' => 'La organización no tiene habilitada la gestión de borradores.']);

        // 6. Convert allowed when re-enabled
        $setting->update(['value' => 'true']);
        $convertAllowed = $this->postJson("/api/v1/invoices/drafts/{$draftId}/convert", [
            'payment_method' => 'CASH',
        ]);
        $convertAllowed->assertStatus(200);
        $this->assertEquals('completed', $convertAllowed->json('invoice.invoice_status'));
    }
}
