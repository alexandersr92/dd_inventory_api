<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Organization;
use App\Models\LandingContent;
use App\Models\LandingPlan;
use App\Models\LandingMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    private $admin;
    private $org;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->org = Organization::factory()->create([
            'owner_id' => $this->admin->id,
            'tenancy_type' => 'shared',
            'is_lifetime' => true,
        ]);
        $this->admin->update(['organization_id' => $this->org->id]);
        $this->setupTenantUser($this->admin, $this->org);
    }

    /**
     * Test public content and plans endpoints.
     */
    public function test_public_landing_endpoints(): void
    {
        LandingContent::create([
            'section_key' => 'hero',
            'content' => ['title' => 'SaaS Billing POS', 'subtitle' => 'The best software']
        ]);

        // La landing muestra los planes de licenciamiento reales (tabla plans),
        // no una tabla de marketing aparte.
        \App\Models\Plan::create([
            'slug' => 'landing-pro-test',
            'name' => 'Plan Pro Landing',
            'max_stores' => 3,
            'max_sellers' => 5,
            'max_monthly_invoices' => 3000,
            'tenancy_type' => 'shared',
            'price_monthly' => 19.99,
            'price_annual' => 199.99,
            'currency' => 'NIO',
            'is_active' => true,
            'is_featured' => true,
        ]);

        $response = $this->getJson('/api/v1/landing/content');
        $response->assertStatus(200);
        $response->assertJson([
            'hero' => [
                'title' => 'SaaS Billing POS',
                'subtitle' => 'The best software'
            ]
        ]);

        // Sin assertJsonCount: la conexión central no se revierte entre tests,
        // así que puede haber otros planes; basta con que el creado esté presente.
        $response = $this->getJson('/api/v1/landing/plans');
        $response->assertStatus(200);
        $response->assertJsonFragment([
            'name' => 'Plan Pro Landing',
            'price_monthly' => 19.99,
            'price_annual' => 199.99,
            'is_featured' => true,
        ]);
    }

}
