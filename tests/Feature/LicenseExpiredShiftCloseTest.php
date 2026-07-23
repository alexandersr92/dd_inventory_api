<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Store;
use App\Models\User;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Con la licencia vencida, el resto del sistema se bloquea con 402, pero un
 * cajero DEBE poder cerrar la caja que dejó abierta para cuadrar la gaveta.
 * Si no, el arqueo del día queda atrapado y el efectivo sin conciliar.
 *
 * Verifica la exención de "cierre de turno" del TenantDatabaseSwitcher: es
 * consciente del método (path + verbo) para no exponer, p. ej., abrir una
 * nueva caja ni escribir settings.
 */
class LicenseExpiredShiftCloseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantManager::clear();
    }

    private function makeExpiredTenant(): array
    {
        $owner = User::factory()->create();
        $org = Organization::factory()->create([
            'name' => 'Org licencia vencida',
            'tenancy_type' => 'shared',
            'owner_id' => $owner->id,
            'status' => 'active',
            'is_lifetime' => false,
            'license_expires_at' => now()->subDay(),
        ]);
        $owner->update(['organization_id' => $org->id]);
        $this->setupTenantUser($owner, $org);

        $store = $this->withTenant($org, fn () => Store::factory()->create(['name' => 'Tienda']));

        return [$owner, $org, $store];
    }

    private function withTenant(Organization $org, callable $fn)
    {
        TenantManager::setTenant($org);
        $result = $fn();
        TenantManager::clear();

        return $result;
    }

    public function test_expired_license_blocks_normal_routes(): void
    {
        [$owner] = $this->makeExpiredTenant();
        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/clients')
            ->assertStatus(Response::HTTP_PAYMENT_REQUIRED)
            ->assertJson(['error_code' => 'LICENSE_EXPIRED']);
    }

    public function test_expired_license_allows_reading_active_cash_session(): void
    {
        [$owner, , $store] = $this->makeExpiredTenant();
        Sanctum::actingAs($owner);

        // No hay sesión abierta: 200 con is_open=false. Lo clave es que NO sea 402.
        $this->getJson("/api/v1/cash-sessions/active?store_id={$store->id}")
            ->assertOk()
            ->assertJson(['is_open' => false]);
    }

    public function test_expired_license_allows_reading_expense_categories(): void
    {
        [$owner] = $this->makeExpiredTenant();
        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/expense-categories')->assertOk();
    }

    public function test_expired_license_allows_posting_cash_session_close(): void
    {
        [$owner] = $this->makeExpiredTenant();
        Sanctum::actingAs($owner);

        // Sin sesión válida fallará la validación (422), pero NUNCA con 402:
        // el cierre está exento del bloqueo por licencia.
        $response = $this->postJson('/api/v1/cash-sessions/close', []);

        $this->assertNotSame(
            Response::HTTP_PAYMENT_REQUIRED,
            $response->status(),
            'El cierre de caja no debe bloquearse por licencia vencida.'
        );
    }

    public function test_exemption_is_method_aware_and_does_not_leak_to_opening_a_session(): void
    {
        [$owner, , $store] = $this->makeExpiredTenant();
        Sanctum::actingAs($owner);

        // Abrir una NUEVA caja no está exento: sigue bloqueado con 402.
        $this->postJson('/api/v1/cash-sessions/open', [
            'store_id' => $store->id,
            'opening_balance' => 100,
        ])->assertStatus(Response::HTTP_PAYMENT_REQUIRED);
    }
}
