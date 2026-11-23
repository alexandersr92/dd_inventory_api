<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Inventory;
use App\Models\InventoryDetail;
use App\Models\InventoryMovement;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use App\Services\Reports\SalesReportStrategy;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FreeInvoiceItemTest extends TestCase
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
            'invoice_prefix' => 'FAC',
            'invoice_number' => 100,
        ]);

        $this->user->stores()->attach($this->store->id);

        $this->inventory = Inventory::factory()->create([
            'organization_id' => $this->organization->id,
            'store_id' => $this->store->id,
            'name' => 'Inventario Principal',
        ]);

        $this->product = Product::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Producto Registrado',
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
            'name' => 'Cliente Demo',
        ]);

        Sanctum::actingAs($this->user);
    }

    protected function enableFreeInvoiceItems(): void
    {
        Setting::updateOrCreate(
            [
                'organization_id' => $this->organization->id,
                'key' => 'allow_free_invoice_items',
                'type' => 'global',
                'entity_id' => null,
            ],
            [
                'value' => 'true',
            ]
        );
    }

    protected function disableFreeInvoiceItems(): void
    {
        Setting::updateOrCreate(
            [
                'organization_id' => $this->organization->id,
                'key' => 'allow_free_invoice_items',
                'type' => 'global',
                'entity_id' => null,
            ],
            [
                'value' => 'false',
            ]
        );
    }

    public function test_cannot_create_invoice_with_free_item_when_setting_is_disabled_by_default(): void
    {
        $payload = [
            'store_id' => $this->store->id,
            'invoice_date' => now()->toDateString(),
            'client_name' => 'Consumidor Final',
            'total' => 1,
            'discount' => 0,
            'tax' => 0,
            'grand_total' => 50,
            'payment_method' => 'CASH',
            'payment_date' => now()->toDateString(),
            'products' => [
                [
                    'item_type' => 'free',
                    'description' => 'Servicio de consultoría',
                    'quantity' => 1,
                    'price' => 50,
                    'cost' => 10,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/invoices', $payload);

        $response->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'La organización no tiene habilitada la facturación de conceptos libres.',
            ]);
    }

    public function test_can_create_invoice_with_free_items_when_setting_is_enabled(): void
    {
        $this->enableFreeInvoiceItems();

        $initialMovements = InventoryMovement::count();

        $payload = [
            'store_id' => $this->store->id,
            'invoice_date' => now()->toDateString(),
            'client_name' => 'Consumidor Final',
            'total' => 2,
            'discount' => 0,
            'tax' => 0,
            'grand_total' => 120,
            'payment_method' => 'CASH',
            'payment_date' => now()->toDateString(),
            'products' => [
                [
                    'item_type' => 'free',
                    'description' => 'Concepto libre 1',
                    'quantity' => 2,
                    'price' => 60,
                    'cost' => 25,
                    'total' => 120,
                    'grand_total' => 120,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/invoices', $payload);

        $response->assertStatus(201);
        $data = $response->json('data');

        $this->assertEquals('FAC-000101', $data['invoice_number']);
        $this->assertCount(1, $data['invoice_details']);
        $detail = $data['invoice_details'][0];
        $this->assertEquals('free', $detail['item_type']);
        $this->assertEquals('Concepto libre 1', $detail['description']);
        $this->assertEquals('Concepto libre 1', $detail['product_name']);
        $this->assertNull($detail['product_id']);
        $this->assertNull($detail['inventory_id']);
        $this->assertEquals(25, $detail['cost']);

        // Zero inventory movements created for free items
        $this->assertEquals($initialMovements, InventoryMovement::count());
    }

    public function test_mixed_invoice_decrements_registered_products_and_ignores_free_items(): void
    {
        $this->enableFreeInvoiceItems();

        $initialStock = $this->inventoryDetail->quantity;

        $payload = [
            'store_id' => $this->store->id,
            'inventory_id' => $this->inventory->id,
            'invoice_date' => now()->toDateString(),
            'client_name' => 'Consumidor Final',
            'total' => 4,
            'discount' => 0,
            'tax' => 0,
            'grand_total' => 350,
            'payment_method' => 'CASH',
            'payment_date' => now()->toDateString(),
            'products' => [
                [
                    'product_id' => $this->product->id,
                    'inventory_id' => $this->inventory->id,
                    'quantity' => 3,
                    'price' => 100,
                    'total' => 300,
                    'grand_total' => 300,
                ],
                [
                    'item_type' => 'free',
                    'description' => 'Servicio de entrega express',
                    'quantity' => 1,
                    'price' => 50,
                    'cost' => 15,
                    'total' => 50,
                    'grand_total' => 50,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/invoices', $payload);

        $response->assertStatus(201);

        // Registered product stock must decrease by 3
        $this->inventoryDetail->refresh();
        $this->assertEquals($initialStock - 3, $this->inventoryDetail->quantity);

        // Movements recorded: exactly 1 for the registered product
        $movements = InventoryMovement::where('reference_id', $response->json('data.id'))->get();
        $this->assertCount(1, $movements);
        $this->assertEquals($this->inventoryDetail->id, $movements->first()->inventory_detail_id);
    }

    public function test_canceling_mixed_invoice_returns_registered_stock_without_touching_free_items(): void
    {
        $this->enableFreeInvoiceItems();

        $payload = [
            'store_id' => $this->store->id,
            'inventory_id' => $this->inventory->id,
            'invoice_date' => now()->toDateString(),
            'client_name' => 'Consumidor Final',
            'total' => 3,
            'discount' => 0,
            'tax' => 0,
            'grand_total' => 250,
            'payment_method' => 'CASH',
            'payment_date' => now()->toDateString(),
            'products' => [
                [
                    'product_id' => $this->product->id,
                    'inventory_id' => $this->inventory->id,
                    'quantity' => 2,
                    'price' => 100,
                    'total' => 200,
                    'grand_total' => 200,
                ],
                [
                    'item_type' => 'free',
                    'description' => 'Servicio adicional',
                    'quantity' => 1,
                    'price' => 50,
                    'cost' => 0,
                    'total' => 50,
                    'grand_total' => 50,
                ],
            ],
        ];

        $createRes = $this->postJson('/api/v1/invoices', $payload);
        $createRes->assertStatus(201);
        $invoiceId = $createRes->json('data.id');

        // Cancel the invoice
        $cancelRes = $this->deleteJson("/api/v1/invoices/{$invoiceId}");
        $cancelRes->assertStatus(200);

        // Stock restored back to initial 20
        $this->inventoryDetail->refresh();
        $this->assertEquals(20, $this->inventoryDetail->quantity);

        // There should be a sale_cancel movement for the product, none for the free item
        $cancelMovements = InventoryMovement::where('reference_id', $invoiceId)
            ->where('type', 'sale_cancel')
            ->get();
        $this->assertCount(1, $cancelMovements);
    }

    public function test_draft_workflow_with_free_items(): void
    {
        $this->enableFreeInvoiceItems();

        // 1. Create draft with free item
        $draftPayload = [
            'store_id' => $this->store->id,
            'client_name' => 'Cliente Borrador',
            'products' => [
                [
                    'item_type' => 'free',
                    'description' => 'Diseño Web Especial',
                    'quantity' => 1,
                    'price' => 300,
                    'cost' => 100,
                    'total' => 300,
                    'grand_total' => 300,
                ],
            ],
        ];

        $draftRes = $this->postJson('/api/v1/invoice-drafts', $draftPayload);
        $draftRes->assertStatus(201);
        $draftId = $draftRes->json('data.id');
        $this->assertEquals('draft', $draftRes->json('data.invoice_status'));

        // 2. Convert draft into completed invoice
        $convertRes = $this->postJson("/api/v1/invoice-drafts/{$draftId}/convert", [
            'payment_method' => 'CASH',
        ]);

        $convertRes->assertStatus(200);
        $invoiceData = $convertRes->json('invoice');
        $this->assertEquals('completed', $invoiceData['invoice_status']);
        $this->assertCount(1, $invoiceData['invoice_details']);
        $this->assertEquals('free', $invoiceData['invoice_details'][0]['item_type']);
        $this->assertEquals('Diseño Web Especial', $invoiceData['invoice_details'][0]['description']);
        $this->assertEquals(100, $invoiceData['invoice_details'][0]['cost']);

        // Zero movements recorded
        $this->assertEquals(0, InventoryMovement::where('reference_id', $draftId)->count());
    }

    public function test_draft_conversion_fails_if_free_items_setting_was_disabled_afterwards(): void
    {
        $this->enableFreeInvoiceItems();

        $draftPayload = [
            'store_id' => $this->store->id,
            'client_name' => 'Cliente Borrador',
            'products' => [
                [
                    'item_type' => 'free',
                    'description' => 'Servicio',
                    'quantity' => 1,
                    'price' => 200,
                    'cost' => 50,
                ],
            ],
        ];

        $draftRes = $this->postJson('/api/v1/invoice-drafts', $draftPayload);
        $draftRes->assertStatus(201);
        $draftId = $draftRes->json('data.id');

        // Disable setting
        $this->disableFreeInvoiceItems();

        $convertRes = $this->postJson("/api/v1/invoice-drafts/{$draftId}/convert", [
            'payment_method' => 'CASH',
        ]);

        $convertRes->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'La organización no tiene habilitada la facturación de conceptos libres.',
            ]);
    }

    public function test_sales_report_includes_free_item_profitability(): void
    {
        $this->enableFreeInvoiceItems();

        // Create completed invoice with free item (price: 200, cost: 50, qty: 2) -> ingreso: 400, costo: 100, margen: 300
        $invoice = Invoice::create([
            'organization_id' => $this->organization->id,
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'client_name' => 'Cliente Reporte',
            'invoice_number' => 'FAC-REP-01',
            'invoice_date' => now()->toDateString(),
            'total' => 2,
            'discount' => 0,
            'tax' => 0,
            'grand_total' => 400,
            'payment_method' => 'CASH',
            'payment_date' => now()->toDateString(),
            'invoice_status' => 'completed',
            'invoice_type' => 'cash',
        ]);

        $invoice->invoiceDetails()->create([
            'item_type' => 'free',
            'description' => 'Mantenimiento General',
            'product_id' => null,
            'inventory_id' => null,
            'quantity' => 2,
            'price' => 200,
            'cost' => 50,
            'total' => 400,
            'grand_total' => 400,
        ]);

        $reportStrategy = new SalesReportStrategy();
        $ref = new \ReflectionMethod($reportStrategy, 'fetchData');
        $ref->setAccessible(true);
        $result = $ref->invoke($reportStrategy, $this->organization->id, [
            'store_id' => $this->store->id,
        ]);

        $this->assertEquals(400, $result['rentabilidad']['ingresos']);
        $this->assertEquals(100, $result['rentabilidad']['costo']);
        $this->assertEquals(300, $result['rentabilidad']['margen_dinero']);
        $this->assertEquals(75, $result['rentabilidad']['margen_porcentaje']);

        // Check top products has the free item aggregated
        $top = $result['top_productos'];
        $this->assertNotEmpty($top);
        $this->assertEquals('Concepto libre: Mantenimiento General', $top[0]['name']);
        $this->assertEquals(2, $top[0]['total_vendido']);
        $this->assertEquals(400, $top[0]['ingresos']);
    }

    public function test_validation_rejects_invalid_free_item_attributes(): void
    {
        $this->enableFreeInvoiceItems();

        // 1. Missing description
        $res = $this->postJson('/api/v1/invoices', [
            'store_id' => $this->store->id,
            'invoice_date' => now()->toDateString(),
            'client_name' => 'Consumidor Final',
            'total' => 1,
            'discount' => 0,
            'tax' => 0,
            'grand_total' => 10,
            'payment_method' => 'CASH',
            'payment_date' => now()->toDateString(),
            'products' => [
                [
                    'item_type' => 'free',
                    'description' => '',
                    'quantity' => 1,
                    'price' => 10,
                ],
            ],
        ]);
        $res->assertStatus(422)->assertJsonFragment(['message' => 'La descripción es obligatoria para conceptos libres.']);

        // 2. Quantity <= 0
        $res = $this->postJson('/api/v1/invoices', [
            'store_id' => $this->store->id,
            'invoice_date' => now()->toDateString(),
            'client_name' => 'Consumidor Final',
            'total' => 0,
            'discount' => 0,
            'tax' => 0,
            'grand_total' => 10,
            'payment_method' => 'CASH',
            'payment_date' => now()->toDateString(),
            'products' => [
                [
                    'item_type' => 'free',
                    'description' => 'Concepto',
                    'quantity' => 0,
                    'price' => 10,
                ],
            ],
        ]);
        $res->assertStatus(422)->assertJsonFragment(['message' => 'La cantidad para conceptos libres debe ser mayor a 0.']);

        // 3. Price < 0
        $res = $this->postJson('/api/v1/invoices', [
            'store_id' => $this->store->id,
            'invoice_date' => now()->toDateString(),
            'client_name' => 'Consumidor Final',
            'total' => 1,
            'discount' => 0,
            'tax' => 0,
            'grand_total' => -10,
            'payment_method' => 'CASH',
            'payment_date' => now()->toDateString(),
            'products' => [
                [
                    'item_type' => 'free',
                    'description' => 'Concepto',
                    'quantity' => 1,
                    'price' => -10,
                ],
            ],
        ]);
        $res->assertStatus(422)->assertJsonFragment(['message' => 'El precio para conceptos libres no puede ser negativo.']);

        // 4. Cost < 0
        $res = $this->postJson('/api/v1/invoices', [
            'store_id' => $this->store->id,
            'invoice_date' => now()->toDateString(),
            'client_name' => 'Consumidor Final',
            'total' => 1,
            'discount' => 0,
            'tax' => 0,
            'grand_total' => 10,
            'payment_method' => 'CASH',
            'payment_date' => now()->toDateString(),
            'products' => [
                [
                    'item_type' => 'free',
                    'description' => 'Concepto',
                    'quantity' => 1,
                    'price' => 10,
                    'cost' => -5,
                ],
            ],
        ]);
        $res->assertStatus(422)->assertJsonFragment(['message' => 'El costo para conceptos libres no puede ser negativo.']);
    }

    public function test_updating_draft_checks_setting(): void
    {
        $this->enableFreeInvoiceItems();

        // Create draft with registered product
        $draftRes = $this->postJson('/api/v1/invoice-drafts', [
            'store_id' => $this->store->id,
            'products' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 1,
                    'price' => 100,
                ],
            ],
        ]);
        $draftRes->assertStatus(201);
        $draftId = $draftRes->json('data.id');

        // Disable setting
        $this->disableFreeInvoiceItems();

        // Try updating draft by adding free item
        $updateRes = $this->putJson("/api/v1/invoice-drafts/{$draftId}", [
            'products' => [
                [
                    'item_type' => 'free',
                    'description' => 'Intento de concepto libre',
                    'quantity' => 1,
                    'price' => 50,
                ],
            ],
        ]);
        $updateRes->assertStatus(422)
            ->assertJsonFragment(['message' => 'La organización no tiene habilitada la facturación de conceptos libres.']);
    }

    public function test_historical_invoices_with_free_items_can_be_viewed_even_when_setting_is_disabled(): void
    {
        $this->enableFreeInvoiceItems();

        $createRes = $this->postJson('/api/v1/invoices', [
            'store_id' => $this->store->id,
            'invoice_date' => now()->toDateString(),
            'client_name' => 'Consumidor Final',
            'total' => 1,
            'discount' => 0,
            'tax' => 0,
            'grand_total' => 80,
            'payment_method' => 'CASH',
            'payment_date' => now()->toDateString(),
            'products' => [
                [
                    'item_type' => 'free',
                    'description' => 'Histórico Servicio',
                    'quantity' => 1,
                    'price' => 80,
                    'cost' => 0,
                ],
            ],
        ]);
        $createRes->assertStatus(201);
        $invoiceId = $createRes->json('data.id');

        // Disable setting
        $this->disableFreeInvoiceItems();

        // Query invoice
        $getRes = $this->getJson("/api/v1/invoices/{$invoiceId}");
        $getRes->assertStatus(200);
        $this->assertEquals('free', $getRes->json('data.invoice_details.0.item_type'));
        $this->assertEquals('Histórico Servicio', $getRes->json('data.invoice_details.0.description'));
    }
}
