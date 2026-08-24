<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use App\Models\User;
use App\Models\Organization;
use App\Models\Store;
use App\Models\Seller;
use App\Models\TerminalMagicLink;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

class TerminalMagicLinkTest extends TestCase
{
    use DatabaseTransactions;

    public function test_owner_can_generate_terminal_magic_link(): void
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create(['owner_id' => $user->id]);
        $user->update(['organization_id' => $org->id]);
        $this->setupTenantUser($user, $org);

        $store = Store::factory()->create(['organization_id' => $org->id]);
        $seller = Seller::factory()->create([
            'organization_id' => $org->id,
            'code' => 'V01',
            'pin_hash' => Hash::make('1234'),
            'status' => 'active'
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/terminals/magic-links', [
            'store_id' => $store->id,
            'seller_id' => $seller->id,
            'expires_in_minutes' => 30,
            'device_name' => 'Caja Mostrador Norte'
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'token',
            'path',
            'expires_at',
            'expires_in_minutes',
            'store' => ['id', 'name'],
            'seller' => ['id', 'name', 'code'],
            'device_name'
        ]);

        $token = $response->json('token');
        $this->assertNotEmpty($token);

        // Check in database that token_hash matches
        $this->assertDatabaseHas('terminal_magic_links', [
            'token_hash' => hash('sha256', $token),
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'store_id' => $store->id,
            'seller_id' => $seller->id,
            'used_at' => null
        ]);
    }

    public function test_can_claim_valid_magic_link_only_once(): void
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create(['owner_id' => $user->id]);
        $user->update(['organization_id' => $org->id]);
        $this->setupTenantUser($user, $org);

        $store = Store::factory()->create(['organization_id' => $org->id]);
        $seller = Seller::factory()->create([
            'organization_id' => $org->id,
            'code' => 'V01',
            'pin_hash' => Hash::make('1234'),
            'status' => 'active'
        ]);

        Sanctum::actingAs($user);

        $generateRes = $this->postJson('/api/v1/terminals/magic-links', [
            'store_id' => $store->id,
            'seller_id' => $seller->id,
            'expires_in_minutes' => 30
        ]);

        $token = $generateRes->json('token');

        // Claim as unauthenticated terminal device
        $this->app['auth']->forgetGuards();

        $claimRes = $this->postJson('/api/v1/terminals/claim-link', [
            'token' => $token,
            'device_name' => 'Tablet Samsung'
        ]);

        $claimRes->assertStatus(200);
        $claimRes->assertJsonStructure([
            'attributes' => [
                'id',
                'name',
                'email',
                'organization_id',
                'roles',
            ],
            'token',
            'store_id',
            'seller' => ['id', 'name', 'code']
        ]);

        $sanctumToken = $claimRes->json('token');
        $this->assertNotEmpty($sanctumToken);

        // Attempt to claim a second time (MUST FAIL with 422)
        $secondClaimRes = $this->postJson('/api/v1/terminals/claim-link', [
            'token' => $token,
            'device_name' => 'Another PC'
        ]);

        $secondClaimRes->assertStatus(422);
        $secondClaimRes->assertJson([
            'message' => 'El enlace es inválido, ya fue utilizado o ha expirado.'
        ]);
    }

    public function test_cannot_claim_expired_magic_link(): void
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create(['owner_id' => $user->id]);
        $user->update(['organization_id' => $org->id]);

        $plainToken = Str::random(64);
        TerminalMagicLink::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->subMinutes(5) // Expired!
        ]);

        $claimRes = $this->postJson('/api/v1/terminals/claim-link', [
            'token' => $plainToken
        ]);

        $claimRes->assertStatus(422);
        $claimRes->assertJson([
            'message' => 'El enlace es inválido, ya fue utilizado o ha expirado.'
        ]);
    }
}
