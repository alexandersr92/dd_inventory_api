<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductSkuSetting;
use App\Models\Store;
use App\Models\User;
use App\Services\SkuGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSkuAutoAndRegenerationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Organization $org;
    protected Store $store;
    protected Inventory $inventory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->org = Organization::factory()->create(['owner_id' => $this->user->id]);
        $this->user->update(['organization_id' => $this->org->id]);

        $this->setupTenantUser($this->user, $this->org);
        \App\Services\TenantManager::setTenant($this->org);

        $this->store = Store::factory()->create(['organization_id' => $this->org->id]);
        $this->inventory = Inventory::factory()->create([
            'organization_id' => $this->org->id,
            'name' => 'Bodega Central',
        ]);
        $this->store->inventories()->attach($this->inventory->id);
    }

    public function test_can_get_and_update_sku_settings_with_validation(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/settings/sku');
        $response->assertOk();
        $response->assertJson([
            'enabled' => false,
            'pattern' => 'PROD-{consecutivo:5}',
        ]);

        // Intentar guardar plantilla sin consecutivo
        $invalid = $this->actingAs($this->user)->postJson('/api/v1/settings/sku', [
            'enabled' => true,
            'pattern' => 'PROD-FIXED',
        ]);
        $invalid->assertStatus(422);

        // Intentar guardar con token inválido
        $invalidToken = $this->actingAs($this->user)->postJson('/api/v1/settings/sku', [
            'enabled' => true,
            'pattern' => 'PROD-{proveedor}-{consecutivo:5}',
        ]);
        $invalidToken->assertStatus(422);

        // Guardar plantilla válida con categoria y marca
        $valid = $this->actingAs($this->user)->postJson('/api/v1/settings/sku', [
            'enabled' => true,
            'pattern' => '{categoria}-{marca}-{consecutivo:5}',
        ]);
        $valid->assertOk();
        $valid->assertJson([
            'enabled' => true,
            'pattern' => '{categoria}-{marca}-{consecutivo:5}',
            'preview_example' => 'CAM-NIK-00042',
        ]);
    }

    public function test_automatic_sku_generation_on_product_creation_and_collision_avoidance(): void
    {
        // 1. Crear producto con SKU manual existente
        Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Producto Existente Manual',
            'sku' => 'PROD-00001',
            'cost' => 10,
            'price' => 20,
            'min_stock' => 5,
            'unit_of_measure' => 'unit',
        ]);

        // 2. Activar SKU automático con 'PROD-{consecutivo:5}'
        $setResp = $this->actingAs($this->user)->postJson('/api/v1/settings/sku', [
            'enabled' => true,
            'pattern' => 'PROD-{consecutivo:5}',
        ]);
        $setResp->assertOk();

        // 3. Crear nuevo producto sin SKU: debe evitar la colisión con PROD-00001 y generar PROD-00002
        $resp = $this->actingAs($this->user)->postJson('/api/v1/products', [
            'name' => 'Nuevo Producto Sin SKU',
            'sku' => '',
            'cost' => 15,
            'price' => 30,
            'unit_of_measure' => 'unit',
            'inventory_id' => $this->inventory->id,
        ]);
        $resp->assertStatus(201);
        $this->assertEquals('PROD-00002', $resp->json('sku'));

        // El producto manual original conserva su PROD-00001 intacto
        $manualProd = Product::where('organization_id', $this->org->id)->where('name', 'Producto Existente Manual')->first();
        $this->assertEquals('PROD-00001', $manualProd->sku);

        // 4. Crear producto enviando SKU explícito: debe respetarlo
        $respExplicit = $this->actingAs($this->user)->postJson('/api/v1/products', [
            'name' => 'Producto Con SKU Propio',
            'sku' => 'CUSTOM-999',
            'cost' => 5,
            'price' => 10,
            'unit_of_measure' => 'unit',
            'inventory_id' => $this->inventory->id,
        ]);
        $respExplicit->assertStatus(201);
        $this->assertEquals('CUSTOM-999', $respExplicit->json('sku'));
    }

    public function test_preview_regeneration_blocks_when_required_category_or_brand_is_missing(): void
    {
        // Activar plantilla que requiere categoría y marca
        $this->actingAs($this->user)->postJson('/api/v1/settings/sku', [
            'enabled' => true,
            'pattern' => '{categoria}-{marca}-{consecutivo:4}',
        ]);

        // Crear producto sin categoría ni marca
        $product = Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Zapatos Huérfanos',
            'sku' => 'ZAP-01',
            'cost' => 20,
            'price' => 40,
            'min_stock' => 1,
            'unit_of_measure' => 'unit',
        ]);

        // Solicitar vista previa
        $response = $this->actingAs($this->user)->postJson('/api/v1/products/sku/preview-regeneration');
        $response->assertOk();
        $response->assertJson([
            'can_proceed' => false,
            'total_products' => 1,
        ]);
        $this->assertNotEmpty($response->json('missing_data'));
        $this->assertContains('categoria', $response->json('missing_data.0.missing_fields'));
        $this->assertContains('marca', $response->json('missing_data.0.missing_fields'));
    }

    public function test_bulk_sku_regeneration_updates_all_products_preserves_ids_and_audits(): void
    {
        $cat = Category::create([
            'organization_id' => $this->org->id,
            'name' => 'Camisas',
            'slug' => 'camisas',
        ]);
        $brand = Brand::create([
            'organization_id' => $this->org->id,
            'name' => 'Nike',
            'slug' => 'nike',
        ]);

        $prod1 = Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Camisa Deportiva',
            'sku' => 'OLD-SKU-1',
            'category_id' => $cat->id,
            'brand_id' => $brand->id,
            'cost' => 10,
            'price' => 25,
            'min_stock' => 2,
            'unit_of_measure' => 'unit',
        ]);

        $prod2 = Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Camisa Polo',
            'sku' => 'OLD-SKU-2',
            'category_id' => $cat->id,
            'brand_id' => $brand->id,
            'cost' => 12,
            'price' => 30,
            'min_stock' => 2,
            'unit_of_measure' => 'unit',
        ]);

        $p1Id = $prod1->id;
        $p2Id = $prod2->id;

        // 1. Obtener vista previa
        $previewResp = $this->actingAs($this->user)->postJson('/api/v1/products/sku/preview-regeneration', [
            'pattern' => '{categoria}-{marca}-{consecutivo:5}',
        ]);
        $previewResp->assertOk();
        $previewResp->assertJson([
            'can_proceed' => true,
            'total_products' => 2,
        ]);

        $token = $previewResp->json('preview_token');
        $this->assertNotEmpty($token);

        // 2. Confirmar regeneración
        $confirmResp = $this->actingAs($this->user)->postJson('/api/v1/products/sku/regenerate', [
            'preview_token' => $token,
        ]);
        $confirmResp->assertOk();
        $confirmResp->assertJson([
            'success' => true,
            'total_updated' => 2,
        ]);

        // 3. Verificar que los productos tienen sus nuevos SKUs sobre los MISMOS IDs
        $freshP1 = Product::find($p1Id);
        $freshP2 = Product::find($p2Id);

        $this->assertEquals($p1Id, $freshP1->id);
        $this->assertEquals($p2Id, $freshP2->id);
        $this->assertEqualsCanonicalizing(['CAM-NIK-00001', 'CAM-NIK-00002'], [$freshP1->sku, $freshP2->sku]);
        $this->assertEquals(25, $freshP1->price);
        $this->assertEquals(10, $freshP1->cost);

        // 4. Verificar que el consecutivo de la organización se actualizó a 2
        $config = ProductSkuSetting::where('organization_id', $this->org->id)->first();
        $this->assertEquals(2, $config->counter);

        // 5. Verificar que el mapa de auditoría se guardó
        $this->assertDatabaseHas('sku_regeneration_logs', [
            'organization_id' => $this->org->id,
            'user_id' => $this->user->id,
            'total_products' => 2,
        ]);
    }

    public function test_stale_preview_rejection_when_catalogue_or_counter_changes(): void
    {
        $prod = Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Producto Base',
            'sku' => 'BASE-01',
            'cost' => 10,
            'price' => 20,
            'min_stock' => 1,
            'unit_of_measure' => 'unit',
        ]);

        // Obtener vista previa
        $previewResp = $this->actingAs($this->user)->postJson('/api/v1/products/sku/preview-regeneration', [
            'pattern' => 'PROD-{consecutivo:5}',
        ]);
        $token = $previewResp->json('preview_token');

        // Alterar el catálogo antes de confirmar la vista previa (nuevo producto agregado)
        Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Producto Añadido Posterior',
            'sku' => 'POST-02',
            'cost' => 10,
            'price' => 20,
            'min_stock' => 1,
            'unit_of_measure' => 'unit',
        ]);

        // Intentar confirmar con la vista previa previa que ya no coincide con el catálogo
        $confirm = $this->actingAs($this->user)->postJson('/api/v1/products/sku/regenerate', [
            'preview_token' => $token,
        ]);
        $confirm->assertStatus(409);
    }

    public function test_multitenancy_isolation_for_sku_regeneration(): void
    {
        // Otra organización
        $otherUser = User::factory()->create();
        $otherOrg = Organization::factory()->create(['owner_id' => $otherUser->id]);
        $otherUser->update(['organization_id' => $otherOrg->id]);
        $this->setupTenantUser($otherUser, $otherOrg);

        \App\Services\TenantManager::setTenant($otherOrg);
        $prodOther = Product::create([
            'organization_id' => $otherOrg->id,
            'name' => 'Producto Otra Org',
            'sku' => 'OTHER-01',
            'cost' => 10,
            'price' => 20,
            'min_stock' => 1,
            'unit_of_measure' => 'unit',
        ]);
        \App\Services\TenantManager::setTenant($this->org);

        // Vista previa de la org original no debe incluir productos de la otra org
        $preview = $this->actingAs($this->user)->postJson('/api/v1/products/sku/preview-regeneration', [
            'pattern' => 'PROD-{consecutivo:5}',
        ]);
        $preview->assertOk();
        $preview->assertJson(['total_products' => 0]);

        // El producto de la otra org no debe ser modificado
        $this->assertEquals('OTHER-01', $prodOther->fresh()->sku);
    }

    public function test_purchase_store_generates_automatic_sku_when_code_is_empty(): void
    {
        // 1. Activar SKU automático
        $this->actingAs($this->user)->postJson('/api/v1/settings/sku', [
            'enabled' => true,
            'pattern' => 'PUR-{consecutivo:5}',
        ]);

        $supplier = \App\Models\Supplier::factory()->create(['organization_id' => $this->org->id]);

        // 2. Registrar compra con un producto nuevo cuyo código viene vacío o '[Auto]'
        $payload = [
            'supplier_id' => $supplier->id,
            'store_id' => $this->store->id,
            'inventory_id' => $this->inventory->id,
            'payment_type' => 'cash',
            'purchase_date' => now()->toDateString(),
            'total_items' => 1,
            'total' => 100,
            'products' => [
                [
                    'product_id' => null,
                    'sku' => '[Auto]',
                    'product_name' => 'Producto De Compra Auto',
                    'barcode' => '750000000099',
                    'price' => 20,
                    'quantity' => 10,
                    'cost' => 10,
                    'total' => 100,
                ],
            ],
        ];

        $resp = $this->actingAs($this->user)->postJson('/api/v1/purchases', $payload);
        $resp->assertStatus(201);

        // Verificar que el nuevo producto se creó con el SKU generado automáticamente 'PUR-00001'
        $createdProduct = Product::where('organization_id', $this->org->id)
            ->where('name', 'Producto De Compra Auto')
            ->first();

        $this->assertNotNull($createdProduct);
        $this->assertEquals('PUR-00001', $createdProduct->sku);
    }
}
