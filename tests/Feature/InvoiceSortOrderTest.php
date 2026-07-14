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
use App\Models\InvoiceDetail;

class InvoiceSortOrderTest extends TestCase
{
    use DatabaseTransactions;

    public function test_invoice_details_are_sorted_by_sort_order(): void
    {
        // 1. Create dependencies
        $user = User::factory()->create();
        $org = Organization::factory()->create(['owner_id' => $user->id]);
        $user->update(['organization_id' => $org->id]);
        
        $store = Store::factory()->create(['organization_id' => $org->id]);
        $inventory = Inventory::factory()->create([
            'organization_id' => $org->id,
            'store_id' => $store->id
        ]);

        $invoice = Invoice::factory()->create([
            'user_id' => $user->id,
            'organization_id' => $org->id,
            'store_id' => $store->id,
            'invoice_number' => 'TEST-ORDER-123',
        ]);

        $product1 = Product::factory()->create(['organization_id' => $org->id]);
        $product2 = Product::factory()->create(['organization_id' => $org->id]);
        $product3 = Product::factory()->create(['organization_id' => $org->id]);

        // Insert in scrambled order with different sort_order values
        InvoiceDetail::create([
            'invoice_id' => $invoice->id,
            'product_id' => $product2->id,
            'inventory_id' => $inventory->id,
            'quantity' => 1,
            'price' => 100,
            'total' => 100,
            'sort_order' => 1, // should be second
        ]);

        InvoiceDetail::create([
            'invoice_id' => $invoice->id,
            'product_id' => $product3->id,
            'inventory_id' => $inventory->id,
            'quantity' => 1,
            'price' => 100,
            'total' => 100,
            'sort_order' => 2, // should be third
        ]);

        InvoiceDetail::create([
            'invoice_id' => $invoice->id,
            'product_id' => $product1->id,
            'inventory_id' => $inventory->id,
            'quantity' => 1,
            'price' => 100,
            'total' => 100,
            'sort_order' => 0, // should be first
        ]);

        // Fetch fresh invoice and load invoiceDetails
        $freshInvoice = Invoice::with('invoiceDetails')->find($invoice->id);

        $details = $freshInvoice->invoiceDetails;

        $this->assertCount(3, $details);
        $this->assertEquals($product1->id, $details[0]->product_id);
        $this->assertEquals($product2->id, $details[1]->product_id);
        $this->assertEquals($product3->id, $details[2]->product_id);
    }

    public function test_invoice_details_endpoint_returns_sorted_details(): void
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

        $product1 = Product::factory()->create(['organization_id' => $org->id]);
        $product2 = Product::factory()->create(['organization_id' => $org->id]);
        $product3 = Product::factory()->create(['organization_id' => $org->id]);

        // Create inventory details to avoid stock error
        InventoryDetail::factory()->create([
            'inventory_id' => $inventory->id,
            'product_id' => $product1->id,
            'quantity' => 10,
        ]);
        InventoryDetail::factory()->create([
            'inventory_id' => $inventory->id,
            'product_id' => $product2->id,
            'quantity' => 10,
        ]);
        InventoryDetail::factory()->create([
            'inventory_id' => $inventory->id,
            'product_id' => $product3->id,
            'quantity' => 10,
        ]);

        // Authenticate via Sanctum
        \Laravel\Sanctum\Sanctum::actingAs($user);

        // Send POST request to create invoice with products in specific order: product2, product3, product1
        $response = $this->postJson('/api/v1/invoices', [
            'store_id' => $store->id,
            'invoice_date' => now()->format('Y-m-d'),
            'client_name' => 'Test Client',
            'discount' => 0,
            'tax' => 0,
            'total' => 3,
            'grand_total' => 300,
            'payment_method' => 'cash',
            'payment_date' => now()->format('Y-m-d'),
            'isCredit' => false,
            'products' => [
                [
                    'product_id' => $product2->id,
                    'inventory_id' => $inventory->id,
                    'quantity' => 1,
                    'price' => 100,
                    'total' => 100,
                ],
                [
                    'product_id' => $product3->id,
                    'inventory_id' => $inventory->id,
                    'quantity' => 1,
                    'price' => 100,
                    'total' => 100,
                ],
                [
                    'product_id' => $product1->id,
                    'inventory_id' => $inventory->id,
                    'quantity' => 1,
                    'price' => 100,
                    'total' => 100,
                ]
            ]
        ]);

        $response->assertStatus(201);

        $invoiceId = $response->json('data.id');

        // Fetch the invoice details and verify order
        $getResponse = $this->getJson("/api/v1/invoices/{$invoiceId}");

        $getResponse->assertStatus(200);
        $details = $getResponse->json('data.invoice_details');

        $this->assertCount(3, $details);
        $this->assertEquals($product2->id, $details[0]['product_id']);
        $this->assertEquals($product3->id, $details[1]['product_id']);
        $this->assertEquals($product1->id, $details[2]['product_id']);
    }
}
