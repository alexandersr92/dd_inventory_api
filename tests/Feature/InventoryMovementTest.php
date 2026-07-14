<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use App\Models\User;
use App\Models\Organization;
use App\Models\Store;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\InventoryDetail;
use App\Models\InventoryMovement;
use Laravel\Sanctum\Sanctum;

class InventoryMovementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_can_record_manual_inventory_movements(): void
    {
        // 1. Setup entities
        $user = User::factory()->create();
        $org = Organization::factory()->create(['owner_id' => $user->id]);
        $user->update(['organization_id' => $org->id]);

        $store = Store::factory()->create(['organization_id' => $org->id]);
        $inventory = Inventory::factory()->create([
            'organization_id' => $org->id,
            'store_id' => $store->id
        ]);

        $product = Product::factory()->create(['organization_id' => $org->id]);

        $detail = InventoryDetail::factory()->create([
            'inventory_id' => $inventory->id,
            'product_id' => $product->id,
            'quantity' => 10,
        ]);

        // 2. Authenticate and assign permissions using tenant setup
        $this->setupTenantUser($user, $org);
        Sanctum::actingAs($user);

        // 3. Post a damage exit movement (10 - 3 = 7)
        $response = $this->postJson('/api/v1/inventories/movements', [
            'inventory_detail_id' => $detail->id,
            'type' => 'damage',
            'quantity' => 3,
            'reason' => 'Producto quebrado'
        ]);

        $response->assertStatus(201);
        $this->assertEquals(7, $detail->fresh()->quantity);

        $movementId = $response->json('data.id');
        $this->assertDatabaseHas('inventory_movements', [
            'id' => $movementId,
            'type' => 'damage',
            'direction' => 'out',
            'quantity' => 3,
            'stock_before' => 10,
            'stock_after' => 7,
            'reason' => 'Producto quebrado'
        ]);

        // 4. List movements for the inventory
        $listResponse = $this->getJson("/api/v1/inventories/{$inventory->id}/movements");
        $listResponse->assertStatus(200);
        $listResponse->assertJsonFragment([
            'id' => $movementId,
            'product_name' => $product->name,
            'type' => 'damage'
        ]);
    }

    public function test_can_reverse_inventory_movement(): void
    {
        // 1. Setup entities
        $user = User::factory()->create();
        $org = Organization::factory()->create(['owner_id' => $user->id]);
        $user->update(['organization_id' => $org->id]);

        $store = Store::factory()->create(['organization_id' => $org->id]);
        $inventory = Inventory::factory()->create([
            'organization_id' => $org->id,
            'store_id' => $store->id
        ]);

        $product = Product::factory()->create(['organization_id' => $org->id]);

        $detail = InventoryDetail::factory()->create([
            'inventory_id' => $inventory->id,
            'product_id' => $product->id,
            'quantity' => 10,
        ]);

        // 2. Authenticate and assign permissions using tenant setup
        $this->setupTenantUser($user, $org);
        Sanctum::actingAs($user);

        // 3. Post a positive adjustment (10 + 5 = 15)
        $response = $this->postJson('/api/v1/inventories/movements', [
            'inventory_detail_id' => $detail->id,
            'type' => 'adjustment_in',
            'quantity' => 5,
            'reason' => 'Encontrado en bodega'
        ]);

        $response->assertStatus(201);
        $movementId = $response->json('data.id');

        // 4. Reverse the movement (should return stock back to 10 and log a cancel movement)
        $reverseResponse = $this->deleteJson("/api/v1/inventories/movements/{$movementId}");
        $reverseResponse->assertStatus(200);

        $this->assertEquals(10, $detail->fresh()->quantity);

        $this->assertDatabaseHas('inventory_movements', [
            'type' => 'adjustment_in_cancel',
            'direction' => 'out',
            'quantity' => 5,
            'stock_before' => 15,
            'stock_after' => 10,
        ]);
    }

    public function test_can_transfer_stock_between_inventories(): void
    {
        // 1. Setup entities
        $user = User::factory()->create();
        $org = Organization::factory()->create(['owner_id' => $user->id]);
        $user->update(['organization_id' => $org->id]);

        $store = Store::factory()->create(['organization_id' => $org->id]);
        
        $originInventory = Inventory::factory()->create([
            'organization_id' => $org->id,
            'store_id' => $store->id,
            'name' => 'Almacén Principal'
        ]);

        $destInventory = Inventory::factory()->create([
            'organization_id' => $org->id,
            'store_id' => $store->id,
            'name' => 'Almacén Secundario'
        ]);

        $product = Product::factory()->create(['organization_id' => $org->id]);

        // Stock 10 in Origin
        $originDetail = InventoryDetail::factory()->create([
            'inventory_id' => $originInventory->id,
            'product_id' => $product->id,
            'quantity' => 10,
        ]);

        // 2. Authenticate
        $this->setupTenantUser($user, $org);
        Sanctum::actingAs($user);

        // 3. Post a transfer of 4 units (10 - 4 = 6, 0 + 4 = 4)
        $response = $this->postJson('/api/v1/inventories/transfer', [
            'origin_inventory_id' => $originInventory->id,
            'destination_inventory_id' => $destInventory->id,
            'products' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 4
                ]
            ],
            'reason' => 'Reabastecimiento de sucursal'
        ]);

        $response->assertStatus(201);

        // 4. Verify stock
        $this->assertEquals(6, $originDetail->fresh()->quantity);
        
        $destDetail = InventoryDetail::where('inventory_id', $destInventory->id)
            ->where('product_id', $product->id)
            ->first();
        $this->assertNotNull($destDetail);
        $this->assertEquals(4, $destDetail->quantity);

        // 5. Verify movement logs in DB
        $this->assertDatabaseHas('inventory_movements', [
            'inventory_id' => $originInventory->id,
            'type' => 'transfer_out',
            'direction' => 'out',
            'quantity' => 4,
            'stock_before' => 10,
            'stock_after' => 6,
            'reason' => 'Reabastecimiento de sucursal'
        ]);

        $this->assertDatabaseHas('inventory_movements', [
            'inventory_id' => $destInventory->id,
            'type' => 'transfer_in',
            'direction' => 'in',
            'quantity' => 4,
            'stock_before' => 0,
            'stock_after' => 4,
            'reason' => 'Reabastecimiento de sucursal'
        ]);
    }
}
