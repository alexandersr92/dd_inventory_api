<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditDetail;
use App\Models\Module;
use App\Models\Organization;
use App\Models\Store;
use App\Models\User;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Anular un abono (pago de crédito) debe restaurar la deuda del crédito y dejar
 * el abono marcado como anulado (auditable), sin borrarlo. Antes no existía la
 * forma de anular un abono registrado por error.
 */
class VoidCreditPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantManager::clear();
    }

    private function makeOrgOwner(): array
    {
        $owner = User::factory()->create();
        $org = Organization::factory()->create([
            'tenancy_type' => 'shared',
            'owner_id' => $owner->id,
            'status' => 'active',
        ]);
        $owner->update(['organization_id' => $org->id]);
        $this->setupTenantUser($owner, $org);

        // Módulo de créditos activo (para pasar el middleware module:credits).
        $module = Module::firstOrCreate(['slug' => 'credits'], ['name' => 'Créditos']);
        $org->modules()->syncWithoutDetaching([$module->id => ['status' => 'active']]);

        return [$owner, $org];
    }

    private function withTenant(Organization $org, callable $fn)
    {
        TenantManager::setTenant($org);
        $result = $fn();
        TenantManager::clear();

        return $result;
    }

    private function makeCreditWithAbono(User $owner, Organization $org, float $total, float $debt, float $abono, string $status): array
    {
        return $this->withTenant($org, function () use ($owner, $org, $total, $debt, $abono, $status) {
            // El crédito cuelga de una factura; la factory arma sus dependencias.
            $invoice = \App\Models\Invoice::factory()->create();
            $credit = Credit::create([
                'user_id' => $owner->id,
                'organization_id' => $org->id,
                'store_id' => $invoice->store_id,
                'client_id' => $invoice->client_id,
                'invoice_id' => $invoice->id,
                'total' => $total,
                'debt' => $debt,
                'credit_status' => $status,
            ]);
            $detail = CreditDetail::create([
                'credit_id' => $credit->id,
                'amount' => $abono,
                'date' => now()->toDateString(),
                'payment_method' => 'CASH',
            ]);

            return [$credit, $detail];
        });
    }

    public function test_voiding_an_abono_restores_the_debt(): void
    {
        [$owner, $org] = $this->makeOrgOwner();
        // total 100, ya se abonaron 40 → debt 60.
        [$credit, $detail] = $this->makeCreditWithAbono($owner, $org, 100, 60, 40, 'active');

        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/credits/payment/{$detail->id}/void", ['reason' => 'error de digitación'])
            ->assertOk();

        $this->assertSame(100.0, (float) $credit->fresh()->debt);
        $this->assertNotNull($detail->fresh()->voided_at);
    }

    public function test_voiding_the_abono_of_a_paid_credit_reactivates_it(): void
    {
        [$owner, $org] = $this->makeOrgOwner();
        // Crédito saldado con un abono de 100 → al anular vuelve a deber.
        [$credit, $detail] = $this->makeCreditWithAbono($owner, $org, 100, 0, 100, 'paid');

        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/credits/payment/{$detail->id}/void")->assertOk();

        $fresh = $credit->fresh();
        $this->assertSame(100.0, (float) $fresh->debt);
        $this->assertSame('active', $fresh->credit_status);
    }

    public function test_cannot_void_the_same_abono_twice(): void
    {
        [$owner, $org] = $this->makeOrgOwner();
        [, $detail] = $this->makeCreditWithAbono($owner, $org, 100, 60, 40, 'active');

        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/credits/payment/{$detail->id}/void")->assertOk();
        $this->postJson("/api/v1/credits/payment/{$detail->id}/void")->assertStatus(409);
    }
}
