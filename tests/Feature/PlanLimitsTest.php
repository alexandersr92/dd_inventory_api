<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\Store;
use App\Models\User;
use App\Services\PlanLimits;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanLimitsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantManager::clear();
    }

    private function orgWithPlan(?Plan $plan): Organization
    {
        $owner = User::factory()->create();
        $org = Organization::factory()->create([
            'tenancy_type' => 'shared',
            'owner_id' => $owner->id,
            'plan_id' => $plan?->id,
        ]);
        $owner->update(['organization_id' => $org->id]);
        TenantManager::setTenant($org);

        return $org;
    }

    public function test_store_limit_is_enforced(): void
    {
        $plan = Plan::create([
            'slug' => 'test-1-store',
            'name' => 'Test',
            'duration_months' => 3,
            'max_stores' => 1,
            'tenancy_type' => 'shared',
        ]);
        $org = $this->orgWithPlan($plan);

        $this->assertTrue(PlanLimits::for($org)->canAddStore());

        Store::factory()->create();

        $this->assertFalse(PlanLimits::for($org->fresh())->canAddStore());
    }

    public function test_no_plan_means_unlimited(): void
    {
        $org = $this->orgWithPlan(null);

        Store::factory()->count(5)->create();

        $this->assertTrue(PlanLimits::for($org->fresh())->canAddStore());
        $this->assertTrue(PlanLimits::for($org->fresh())->canAddSeller());
        $this->assertTrue(PlanLimits::for($org->fresh())->canCreateInvoice());
    }

    public function test_usage_reports_used_and_limit(): void
    {
        $plan = Plan::create([
            'slug' => 'test-usage',
            'name' => 'Test',
            'duration_months' => 6,
            'max_stores' => 3,
            'tenancy_type' => 'shared',
        ]);
        $org = $this->orgWithPlan($plan);
        Store::factory()->count(2)->create();

        $usage = PlanLimits::for($org->fresh())->usage();

        $this->assertEquals(2, $usage['stores']['used']);
        $this->assertEquals(3, $usage['stores']['limit']);
    }
}
