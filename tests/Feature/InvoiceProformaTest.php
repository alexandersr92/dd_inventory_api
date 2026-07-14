<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use App\Models\User;
use App\Models\Organization;
use App\Models\Store;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Inventory;
use App\Models\InventoryDetail;
use App\Models\Client;
use App\Models\CashSession;

class InvoiceProformaTest extends TestCase
{
    use DatabaseTransactions;

    public function test_can_create_proforma_invoice_without_reducing_stock_or_creating_credits(): void
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create(['owner_id' => $user->id]);
        $user->update(['organization_id' => $org->id]);

        $this->setupTenantUser($user, $org);

        $store = Store::factory()->create(['organization_id' => $org->id]);
        $inventory = Inventory::factory()->create([
            'organization_id' => $org->id,
            'store_id' => $store->id
        ]);
        $client = Client::factory()->create(['organization_id' => $org->id]);

        $product = Product::factory()->create(['organization_id' => $org->id]);
        $detail = InventoryDetail::create([
            'product_id' => $product->id,
            'inventory_id' => $inventory->id,
            'quantity' => 10,
            'organization_id' => $org->id,
        ]);

        $cashSession = CashSession::create([
            'organization_id' => $org->id,
            'store_id' => $store->id,
            'user_id' => $user->id,
            'opening_balance' => 100,
            'status' => 'open',
        ]);

        \Laravel\Sanctum\Sanctum::actingAs($user);

        $payload = [
            'store_id' => $store->id,
            'client_id' => $client->id,
            'client_name' => $client->name,
            'invoice_date' => now()->format('Y-m-d'),
            'invoice_note' => 'Proforma cotización test',
            'total' => 500,
            'discount' => 0,
            'tax' => 0,
            'grand_total' => 500,
            'payment_method' => 'CASH',
            'payment_date' => now()->format('Y-m-d'),
            'is_proforma' => true,
            'isCredit' => true, // even if they pass credit, it should be ignored!
            'cash_session_id' => $cashSession->id,
            'products' => [
                [
                    'product_id' => $product->id,
                    'inventory_id' => $inventory->id,
                    'quantity' => 3,
                    'price' => 150,
                    'total' => 450,
                ]
            ]
        ];

        $response = $this->postJson('/api/v1/invoices', $payload);

        $response->assertStatus(201);
        
        $invoiceId = $response->json('data.id');
        $invoice = Invoice::find($invoiceId);
        $this->assertNotNull($invoice);
        $this->assertEquals('proforma', $invoice->invoice_status);
        $this->assertEquals('proforma', $invoice->invoice_type);
        $this->assertNull($invoice->cash_session_id);

        // Verify stock is still 10 (not reduced by 3)
        $detail->refresh();
        $this->assertEquals(10, $detail->quantity);

        // Verify no credit record is created
        $this->assertDatabaseMissing('credits', [
            'invoice_id' => $invoiceId
        ]);

        // Verify no movements are created
        $this->assertDatabaseMissing('inventory_movements', [
            'reference_id' => $invoiceId,
            'reference_type' => Invoice::class
        ]);
    }
}
