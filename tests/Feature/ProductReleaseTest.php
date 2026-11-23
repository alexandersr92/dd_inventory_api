<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Organization;
use App\Models\ProductRelease;
use App\Models\User;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductReleaseTest extends TestCase
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

    public function test_guest_cannot_access_admin_releases(): void
    {
        $response = $this->get(route('admin.releases.index'));
        $response->assertRedirect(route('admin.login'));
    }

    public function test_tenant_user_cannot_access_admin_releases(): void
    {
        // Un usuario de organización autenticado vía Sanctum intenta acceder al panel /admin
        $response = $this->actingAs($this->user)->get(route('admin.releases.index'));
        $response->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_view_releases_list(): void
    {
        $admin = Admin::create([
            'name' => 'Super Admin',
            'email' => 'admin@diplebill.test',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);

        $release = ProductRelease::create([
            'version' => '1.5.0',
            'title' => 'Mejoras en facturación',
            'body' => "- Conceptos libres\n- Sesión persistente",
            'published_at' => now(),
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.releases.index'));
        $response->assertOk();
        $response->assertSee('Versiones y novedades');
        $response->assertSee('1.5.0');
        $response->assertSee('Mejoras en facturación');
    }

    public function test_admin_can_create_draft_release(): void
    {
        $admin = Admin::create([
            'name' => 'Super Admin',
            'email' => 'admin2@diplebill.test',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin, 'admin')->post(route('admin.releases.store'), [
            'version' => '1.6.0',
            'title' => 'Nuevo módulo de reportes',
            'body' => "- Reportes avanzados\n- Filtro por fecha",
            'publish_now' => 0,
        ]);

        $response->assertRedirect(route('admin.releases.index'));
        $this->assertDatabaseHas('product_releases', [
            'version' => '1.6.0',
            'title' => 'Nuevo módulo de reportes',
            'published_at' => null,
        ]);
    }

    public function test_admin_can_create_published_release(): void
    {
        $admin = Admin::create([
            'name' => 'Super Admin',
            'email' => 'admin3@diplebill.test',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin, 'admin')->post(route('admin.releases.store'), [
            'version' => '1.7.0',
            'title' => 'Actualización inmediata',
            'body' => "Cambio publicado",
            'publish_now' => 1,
        ]);

        $response->assertRedirect(route('admin.releases.index'));
        $release = ProductRelease::where('version', '1.7.0')->first();
        $this->assertNotNull($release);
        $this->assertNotNull($release->published_at);
    }

    public function test_admin_can_toggle_publish_status(): void
    {
        $admin = Admin::create([
            'name' => 'Super Admin',
            'email' => 'admin4@diplebill.test',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);

        $release = ProductRelease::create([
            'version' => '1.8.0',
            'title' => 'Prueba toggle',
            'body' => "Detalle",
            'published_at' => null,
        ]);

        // Toggle to publish
        $response = $this->actingAs($admin, 'admin')->post(route('admin.releases.toggle', $release->id));
        $response->assertRedirect(route('admin.releases.index'));
        $release->refresh();
        $this->assertNotNull($release->published_at);

        // Toggle to unpublish
        $response = $this->actingAs($admin, 'admin')->post(route('admin.releases.toggle', $release->id));
        $response->assertRedirect(route('admin.releases.index'));
        $release->refresh();
        $this->assertNull($release->published_at);
    }

    public function test_admin_can_update_and_delete_release(): void
    {
        $admin = Admin::create([
            'name' => 'Super Admin',
            'email' => 'admin5@diplebill.test',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);

        $release = ProductRelease::create([
            'version' => '1.9.0',
            'title' => 'Título original',
            'body' => "Cuerpo original",
            'published_at' => now(),
        ]);

        // Update
        $response = $this->actingAs($admin, 'admin')->post(route('admin.releases.update', $release->id), [
            'version' => '1.9.1',
            'title' => 'Título editado',
            'body' => "Cuerpo editado",
        ]);
        $response->assertRedirect(route('admin.releases.index'));
        $release->refresh();
        $this->assertEquals('1.9.1', $release->version);
        $this->assertEquals('Título editado', $release->title);

        // Delete
        $response = $this->actingAs($admin, 'admin')->post(route('admin.releases.destroy', $release->id));
        $response->assertRedirect(route('admin.releases.index'));
        $this->assertDatabaseMissing('product_releases', ['id' => $release->id]);
    }

    public function test_api_guest_cannot_access_releases(): void
    {
        $response = $this->getJson('/api/v1/releases');
        $response->assertUnauthorized();
    }

    public function test_api_returns_only_valid_published_releases_ordered_by_published_at_desc(): void
    {
        Sanctum::actingAs($this->user);

        // 1. Published release in the past
        $past = ProductRelease::create([
            'version' => '1.0.0',
            'title' => 'Versión inicial',
            'body' => "Primer lanzamiento",
            'published_at' => now()->subDays(5),
        ]);

        // 2. Published release more recently
        $recent = ProductRelease::create([
            'version' => '1.1.0',
            'title' => 'Versión reciente',
            'body' => "Segundo lanzamiento",
            'published_at' => now()->subDay(),
        ]);

        // 3. Draft release (published_at is null)
        ProductRelease::create([
            'version' => '1.2.0-draft',
            'title' => 'Borrador que no debe salir',
            'body' => "Solo admin",
            'published_at' => null,
        ]);

        // 4. Future release (published_at > now())
        ProductRelease::create([
            'version' => '2.0.0-future',
            'title' => 'Futura que no debe salir',
            'body' => "Programado",
            'published_at' => now()->addDays(2),
        ]);

        $response = $this->getJson('/api/v1/releases');
        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount(2, $data);

        // First item must be 1.1.0 (recent), second must be 1.0.0 (past)
        $this->assertEquals('1.1.0', $data[0]['version']);
        $this->assertEquals('1.0.0', $data[1]['version']);

        // Verify draft and future are not in response
        $versions = array_column($data, 'version');
        $this->assertNotContains('1.2.0-draft', $versions);
        $this->assertNotContains('2.0.0-future', $versions);
    }
}
