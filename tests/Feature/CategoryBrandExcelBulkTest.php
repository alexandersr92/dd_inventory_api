<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Brand;
use App\Models\Product;
use App\Models\Organization;
use App\Models\Store;
use App\Models\Inventory;
use App\Models\InventoryDetail;
use App\Models\Supplier;
use App\Models\User;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Maatwebsite\Excel\Facades\Excel;

class CategoryBrandExcelBulkTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Organization $organization;
    protected Store $store;
    protected Inventory $inventory;

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
        ]);
        $this->user->stores()->attach($this->store->id);

        $this->inventory = Inventory::factory()->create([
            'organization_id' => $this->organization->id,
            'store_id' => $this->store->id,
            'name' => 'Inventario Central',
        ]);
    }

    public function test_hierarchical_categories_crud_and_tree(): void
    {
        Sanctum::actingAs($this->user);

        // 1. Create root category
        $rootRes = $this->postJson('/api/v1/categories', [
            'name' => 'Ropa',
        ]);
        $rootRes->assertCreated();
        $rootId = $rootRes->json('data.id');
        $this->assertEquals(1, $rootRes->json('data.level'));
        $this->assertEquals('Ropa', $rootRes->json('data.full_path'));

        // 2. Create child category
        $childRes = $this->postJson('/api/v1/categories', [
            'name' => 'Camisas',
            'parent_id' => $rootId,
        ]);
        $childRes->assertCreated();
        $childId = $childRes->json('data.id');
        $this->assertEquals(2, $childRes->json('data.level'));
        $this->assertEquals('Ropa > Camisas', $childRes->json('data.full_path'));

        // 3. Create subchild category
        $subChildRes = $this->postJson('/api/v1/categories', [
            'name' => 'Manga corta',
            'parent_id' => $childId,
        ]);
        $subChildRes->assertCreated();
        $this->assertEquals(3, $subChildRes->json('data.level'));
        $this->assertEquals('Ropa > Camisas > Manga corta', $subChildRes->json('data.full_path'));

        // 4. Test tree endpoint
        $treeRes = $this->getJson('/api/v1/categories/tree');
        $treeRes->assertOk();
        $treeData = $treeRes->json('data');
        $this->assertCount(1, $treeData);
        $this->assertEquals('Ropa', $treeData[0]['name']);
        $this->assertNotEmpty($treeData[0]['children']);
        $this->assertEquals('Camisas', $treeData[0]['children'][0]['name']);

        // 5. Test cycle prevention
        $cycleRes = $this->putJson("/api/v1/categories/{$rootId}", [
            'parent_id' => $childId,
        ]);
        $cycleRes->assertStatus(422);

        // 6. Test delete prevention if has children
        $delRes = $this->deleteJson("/api/v1/categories/{$rootId}");
        $delRes->assertStatus(422);
    }

    public function test_brands_crud_and_case_insensitive_uniqueness(): void
    {
        Sanctum::actingAs($this->user);

        $res1 = $this->postJson('/api/v1/brands', [
            'name' => 'Nike',
        ]);
        $res1->assertCreated();
        $brandId = $res1->json('data.id');

        // Duplicate case-insensitive
        $res2 = $this->postJson('/api/v1/brands', [
            'name' => '  nike  ',
        ]);
        $res2->assertStatus(422);

        // Update brand
        $updateRes = $this->putJson("/api/v1/brands/{$brandId}", [
            'name' => 'Nike Sportswear',
        ]);
        $updateRes->assertOk();
        $this->assertEquals('Nike Sportswear', $updateRes->json('data.name'));
    }

    public function test_product_bulk_update_does_not_modify_inventory_or_prices(): void
    {
        Sanctum::actingAs($this->user);

        $brand = Brand::create([
            'organization_id' => $this->organization->id,
            'name' => 'Adidas',
            'slug' => 'adidas',
        ]);

        $category = Category::create([
            'organization_id' => $this->organization->id,
            'name' => 'Calzado',
            'slug' => 'calzado',
            'level' => 1,
        ]);

        $product = Product::create([
            'organization_id' => $this->organization->id,
            'sku' => 'ZAP-001',
            'name' => 'Zapato Deportivo',
            'cost' => 50,
            'price' => 100,
            'unit_of_measure' => 'unit',
        ]);

        $detail = InventoryDetail::create([
            'inventory_id' => $this->inventory->id,
            'product_id' => $product->id,
            'quantity' => 15,
            'price' => 100,
            'status' => 'active',
        ]);

        // Bulk update descriptive information only
        $res = $this->postJson('/api/v1/products/bulk/update', [
            'items' => [
                [
                    'product_id' => $product->id,
                    'name' => 'Zapato Deportivo Pro 2026',
                    'barcode' => '999888777',
                    'category_id' => $category->id,
                    'brand_id' => $brand->id,
                ]
            ]
        ]);

        $res->assertOk();
        $res->assertJson(['success' => true, 'updated_count' => 1]);

        $product->refresh();
        $detail->refresh();

        // Descriptive fields updated
        $this->assertEquals('Zapato Deportivo Pro 2026', $product->name);
        $this->assertEquals('999888777', $product->barcode);
        $this->assertEquals($category->id, $product->category_id);
        $this->assertEquals($brand->id, $product->brand_id);

        // Crucial: Financial & inventory fields MUST NOT change
        $this->assertEquals(50, (float)$product->cost);
        $this->assertEquals(100, (float)$product->price);
        $this->assertEquals(15, (float)$detail->quantity);
    }

    public function test_purchases_template_download(): void
    {
        Sanctum::actingAs($this->user);

        $res = $this->get('/api/v1/purchases/template');
        $res->assertOk();
        $this->assertStringContainsString('spreadsheet', $res->headers->get('content-type'));
    }

    public function test_purchases_excel_upload_and_resolution(): void
    {
        Sanctum::actingAs($this->user);

        $rootCat = Category::create([
            'organization_id' => $this->organization->id,
            'name' => 'Ropa',
            'slug' => 'ropa',
            'level' => 1,
        ]);

        $childCat = Category::create([
            'organization_id' => $this->organization->id,
            'parent_id' => $rootCat->id,
            'name' => 'Camisas',
            'slug' => 'camisas',
            'level' => 2,
        ]);

        $brand = Brand::create([
            'organization_id' => $this->organization->id,
            'name' => 'Zara',
            'slug' => 'zara',
        ]);

        $supplier = Supplier::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Distribuidora Textil',
        ]);

        // CSV content with headers: Código, categoria, Marca, Nombre, Codigo Barra, Precio, Cantidad, Costo, Total, Proveedor
        $csv = "Código,categoria,Marca,Nombre,Codigo Barra,Precio,Cantidad,Costo,Total,Proveedor\n" .
               "SHIRT-01,Ropa > Camisas,Zara,Camisa Blanca,12345678,30.00,5,10.00,50.00,Distribuidora Textil\n" .
               "SHIRT-BAD,Inexistente,NoExiste,Camisa Error,8888,20.00,2,5.00,999.00,ProvFalso\n";

        $file = UploadedFile::fake()->createWithContent('compras.csv', $csv);

        $res = $this->postJson('/api/v1/purchases/upload', [
            'file' => $file,
        ]);

        $res->assertOk();
        $validNew = $res->json('valid_new');
        $invalid = $res->json('invalid');

        $this->assertCount(1, $validNew);
        $this->assertEquals('SHIRT-01', $validNew[0]['sku']);
        $this->assertEquals($childCat->id, $validNew[0]['category_id']);
        $this->assertEquals('Ropa > Camisas', $validNew[0]['category_name']);
        $this->assertEquals($brand->id, $validNew[0]['brand_id']);
        $this->assertEquals('Zara', $validNew[0]['brand_name']);
        $this->assertEquals($supplier->id, $validNew[0]['supplier_id']);
        $this->assertEquals(50.00, $validNew[0]['total']);

        // Check invalid row errors
        $this->assertCount(1, $invalid);
        $this->assertEquals(3, $invalid[0]['row']);
        $this->assertArrayHasKey('category', $invalid[0]['field_errors']);
        $this->assertArrayHasKey('brand', $invalid[0]['field_errors']);
        $this->assertArrayHasKey('supplier', $invalid[0]['field_errors']);
        $this->assertArrayHasKey('total', $invalid[0]['field_errors']);
    }

    public function test_purchases_store_assigns_category_and_brand_to_new_product(): void
    {
        Sanctum::actingAs($this->user);

        $category = Category::create([
            'organization_id' => $this->organization->id,
            'name' => 'Accesorios',
            'slug' => 'accesorios',
            'level' => 1,
        ]);

        $brand = Brand::create([
            'organization_id' => $this->organization->id,
            'name' => 'Casio',
            'slug' => 'casio',
        ]);

        $supplier = Supplier::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $res = $this->postJson('/api/v1/purchases', [
            'store_id' => $this->store->id,
            'supplier_id' => $supplier->id,
            'inventory_id' => $this->inventory->id,
            'total' => 150,
            'total_items' => 3,
            'purchase_date' => now()->toDateString(),
            'products' => [
                [
                    'product_id' => null,
                    'sku' => 'WATCH-01',
                    'product_name' => 'Reloj Digital',
                    'barcode' => '7891011',
                    'price' => 75,
                    'cost' => 50,
                    'quantity' => 3,
                    'category_id' => $category->id,
                    'brand_id' => $brand->id,
                ]
            ]
        ]);

        $res->assertCreated();

        $product = Product::where('organization_id', $this->organization->id)
            ->where('sku', 'WATCH-01')
            ->first();

        $this->assertNotNull($product);
        $this->assertEquals($category->id, $product->category_id);
        $this->assertEquals($brand->id, $product->brand_id);
    }

    public function test_product_bulk_preview_diff(): void
    {
        Sanctum::actingAs($this->user);

        $product = Product::create([
            'organization_id' => $this->organization->id,
            'sku' => 'BULK-01',
            'name' => 'Nombre Antiguo',
            'barcode' => '111111',
            'cost' => 10,
            'price' => 20,
            'unit_of_measure' => 'unit',
        ]);

        $csv = "Código,categoria,Marca,Nombre,Codigo Barra\n" .
               "BULK-01,,,Nombre Nuevo,222222\n" .
               "NOTFOUND,,,X,Y\n";

        $file = UploadedFile::fake()->createWithContent('bulk.csv', $csv);

        $res = $this->postJson('/api/v1/products/bulk/preview', [
            'file' => $file,
        ]);

        $res->assertOk();
        $this->assertEquals(2, $res->json('total_rows'));
        $this->assertEquals(1, $res->json('valid_count'));
        $this->assertEquals(1, $res->json('invalid_count'));

        $item = $res->json('items.0');
        $this->assertEquals('BULK-01', $item['sku']);
        $this->assertTrue($item['name']['changed']);
        $this->assertEquals('Nombre Antiguo', $item['name']['current']);
        $this->assertEquals('Nombre Nuevo', $item['name']['new']);
        $this->assertTrue($item['barcode']['changed']);
    }
}
