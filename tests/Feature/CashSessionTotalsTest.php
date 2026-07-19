<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\V1\CashSessionController;
use App\Models\CashSession;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\Store;
use App\Models\User;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Regresión del descuadre de caja (2026-07-18): el arqueo sumaba el efectivo
 * RECIBIDO (paid_in_nio) sin restar el vuelto (change_nio), inflando el efectivo
 * esperado y produciendo faltantes ficticios. El esperado debe reflejar el
 * efectivo NETO que quedó en el cajón.
 */
class CashSessionTotalsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantManager::clear();
    }

    private function computeExpectedNio(CashSession $session): float
    {
        $controller = new CashSessionController();
        $method = new ReflectionMethod($controller, 'computeSessionTotals');
        $method->setAccessible(true);
        $totals = $method->invoke($controller, $session);
        return (float) $totals['expected_cash_nio'];
    }

    private function makeSession(Organization $org, float $opening): CashSession
    {
        $store = Store::factory()->create();
        return CashSession::create([
            'organization_id' => $org->id,
            'store_id' => $store->id,
            'user_id' => $org->owner_id,
            'opening_balance' => $opening,
            'status' => 'open',
            'opened_at' => now(),
        ]);
    }

    private function cashInvoice(Organization $org, CashSession $session, array $overrides): void
    {
        Invoice::create(array_merge([
            'id' => (string) Str::uuid(),
            'organization_id' => $org->id,
            'store_id' => $session->store_id,
            'user_id' => $org->owner_id,
            'cash_session_id' => $session->id,
            'invoice_number' => 'T-' . random_int(1000, 9999),
            'invoice_date' => now()->toDateString(),
            'payment_date' => now()->toDateString(),
            'client_name' => 'Cliente',
            'total' => 1,
            'discount' => 0,
            'tax' => 0,
            'payment_method' => 'CASH',
            'invoice_status' => 'completed',
            'invoice_type' => 'cash',
            'source' => 'POS',
        ], $overrides));
    }

    public function test_change_is_subtracted_from_expected_cash(): void
    {
        $owner = User::factory()->create();
        $org = Organization::factory()->create(['owner_id' => $owner->id, 'tenancy_type' => 'shared']);
        TenantManager::setTenant($org);

        $session = $this->makeSession($org, 100.0); // fondo inicial C$100

        // Venta C$120: cliente paga C$150 en efectivo, vuelto C$30.
        $this->cashInvoice($org, $session, [
            'grand_total' => 120,
            'paid_in_nio' => 150,
            'paid_in_usd' => 0,
            'exchange_rate' => 36.5,
            'payment_metadata' => ['paid_in_nio' => 150, 'paid_in_usd' => 0, 'change_nio' => 30],
        ]);

        // Efectivo neto en caja por la venta = 120. Esperado = 100 + 120 = 220.
        // (Antes del fix daba 250 por sumar el bruto 150.)
        $this->assertEquals(220.0, $this->computeExpectedNio($session->fresh()));
    }

    public function test_exact_cash_payment_matches(): void
    {
        $owner = User::factory()->create();
        $org = Organization::factory()->create(['owner_id' => $owner->id, 'tenancy_type' => 'shared']);
        TenantManager::setTenant($org);

        $session = $this->makeSession($org, 0.0);

        // Venta C$200, paga exacto, sin vuelto.
        $this->cashInvoice($org, $session, [
            'grand_total' => 200,
            'paid_in_nio' => 200,
            'paid_in_usd' => 0,
            'payment_metadata' => ['paid_in_nio' => 200, 'change_nio' => 0],
        ]);

        $this->assertEquals(200.0, $this->computeExpectedNio($session->fresh()));
    }

    public function test_legacy_invoice_without_breakdown_uses_grand_total(): void
    {
        $owner = User::factory()->create();
        $org = Organization::factory()->create(['owner_id' => $owner->id, 'tenancy_type' => 'shared']);
        TenantManager::setTenant($org);

        $session = $this->makeSession($org, 0.0);

        // Factura vieja sin desglose de efectivo: cae al grand_total.
        $this->cashInvoice($org, $session, [
            'grand_total' => 75,
            'paid_in_nio' => 0,
            'paid_in_usd' => 0,
            'payment_metadata' => null,
        ]);

        $this->assertEquals(75.0, $this->computeExpectedNio($session->fresh()));
    }
}
