<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\Reports\InventoryMarginsReportStrategy;
use App\Services\Reports\SalesReportStrategy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class DraftAndProformaSalesExclusionTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_and_proforma_invoices_are_excluded_from_sales_and_margins_reports(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $org->id]);
        $store = Store::factory()->create(['organization_id' => $org->id]);
        $client = Client::factory()->create(['organization_id' => $org->id]);

        $product = Product::factory()->create([
            'organization_id' => $org->id,
            'name' => 'Widget Test',
            'price' => 50,
            'cost' => 20,
        ]);

        // 1. Factura completada (venta real: $100, 2 unidades)
        $completedInvoice = Invoice::factory()->create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'store_id' => $store->id,
            'client_id' => $client->id,
            'invoice_status' => 'completed',
            'grand_total' => 100,
            'invoice_date' => now()->toDateString(),
            'created_at' => now(),
        ]);
        InvoiceDetail::create([
            'invoice_id' => $completedInvoice->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'price' => 50,
            'cost' => 20,
            'total' => 100,
            'grand_total' => 100,
        ]);

        // 2. Factura borrador (draft: $250, 5 unidades) - NO debe contar como venta
        $draftInvoice = Invoice::factory()->create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'store_id' => $store->id,
            'client_id' => $client->id,
            'invoice_status' => 'draft',
            'grand_total' => 250,
            'invoice_date' => now()->toDateString(),
            'created_at' => now(),
        ]);
        InvoiceDetail::create([
            'invoice_id' => $draftInvoice->id,
            'product_id' => $product->id,
            'quantity' => 5,
            'price' => 50,
            'cost' => 20,
            'total' => 250,
            'grand_total' => 250,
        ]);

        // 3. Proforma (proforma: $350, 7 unidades) - NO debe contar como venta
        $proformaInvoice = Invoice::factory()->create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'store_id' => $store->id,
            'client_id' => $client->id,
            'invoice_status' => 'proforma',
            'grand_total' => 350,
            'invoice_date' => now()->toDateString(),
            'created_at' => now(),
        ]);
        InvoiceDetail::create([
            'invoice_id' => $proformaInvoice->id,
            'product_id' => $product->id,
            'quantity' => 7,
            'price' => 50,
            'cost' => 20,
            'total' => 350,
            'grand_total' => 350,
        ]);

        // 4. Cancelada (canceled: $400, 8 unidades) - NO debe contar como venta
        $canceledInvoice = Invoice::factory()->create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'store_id' => $store->id,
            'client_id' => $client->id,
            'invoice_status' => 'canceled',
            'grand_total' => 400,
            'invoice_date' => now()->toDateString(),
            'created_at' => now(),
        ]);
        InvoiceDetail::create([
            'invoice_id' => $canceledInvoice->id,
            'product_id' => $product->id,
            'quantity' => 8,
            'price' => 50,
            'cost' => 20,
            'total' => 400,
            'grand_total' => 400,
        ]);

        // Verificación en SalesReportStrategy
        $salesStrategy = new SalesReportStrategy();
        $salesFetch = new ReflectionMethod($salesStrategy, 'fetchData');
        $salesFetch->setAccessible(true);
        $salesData = $salesFetch->invoke($salesStrategy, $org->id, []);

        $this->assertEquals(100.0, (float)$salesData['ingresosTotales'], 'SalesReportStrategy no debe sumar borradores ni proformas a ingresos.');
        $this->assertEquals(40.0, (float)$salesData['costoTotal'], 'SalesReportStrategy no debe sumar costos de borradores ni proformas.');
        $this->assertCount(1, $salesData['productosVendidos']);
        $this->assertEquals(2, $salesData['productosVendidos'][0]['total_vendido']);

        // Verificación en InventoryMarginsReportStrategy
        $marginsStrategy = new InventoryMarginsReportStrategy();
        $marginsFetch = new ReflectionMethod($marginsStrategy, 'fetchData');
        $marginsFetch->setAccessible(true);
        $marginsData = $marginsFetch->invoke($marginsStrategy, $org->id, []);

        $marginItems = collect($marginsData['items'])->keyBy('product_id');
        $item = $marginItems->get($product->id);
        if ($item) {
            $this->assertEquals(2.0, (float)$item['sold_qty'], 'InventoryMarginsReportStrategy no debe sumar cantidad de borradores o proformas.');
            $this->assertEquals(100.0, (float)$item['sold_amount'], 'InventoryMarginsReportStrategy no debe sumar monto vendido de borradores o proformas.');
        }

        // Verificación en DashboardController
        $this->setupTenantUser($user, $org);
        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/dashboard/metrics');
        $response->assertStatus(200);
        $response->assertJsonPath('kpis.sales.current', 1);
        $response->assertJsonPath('kpis.revenue.current', 100);
    }
}
