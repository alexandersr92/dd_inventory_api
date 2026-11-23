<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\ProductRelease;
use App\Models\User;
use App\Services\TenantManager;
use Database\Seeders\ProductReleasesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductReleasesSeederTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Organization $organization;

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
    }

    public function test_seeder_creates_initial_1_1_0_release(): void
    {
        $this->assertDatabaseMissing('product_releases', ['version' => '1.1.0']);

        $this->seed(ProductReleasesSeeder::class);

        $this->assertDatabaseHas('product_releases', [
            'version' => '1.1.0',
            'title' => 'Más flexibilidad al facturar y nuevas opciones para tu negocio',
        ]);

        $release = ProductRelease::where('version', '1.1.0')->first();
        $this->assertNotNull($release);
        $this->assertNotNull($release->published_at);
        $this->assertTrue($release->published_at->isPast());
        $this->assertStringContainsString('conceptos libres', $release->body);
        $this->assertStringContainsString('sesión iniciada', $release->body);
        $this->assertStringContainsString('proformas', $release->body);
        $this->assertStringContainsString('borradores', $release->body);
        $this->assertStringContainsString('Novedades', $release->body);
    }

    public function test_seeder_is_idempotent_and_does_not_duplicate(): void
    {
        $this->seed(ProductReleasesSeeder::class);
        $this->assertEquals(1, ProductRelease::where('version', '1.1.0')->count());

        // Second run
        $this->seed(ProductReleasesSeeder::class);
        $this->assertEquals(1, ProductRelease::where('version', '1.1.0')->count());
    }

    public function test_seeder_preserves_manually_edited_release(): void
    {
        // Pre-create an edited release
        $customDate = now()->subDays(10);
        $release = ProductRelease::create([
            'version' => '1.1.0',
            'title' => 'Título personalizado por el administrador',
            'body' => 'Contenido modificado manualmente desde el panel.',
            'published_at' => $customDate,
        ]);

        // Run seeder
        $this->seed(ProductReleasesSeeder::class);

        // Verify that custom title, body and date are preserved
        $release->refresh();
        $this->assertEquals('Título personalizado por el administrador', $release->title);
        $this->assertEquals('Contenido modificado manualmente desde el panel.', $release->body);
        $this->assertEquals($customDate->timestamp, $release->published_at->timestamp);
    }

    public function test_seeder_handles_multiple_existing_records_without_deleting(): void
    {
        // Create duplicate records
        $r1 = ProductRelease::create([
            'version' => '1.1.0',
            'title' => 'Registro 1',
            'body' => 'Detalle 1',
            'published_at' => now(),
        ]);
        $r2 = ProductRelease::create([
            'version' => '1.1.0',
            'title' => 'Registro 2',
            'body' => 'Detalle 2',
            'published_at' => null,
        ]);

        $this->seed(ProductReleasesSeeder::class);

        // Both records must still exist
        $this->assertEquals(2, ProductRelease::where('version', '1.1.0')->count());
    }

    public function test_api_returns_seeded_release(): void
    {
        Sanctum::actingAs($this->user);

        $this->seed(ProductReleasesSeeder::class);

        $response = $this->getJson('/api/v1/releases');
        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('1.1.0', $data[0]['version']);
        $this->assertEquals('Más flexibilidad al facturar y nuevas opciones para tu negocio', $data[0]['title']);
        $this->assertNotNull($data[0]['published_at']);
    }
}
