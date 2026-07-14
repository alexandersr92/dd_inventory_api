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

        LandingPlan::create([
            'name' => 'Plan Pro',
            'price' => 19.99,
            'period' => 'monthly',
            'discount' => 10.00,
            'features' => ['Unlimited sales', '24/7 support'],
            'is_featured' => true,
            'status' => 'active'
        ]);

        $response = $this->getJson('/api/v1/landing/content');
        $response->assertStatus(200);
        $response->assertJson([
            'hero' => [
                'title' => 'SaaS Billing POS',
                'subtitle' => 'The best software'
            ]
        ]);

        $response = $this->getJson('/api/v1/landing/plans');
        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonFragment([
            'name' => 'Plan Pro',
            'price' => '19.99',
            'period' => 'monthly'
        ]);
    }

    /**
     * Test media library upload, listing, and deletion.
     */
    public function test_media_library_admin_endpoints(): void
    {
        Sanctum::actingAs($this->admin);
        Storage::fake('public');

        $file = UploadedFile::fake()->image('logo.png');
        $response = $this->postJson('/api/v1/landing/admin/media', [
            'file' => $file
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'message',
            'media' => ['id', 'filename', 'disk_path', 'url']
        ]);

        $mediaId = $response->json('media.id');
        $diskPath = $response->json('media.disk_path');

        Storage::disk('public')->assertExists($diskPath);

        $response = $this->getJson('/api/v1/landing/admin/media');
        $response->assertStatus(200);
        $response->assertJsonCount(1);

        $response = $this->deleteJson("/api/v1/landing/admin/media/{$mediaId}");
        $response->assertStatus(200);

        Storage::disk('public')->assertMissing($diskPath);
        $this->assertDatabaseMissing('landing_media', ['id' => $mediaId], 'central');
    }

    /**
     * Test saving JSON section content.
     */
    public function test_save_section_content_admin_endpoint(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->putJson('/api/v1/landing/admin/content/hero', [
            'content' => [
                'title' => 'Updated SaaS Title',
                'description' => 'Dynamic description text'
            ]
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('landing_contents', [
            'section_key' => 'hero'
        ], 'central');

        $response = $this->getJson('/api/v1/landing/content');
        $response->assertStatus(200);
        $response->assertJson([
            'hero' => [
                'title' => 'Updated SaaS Title',
                'description' => 'Dynamic description text'
            ]
        ]);
    }

    /**
     * Test pricing plan management.
     */
    public function test_pricing_plan_admin_management(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/v1/landing/admin/plans', [
            'name' => 'Super Plan',
            'price' => 49.99,
            'period' => 'yearly',
            'discount' => 15.00,
            'features' => ['Feature 1', 'Feature 2'],
            'is_featured' => false,
            'status' => 'active'
        ]);

        $response->assertStatus(200);
        $planId = $response->json('plan.id');

        $this->assertDatabaseHas('landing_plans', [
            'id' => $planId,
            'name' => 'Super Plan',
            'period' => 'yearly'
        ], 'central');

        $response = $this->putJson("/api/v1/landing/admin/plans/{$planId}", [
            'name' => 'Super Plan Updated',
            'price' => 39.99,
            'period' => 'monthly',
            'discount' => 5.00,
            'features' => ['Feature 1 Updated'],
            'is_featured' => true,
            'status' => 'active'
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('landing_plans', [
            'id' => $planId,
            'name' => 'Super Plan Updated',
            'period' => 'monthly'
        ], 'central');

        $response = $this->deleteJson("/api/v1/landing/admin/plans/{$planId}");
        $response->assertStatus(200);
        $this->assertDatabaseMissing('landing_plans', ['id' => $planId], 'central');
    }
}
