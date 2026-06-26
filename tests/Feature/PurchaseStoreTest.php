<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use App\Models\User;
use App\Models\Organization;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Purchases;
use App\Models\PurchaseDetail;

class PurchaseStoreTest extends TestCase
{
    use DatabaseTransactions;

    public function test_store_purchase_does_not_match_wrong_product_on_empty_barcode(): void
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create(['owner_id' => $user->id]);
        $user->update(['organization_id' => $org->id]);

        $this->setupTenantUser($user, $org);

        $store = Store::factory()->create(['organization_id' => $org->id]);
        $supplier = Supplier::factory()->create(['organization_id' => $org->id]);
        $inventory = Inventory::factory()->create([
            'organization_id' => $org->id,
            'store_id' => $store->id,
        ]);

        // Create Product A (different SKU, empty barcode)
        $productA = Product::factory()->create([
            'organization_id' => $org->id,
            'sku' => 'A-1',
            'barcode' => '',
            'price' => 100,
            'cost' => 50,
        ]);

        // Create Product B (desired SKU, empty barcode)
        $productB = Product::factory()->create([
            'organization_id' => $org->id,
            'sku' => 'B-2',
            'barcode' => '',
            'price' => 200,
            'cost' => 100,
        ]);

        // Authenticate
        \Laravel\Sanctum\Sanctum::actingAs($user);

        // Store a purchase with product matching Product B's SKU, but with barcode empty
        $response = $this->postJson('/api/v1/purchases', [
            'store_id' => $store->id,
            'supplier_id' => $supplier->id,
            'inventory_id' => $inventory->id,
            'total' => 200,
            'purchase_date' => now()->format('Y-m-d'),
            'purchase_note' => 'Test Purchase note',
            'total_items' => 1,
            'products' => [
                [
                    'product_id' => null,
                    'sku' => 'B-2',
                    'product_name' => $productB->name,
                    'barcode' => '',
                    'price' => 200,
                    'quantity' => 1,
                    'cost' => 100,
                ]
            ]
        ]);

        $response->assertStatus(201);

        $purchaseId = $response->json('id');
        $this->assertNotEmpty($purchaseId);

        // Retrieve purchase details
        $details = PurchaseDetail::where('purchase_id', $purchaseId)->get();
        $this->assertCount(1, $details);

        // It must match Product B, not Product A!
        $this->assertEquals($productB->id, $details[0]->product_id);
    }
}
