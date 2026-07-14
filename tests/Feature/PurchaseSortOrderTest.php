<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use App\Models\User;
use App\Models\Organization;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\Inventory;
use App\Models\Purchases;
use Carbon\Carbon;

class PurchaseSortOrderTest extends TestCase
{
    use DatabaseTransactions;

    private $user;
    private $org;
    private $store;
    private $supplier;
    private $inventory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->org = Organization::factory()->create(['owner_id' => $this->user->id]);
        $this->user->update(['organization_id' => $this->org->id]);

        $this->setupTenantUser($this->user, $this->org);

        $this->store = Store::factory()->create(['organization_id' => $this->org->id]);
        $this->supplier = Supplier::factory()->create(['organization_id' => $this->org->id]);
        $this->inventory = Inventory::factory()->create([
            'organization_id' => $this->org->id,
            'store_id' => $this->store->id,
        ]);
    }

    public function test_purchases_are_sorted_by_created_at_descending_by_default(): void
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->user);

        // Create 3 purchases with different created_at dates
        // oldest: 3 hours ago
        $purchase1 = Purchases::factory()->create([
            'user_id' => $this->user->id,
            'organization_id' => $this->org->id,
            'store_id' => $this->store->id,
            'supplier_id' => $this->supplier->id,
            'inventory_id' => $this->inventory->id,
            'total' => 100.00,
            'created_at' => Carbon::now()->subHours(3),
        ]);

        // newest: now
        $purchase2 = Purchases::factory()->create([
            'user_id' => $this->user->id,
            'organization_id' => $this->org->id,
            'store_id' => $this->store->id,
            'supplier_id' => $this->supplier->id,
            'inventory_id' => $this->inventory->id,
            'total' => 200.00,
            'created_at' => Carbon::now(),
        ]);

        // middle: 1 hour ago
        $purchase3 = Purchases::factory()->create([
            'user_id' => $this->user->id,
            'organization_id' => $this->org->id,
            'store_id' => $this->store->id,
            'supplier_id' => $this->supplier->id,
            'inventory_id' => $this->inventory->id,
            'total' => 300.00,
            'created_at' => Carbon::now()->subHours(1),
        ]);

        $response = $this->getJson('/api/v1/purchases');

        $response->assertStatus(201); // Keeping Response::HTTP_CREATED as per index implementation
        
        $data = $response->json();

        $this->assertCount(3, $data);
        // Default order should be desc (most recent first): purchase2 (now), purchase3 (1 hour ago), purchase1 (3 hours ago)
        $this->assertEquals($purchase2->id, $data[0]['id']);
        $this->assertEquals($purchase3->id, $data[1]['id']);
        $this->assertEquals($purchase1->id, $data[2]['id']);
    }

    public function test_purchases_can_be_sorted_differently_if_sorting_parameters_are_passed(): void
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->user);

        // Create 3 purchases with different totals
        $purchase1 = Purchases::factory()->create([
            'user_id' => $this->user->id,
            'organization_id' => $this->org->id,
            'store_id' => $this->store->id,
            'supplier_id' => $this->supplier->id,
            'inventory_id' => $this->inventory->id,
            'total' => 100.00,
            'created_at' => Carbon::now()->subHours(3),
        ]);

        $purchase2 = Purchases::factory()->create([
            'user_id' => $this->user->id,
            'organization_id' => $this->org->id,
            'store_id' => $this->store->id,
            'supplier_id' => $this->supplier->id,
            'inventory_id' => $this->inventory->id,
            'total' => 300.00,
            'created_at' => Carbon::now(),
        ]);

        $purchase3 = Purchases::factory()->create([
            'user_id' => $this->user->id,
            'organization_id' => $this->org->id,
            'store_id' => $this->store->id,
            'supplier_id' => $this->supplier->id,
            'inventory_id' => $this->inventory->id,
            'total' => 200.00,
            'created_at' => Carbon::now()->subHours(1),
        ]);

        // Test 1: sort by total asc
        $responseAsc = $this->getJson('/api/v1/purchases?sort=total&order=asc');
        $responseAsc->assertStatus(201);
        $dataAsc = $responseAsc->json();
        $this->assertCount(3, $dataAsc);
        $this->assertEquals($purchase1->id, $dataAsc[0]['id']); // total: 100.00
        $this->assertEquals($purchase3->id, $dataAsc[1]['id']); // total: 200.00
        $this->assertEquals($purchase2->id, $dataAsc[2]['id']); // total: 300.00

        // Test 2: sort_by total desc
        $responseDesc = $this->getJson('/api/v1/purchases?sort_by=total&direction=desc');
        $responseDesc->assertStatus(201);
        $dataDesc = $responseDesc->json();
        $this->assertCount(3, $dataDesc);
        $this->assertEquals($purchase2->id, $dataDesc[0]['id']); // total: 300.00
        $this->assertEquals($purchase3->id, $dataDesc[1]['id']); // total: 200.00
        $this->assertEquals($purchase1->id, $dataDesc[2]['id']); // total: 100.00
    }
}
