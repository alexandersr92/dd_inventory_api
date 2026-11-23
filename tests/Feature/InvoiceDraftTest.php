<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Inventory;
use App\Models\InventoryDetail;
use App\Models\InventoryMovement;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InvoiceDraftTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
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

        $this->user = User::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->setupTenantUser($this->user, $this->organization);

        $this->store = Store::factory()->create([
            'organization_id' => $this->organization->id,
            'invoice_prefix' => 'TS',
            'invoice_number' => 10,
        ]);

        $this->user->stores()->attach($this->store->id);

        $this->inventory = Inventory::factory()->create([
            'organization_id' => $this->organization->id,
            'store_id' => $this->store->id,
            'name' => 'Inventario Test',
        ]);

        $this->product = Product::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Producto Prueba',
            'price' => 150.00,
            'cost' => 80.00,
        ]);

        $this->inventoryDetail = InventoryDetail::factory()->create([
            'inventory_id' => $this->inventory->id,
            'product_id' => $this->product->id,
            'quantity' => 50,
        ]);

        $this->client = Client::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Cliente Test',
        ]);

        \App\Models\Setting::create([
            'organization_id' => $this->organization->id,
            'type' => 'global',
            'key' => 'allow_invoice_drafts',
            'value' => 'true',
            'options' => '[]',
        ]);

        Sanctum::actingAs($this->user);
    }

    public function test_can_create_invoice_draft_without_inventory_movement(): void
    {
        $payload = [
            'store_id' => $this->store->id,
            'client_id' => $this->client->id,
            'client_name' => $this->client->name,
            'invoice_date' => now()->toDateString(),
            'invoice_note' => 'Borrador de prueba',
            'payment_method' => 'CASH',
            'products' => [
                [
                    'product_id' => $this->product->id,
                    'inventory_id' => $this->inventory->id,
                    'quantity' => 5,
                    'price' => 150.00,
                    'discount' => 0,
                    'tax' => 0,
                    'grand_total' => 750.00,
                ]
            ],
            'grand_total' => 750.00,
        ];

        $response = $this->postJson('/api/v1/invoices/drafts', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.invoice_status', 'draft')
            ->assertJsonPath('data.is_draft', true)
            ->assertJsonPath('data.grand_total', 750);

        $draftId = $response->json('data.id');

        // Verificar en BD que el borrador fue creado
        $this->assertDatabaseHas('invoices', [
            'id' => $draftId,
            'invoice_status' => 'draft',
            'organization_id' => $this->organization->id,
            'store_id' => $this->store->id,
        ]);

        // Verificar que los detalles fueron creados
        $this->assertDatabaseHas('invoice_details', [
            'invoice_id' => $draftId,
            'product_id' => $this->product->id,
            'quantity' => 5,
        ]);

        // CRÍTICO: El stock NO debe cambiar al guardar un borrador
        $this->inventoryDetail->refresh();
        $this->assertEquals(50, $this->inventoryDetail->quantity);

        // CRÍTICO: NO debe haber movimientos registrados
        $this->assertDatabaseMissing('inventory_movements', [
            'reference_id' => $draftId,
        ]);

        // CRÍTICO: El consecutivo de la tienda NO debe alterarse
        $this->store->refresh();
        $this->assertEquals(10, $this->store->invoice_number);
    }

    public function test_can_list_drafts_and_regular_invoices_excludes_drafts(): void
    {
        // Crear 1 borrador
        $draft = Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'invoice_status' => 'draft',
            'invoice_number' => 'DFT-TEST-001',
        ]);

        // Crear 1 factura completada
        $completedInvoice = Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'invoice_status' => 'completed',
            'invoice_number' => 'TS-000011',
        ]);

        // 1. Listar borradores: debe incluir el draft y NO la completada
        $draftsResponse = $this->getJson('/api/v1/invoices/drafts');
        $draftsResponse->assertStatus(200);
        $draftIds = collect($draftsResponse->json('data'))->pluck('id')->all();
        $this->assertContains($draft->id, $draftIds);
        $this->assertNotContains($completedInvoice->id, $draftIds);

        // 2. Listar facturas estándar: debe excluir los borradores por defecto
        $invoicesResponse = $this->getJson('/api/v1/invoices');
        $invoicesResponse->assertStatus(200);
        $invoiceIds = collect($invoicesResponse->json('data'))->pluck('id')->all();
        $this->assertContains($completedInvoice->id, $invoiceIds);
        $this->assertNotContains($draft->id, $invoiceIds);
    }

    public function test_can_show_and_update_invoice_draft(): void
    {
        $draft = Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'invoice_status' => 'draft',
            'invoice_number' => 'DFT-SHOW-001',
            'client_name' => 'Cliente Original',
            'grand_total' => 100,
        ]);

        $draft->invoiceDetails()->create([
            'product_id' => $this->product->id,
            'inventory_id' => $this->inventory->id,
            'quantity' => 1,
            'price' => 100,
            'cost' => 50,
            'total' => 100,
            'grand_total' => 100,
            'sort_order' => 0,
        ]);

        // 1. Show draft
        $showResponse = $this->getJson("/api/v1/invoices/drafts/{$draft->id}");
        $showResponse->assertStatus(200)
            ->assertJsonPath('data.id', $draft->id)
            ->assertJsonPath('data.invoice_status', 'draft');

        // 2. Update draft
        $updateResponse = $this->putJson("/api/v1/invoices/drafts/{$draft->id}", [
            'client_name' => 'Cliente Editado',
            'invoice_note' => 'Nota actualizada',
            'products' => [
                [
                    'product_id' => $this->product->id,
                    'inventory_id' => $this->inventory->id,
                    'quantity' => 3,
                    'price' => 150.00,
                    'grand_total' => 450.00,
                ]
            ],
            'grand_total' => 450.00,
        ]);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('data.client_name', 'Cliente Editado')
            ->assertJsonPath('data.grand_total', 450);

        // Stock sigue intacto
        $this->inventoryDetail->refresh();
        $this->assertEquals(50, $this->inventoryDetail->quantity);
    }

    public function test_can_delete_invoice_draft(): void
    {
        $draft = Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'invoice_status' => 'draft',
            'invoice_number' => 'DFT-DEL-001',
        ]);

        $draft->invoiceDetails()->create([
            'product_id' => $this->product->id,
            'inventory_id' => $this->inventory->id,
            'quantity' => 2,
            'price' => 150,
            'cost' => 80,
            'total' => 300,
            'grand_total' => 300,
            'sort_order' => 0,
        ]);

        $response = $this->deleteJson("/api/v1/invoices/drafts/{$draft->id}");
        $response->assertStatus(200);

        $this->assertDatabaseMissing('invoices', ['id' => $draft->id]);
        $this->assertDatabaseMissing('invoice_details', ['invoice_id' => $draft->id]);
    }

    public function test_can_convert_draft_to_invoice_with_transaction(): void
    {
        $draft = Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'invoice_status' => 'draft',
            'invoice_number' => 'DFT-CONV-001',
            'client_name' => 'Cliente A Facturar',
            'payment_method' => 'CASH',
            'grand_total' => 300.00,
        ]);

        $draft->invoiceDetails()->create([
            'product_id' => $this->product->id,
            'inventory_id' => $this->inventory->id,
            'quantity' => 2,
            'price' => 150.00,
            'cost' => 80.00,
            'total' => 300.00,
            'grand_total' => 300.00,
            'sort_order' => 0,
        ]);

        $this->store->update(['invoice_number' => 10]);
        $this->inventoryDetail->update(['quantity' => 50]);

        $convertResponse = $this->postJson("/api/v1/invoices/drafts/{$draft->id}/convert", [
            'payment_method' => 'CASH',
        ]);

        $convertResponse->assertStatus(200)
            ->assertJsonPath('invoice.invoice_status', 'completed')
            ->assertJsonPath('invoice.is_draft', false);

        $draft->refresh();
        $this->assertEquals('completed', $draft->invoice_status);
        $this->assertNotNull($draft->converted_at);

        // Se asignó el número consecutivo formal de la tienda (TS-000011)
        $this->assertEquals('TS-000011', $draft->invoice_number);
        $this->store->refresh();
        $this->assertEquals(11, $this->store->invoice_number);

        // Se descontó el inventario (50 - 2 = 48)
        $this->inventoryDetail->refresh();
        $this->assertEquals(48, $this->inventoryDetail->quantity);

        // Se registró el movimiento de inventario
        $this->assertDatabaseHas('inventory_movements', [
            'reference_id' => $draft->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'type' => 'sale',
        ]);
    }

    public function test_cannot_convert_already_converted_invoice(): void
    {
        $completed = Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'invoice_status' => 'completed',
            'invoice_number' => 'TS-000012',
        ]);

        $response = $this->postJson("/api/v1/invoices/drafts/{$completed->id}/convert");
        $response->assertStatus(400);
    }
}
