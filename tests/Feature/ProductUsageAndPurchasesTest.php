<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\V1\InventoryController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Models\Inventory;
use App\Models\InventoryDetail;
use App\Models\InventoryMovement;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Purchases;
use App\Models\Store;
use App\Models\User;
use App\Services\InventoryMovementService;
use App\Services\MaterialUnits;
use App\Services\PurchaseReceiptService;
use App\Services\TenantManager;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductUsageAndPurchasesTest extends TestCase
{
    private Organization $org;

    private Store $store;

    private Inventory $inventory;

    protected function setUp(): void
    {
        parent::setUp();
        TenantManager::clear();
        // A fresh in-memory central database; never migrate or seed a developer database.
        config(['database.connections.central' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]]);
        DB::purge('central');
        DB::setDefaultConnection('central');
        Schema::create('users', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('name');
            $t->string('email')->unique();
            $t->string('password');
            $t->uuid('organization_id')->nullable();
            $t->uuid('seller_id')->nullable();
            $t->uuid('role_id')->nullable();
            $t->string('status')->default('active');
            $t->boolean('must_change_password')->default(false);
            $t->timestamp('email_verified_at')->nullable();
            $t->rememberToken();
            $t->timestamps();
        });
        Schema::create('organizations', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('name');
            $t->string('email')->nullable();
            $t->uuid('owner_id');
            $t->string('status')->default('active');
            $t->boolean('is_lifetime')->default(true);
            $t->timestamp('license_expires_at')->nullable();
            $t->string('tenancy_type')->default('shared');
            $t->softDeletes();
            $t->timestamps();
        });
        (require database_path('migrations/2024_09_03_062911_create_permission_tables.php'))->up();
        Schema::create('stores', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('organization_id');
            $t->string('name');
            $t->timestamps();
        });
        foreach (['suppliers', 'sellers', 'tags', 'categories'] as $table) {
            Schema::create($table, function (Blueprint $t) {
                $t->uuid('id')->primary();
                $t->uuid('organization_id')->nullable();
                $t->string('name')->nullable();
                $t->timestamps();
            });
        }
        foreach (['2024_09_07_213444_create_products_table.php', '2024_09_18_025336_create_inventories_table.php',
            '2024_09_18_025405_create_inventory_details_table.php', '2026_07_03_000000_create_inventory_store_table.php',
            '2025_01_13_224942_purchases_table.php', '2025_01_13_224951_purchase_details_table.php',
            '2026_07_01_095815_create_inventory_movements_table.php', '2025_05_09_013100_create_settings_table.php'] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }
        Schema::table('purchases', fn (Blueprint $t) => $t->string('invoice_number')->nullable());
        Schema::create('invoice_details', fn (Blueprint $t) => $t->float('quantity'));
        foreach (['category_product' => 'category_id', 'product_tag' => 'tag_id', 'product_supplier' => 'supplier_id'] as $table => $column) {
            Schema::create($table, function (Blueprint $t) use ($column) {
                $t->uuid('product_id');
                $t->uuid($column);
            });
        }
        Schema::create('woocommerce_integrations', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('organization_id');
            $t->uuid('inventory_id');
            $t->boolean('status');
            $t->string('woo_store_url')->nullable();
            $t->string('woo_consumer_key')->nullable();
            $t->string('woo_consumer_secret')->nullable();
        });
        (require database_path('migrations/2026_10_05_000000_product_usage_and_material_units.php'))->up();
        $user = User::create(['name' => 'Owner', 'email' => 'owner@example.com', 'password' => 'password']);
        $this->org = Organization::create(['name' => 'Cuenta', 'owner_id' => $user->id]);
        $user->update(['organization_id' => $this->org->id]);
        $this->actingAs($user);
        TenantManager::setTenant($this->org);
        $this->store = Store::create(['name' => 'Sucursal', 'organization_id' => $this->org->id]);
        $this->inventory = Inventory::create(['name' => 'Inventario', 'store_id' => $this->store->id]);
        Gate::before(fn () => true);
        Route::get('/api/test/products', [ProductController::class, 'index']);
        Route::get('/api/test/inventories/{inventory}/products', [InventoryController::class, 'showProducts'])->middleware(SubstituteBindings::class);
        Route::get('/api/test/stores/{store}', [InventoryController::class, 'getProductByStore'])->middleware(SubstituteBindings::class);
    }

    protected function tearDown(): void
    {
        TenantManager::clear();
        parent::tearDown();
    }

    private function product(array $data = []): Product
    {
        return Product::create(array_merge(['sku' => fake()->unique()->numerify('SKU-####'), 'name' => 'Opalina', 'cost' => 2, 'price' => 5, 'unit_of_measure' => 'sheet'], $data));
    }

    private function receipt(Product $product, array $line = []): array
    {
        return ['store_id' => $this->store->id, 'inventory_id' => $this->inventory->id, 'purchase_date' => '2026-10-05',
            'products' => [array_merge(['product_id' => $product->id, 'sku' => $product->sku, 'product_name' => $product->name,
                'quantity' => 0.5, 'cost' => 2, 'price' => 5], $line)]];
    }

    public function test_global_usage_filters_and_legacy_sales_search(): void
    {
        foreach (['sale', 'supply', 'both'] as $usage) {
            $p = $this->product(['product_usage' => $usage]);
            InventoryDetail::create(['inventory_id' => $this->inventory->id, 'product_id' => $p->id, 'quantity' => 1.5]);
        }
        $this->getJson('/api/test/products')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/test/products?purpose=all')->assertOk()->assertJsonCount(3, 'data');
        $this->getJson('/api/test/products?purpose=material')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/test/products?purpose=all&product_usage=supply')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/test/stores/'.$this->store->id.'?purpose=all')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/test/inventories/'.$this->inventory->id.'/products?purpose=material&search=Opalina')->assertOk()->assertJsonCount(2, 'data');
        $other = Inventory::create(['name' => 'Otro', 'store_id' => $this->store->id]);
        $p = Product::where('product_usage', 'both')->first();
        InventoryDetail::create(['inventory_id' => $other->id, 'product_id' => $p->id, 'quantity' => 7]);
        $p->update(['product_usage' => 'supply']);
        $this->getJson('/api/test/stores/'.$this->store->id)->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame(4, InventoryDetail::count());
        $this->assertSame(7.0, $other->inventoryDetails->first()->quantity);
    }

    public function test_fractional_receipt_edit_cancel_and_historical_usage(): void
    {
        $p = $this->product();
        $service = app(PurchaseReceiptService::class);
        $purchase = $service->save($this->receipt($p, ['product_usage' => 'both']));
        $this->assertSame('both', $p->fresh()->product_usage);
        $this->assertSame(0.5, InventoryDetail::first()->quantity);
        $this->assertEquals(1, $purchase->total);
        $this->assertEquals(0.5, $purchase->total_items);
        $p->update(['product_usage' => 'supply']);
        $purchase = $service->save($this->receipt($p, ['quantity' => 0.25, 'product_usage' => 'both']), $purchase);
        $this->assertSame('supply', $p->fresh()->product_usage);
        $this->assertSame('both', $purchase->purchaseDetails->first()->product_usage);
        $this->assertSame(0.25, InventoryDetail::first()->quantity);
        $service->cancel($purchase);
        $service->cancel($purchase);
        $this->assertSame(0.0, InventoryDetail::first()->quantity);
        $this->assertSame(4, InventoryMovement::count());
    }

    public function test_area_normalizes_stock_and_cost_and_reverses_old_conversion(): void
    {
        $profile = ['base_unit' => 'cm2', 'purchase_unit' => 'sheet', 'consumption_unit' => 'cm2',
            'purchase_to_base_factor' => 600, 'consumption_to_base_factor' => 1, 'width_cm' => 20, 'height_cm' => 30];
        $p = $this->product(['product_usage' => 'supply', 'unit_of_measure' => 'cm2', 'material_profile' => $profile]);
        $purchase = app(PurchaseReceiptService::class)->save($this->receipt($p, ['material_profile' => $profile, 'cost' => 3]));
        $this->assertSame(300.0, InventoryDetail::first()->quantity);
        $this->assertEquals(0.005, $p->fresh()->cost);
        $this->assertEquals(1.5, $purchase->total);
        $profile['width_cm'] = 40;
        $profile['purchase_to_base_factor'] = 1200;
        $p->update(['material_profile' => $profile]);
        $purchase = app(PurchaseReceiptService::class)->save($this->receipt($p, ['quantity' => 0.25, 'cost' => 3]), $purchase);
        $this->assertSame(150.0, InventoryDetail::first()->quantity);
        $this->assertSame(600.0, (float) $purchase->purchaseDetails->first()->material_profile['purchase_to_base_factor']);
        app(PurchaseReceiptService::class)->cancel($purchase);
        $this->assertSame(0.0, InventoryDetail::first()->quantity);
    }

    public function test_duplicate_new_sku_reuses_one_product_and_one_stock_balance(): void
    {
        $line = ['product_id' => null, 'sku' => 'NEW-SHEET', 'product_name' => 'Opalina nueva',
            'quantity' => 0.5, 'cost' => 2, 'price' => 5, 'product_usage' => 'both', 'unit_of_measure' => 'sheet'];
        $purchase = app(PurchaseReceiptService::class)->save(['store_id' => $this->store->id,
            'inventory_id' => $this->inventory->id, 'purchase_date' => '2026-10-05', 'products' => [$line, $line]]);
        $this->assertSame(1, Product::count());
        $this->assertSame(1, InventoryDetail::count());
        $this->assertSame(1.0, InventoryDetail::first()->quantity);
        $this->assertSame(2, $purchase->purchaseDetails->count());
        $this->assertSame('both', Product::first()->product_usage);
    }

    public function test_contradictory_lines_are_atomic(): void
    {
        $p = $this->product();
        $data = $this->receipt($p, ['product_usage' => 'supply']);
        $data['products'][] = array_merge($data['products'][0], ['product_usage' => 'both']);
        try {
            app(PurchaseReceiptService::class)->save($data);
            $this->fail('Expected validation error');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('products', $e->errors());
        }
        $this->assertSame(0, Purchases::count());
        $this->assertSame(0, InventoryDetail::count());
        $this->assertSame('sale', $p->fresh()->product_usage);
    }

    public function test_supply_rejected_without_stock_or_movement_changes(): void
    {
        $p = $this->product(['product_usage' => 'supply']);
        $stock = InventoryDetail::create(['inventory_id' => $this->inventory->id, 'product_id' => $p->id, 'quantity' => 2.5]);
        try {
            app(InventoryMovementService::class)->recordMovement(['inventory_detail_id' => $stock->id, 'type' => 'sale', 'quantity' => 1]);
            $this->fail('Expected rejection');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('products', $e->errors());
        }
        $this->assertSame(2.5, $stock->fresh()->quantity);
        $this->assertSame(0, InventoryMovement::count());
        $p->update(['product_usage' => 'both']);
        app(InventoryMovementService::class)->recordMovement(['inventory_detail_id' => $stock->id, 'type' => 'sale', 'quantity' => 0.5]);
        $this->assertSame(2.0, $stock->fresh()->quantity);
    }

    public function test_transfer_keeps_fractional_stock_and_classification(): void
    {
        $p = $this->product(['product_usage' => 'supply']);
        $stock = InventoryDetail::create(['inventory_id' => $this->inventory->id, 'product_id' => $p->id, 'quantity' => 0.75]);
        $dest = Inventory::create(['name' => 'Destino', 'store_id' => $this->store->id]);
        app(InventoryMovementService::class)->transfer(['origin_inventory_id' => $this->inventory->id,
            'destination_inventory_id' => $dest->id, 'products' => [['product_id' => $p->id, 'quantity' => 0.25]]]);
        $this->assertSame(0.5, $stock->fresh()->quantity);
        $this->assertSame(0.25, $dest->inventoryDetails->first()->quantity);
        $this->assertSame('supply', $p->fresh()->product_usage);
    }

    public function test_incompatible_malformed_and_invalid_area_profiles_are_rejected(): void
    {
        $profiles = [
            ['base_unit' => [], 'purchase_unit' => 'unit', 'consumption_unit' => 'unit', 'purchase_to_base_factor' => 1, 'consumption_to_base_factor' => 1],
            ['base_unit' => 'sheet', 'purchase_unit' => 'kg', 'consumption_unit' => 'sheet', 'purchase_to_base_factor' => 1, 'consumption_to_base_factor' => 1],
            ['base_unit' => 'cm2', 'purchase_unit' => 'sheet', 'consumption_unit' => 'cm2', 'purchase_to_base_factor' => 600, 'consumption_to_base_factor' => 1],
            ['base_unit' => 'cm2', 'purchase_unit' => 'sheet', 'consumption_unit' => 'cm2', 'purchase_to_base_factor' => 599, 'consumption_to_base_factor' => 1, 'width_cm' => 20, 'height_cm' => 30],
        ];
        foreach ($profiles as $profile) {
            try {
                MaterialUnits::validate($profile, is_string($profile['base_unit']) ? $profile['base_unit'] : 'unit');
                $this->fail('Expected validation error');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('material_profile', $e->errors());
            }
        }
    }

    public function test_reclassification_syncs_zero_to_external_channel_only_after_commit(): void
    {
        $p = $this->product();
        InventoryDetail::create(['inventory_id' => $this->inventory->id, 'product_id' => $p->id, 'quantity' => 8]);
        DB::table('woocommerce_integrations')->insert(['id' => (string) Str::uuid(),
            'organization_id' => $this->org->id, 'inventory_id' => $this->inventory->id, 'status' => true,
            'woo_store_url' => 'https://woo.example', 'woo_consumer_key' => 'test', 'woo_consumer_secret' => 'test']);
        Http::fake([
            'https://woo.example/wp-json/wc/v3/products?*' => Http::response([['id' => 1]]),
            'https://woo.example/wp-json/wc/v3/products/1' => Http::response(['id' => 1]),
        ]);
        DB::beginTransaction();
        $p->update(['product_usage' => 'supply']);
        DB::rollBack();
        Http::assertNothingSent();
        $p->refresh()->update(['product_usage' => 'supply']);
        Http::assertSent(fn ($request) => $request->method() === 'PUT' && $request['stock_quantity'] === 0);
    }

    public function test_migration_backfills_existing_product_and_receipt_without_losing_fractions(): void
    {
        $p = $this->product();
        $purchase = app(PurchaseReceiptService::class)->save($this->receipt($p));
        $migration = require database_path('migrations/2026_10_05_000000_product_usage_and_material_units.php');
        $migration->down();
        $migration->up();
        $this->assertSame('sale', DB::table('products')->where('id', $p->id)->value('product_usage'));
        $detail = $purchase->purchaseDetails()->first();
        $this->assertSame('sale', $detail->product_usage);
        $this->assertSame(0.5, $detail->stock_quantity);
        $this->assertSame(0.5, InventoryDetail::first()->quantity);
        $this->assertSame(2.0, $detail->base_unit_cost);
    }

    public function test_foreign_inventory_is_not_receivable_and_base_unit_is_protected(): void
    {
        $p = $this->product();
        $purchase = app(PurchaseReceiptService::class)->save($this->receipt($p));
        try {
            $p->update(['unit_of_measure' => 'cm2']);
            $this->fail('Expected validation error');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('unit_of_measure', $e->errors());
        }
        $foreign = Organization::create(['name' => 'Otra', 'owner_id' => Auth::id()]);
        DB::table('inventories')->insert(['id' => $id = (string) Str::uuid(), 'organization_id' => $foreign->id,
            'store_id' => $this->store->id, 'name' => 'Ajeno']);
        $data = $this->receipt($p);
        $data['inventory_id'] = $id;
        $this->expectException(ModelNotFoundException::class);
        app(PurchaseReceiptService::class)->save($data);
    }
}
