<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\PaymentSubmission;
use App\Models\User;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Un plan es solo su tier (límites) con dos precios (mensual/anual); la duración
 * de la licencia sale del ciclo que elige el cliente al pagar, no del plan.
 */
class PlanBillingCycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantManager::clear();
    }

    private function makePlan(): Plan
    {
        return Plan::create([
            'slug' => 'tier-' . uniqid(),
            'name' => 'Tier de prueba',
            'max_stores' => 1,
            'max_sellers' => 2,
            'max_monthly_invoices' => 500,
            'tenancy_type' => 'shared',
            'price_monthly' => 100,
            'price_annual' => 1000,
            'currency' => 'NIO',
            'is_active' => true,
        ]);
    }

    public function test_months_and_price_for_cycle(): void
    {
        $plan = $this->makePlan();

        $this->assertSame(1, Plan::monthsForCycle('monthly'));
        $this->assertSame(12, Plan::monthsForCycle('annual'));
        // Cualquier valor no-anual cae a mensual (1 mes).
        $this->assertSame(1, Plan::monthsForCycle('cualquier-cosa'));

        $this->assertSame(100.0, $plan->priceForCycle('monthly'));
        $this->assertSame(1000.0, $plan->priceForCycle('annual'));
    }

    public function test_submit_stores_the_chosen_billing_cycle(): void
    {
        Storage::fake('local');

        $owner = User::factory()->create();
        $org = Organization::factory()->create([
            'tenancy_type' => 'shared',
            'owner_id' => $owner->id,
            'status' => 'active',
            'is_lifetime' => false,
            'license_expires_at' => now()->addYear(),
        ]);
        $owner->update(['organization_id' => $org->id]);
        $this->setupTenantUser($owner, $org);

        $plan = $this->makePlan();
        Sanctum::actingAs($owner);

        $response = $this->postJson('/api/v1/payments/submit', [
            'plan_id' => $plan->id,
            'billing_cycle' => 'annual',
            'amount' => 1000,
            'receipt' => UploadedFile::fake()->create('comprobante.pdf', 120, 'application/pdf'),
        ]);

        $response->assertCreated();

        $submission = PaymentSubmission::where('organization_id', $org->id)->first();
        $this->assertNotNull($submission);
        $this->assertSame('annual', $submission->billing_cycle);
    }
}
