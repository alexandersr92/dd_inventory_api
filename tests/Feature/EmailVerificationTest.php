<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * El registro debe enviar un correo de verificación y ofrecer un enlace firmado
 * que marque el correo como verificado. Antes, registerOwner creaba la cuenta y
 * entregaba el token sin ninguna verificación: cualquier correo con typo o falso
 * quedaba como cuenta válida, sin recuperación ni avisos de licencia posibles.
 */
class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    // central no se revierte con RefreshDatabase (otra conexión): usar correos
    // únicos por corrida para no colisionar con el unique de users.
    private function uniqueEmail(): string
    {
        return 'verify_' . uniqid() . '@example.test';
    }

    public function test_registration_sends_verification_email_and_user_starts_unverified(): void
    {
        Notification::fake();
        $email = $this->uniqueEmail();

        $response = $this->postJson('/api/v1/register', [
            'name' => 'Nuevo Dueño',
            'email' => $email,
            'password' => 'secret123',
            'password_confirm' => 'secret123',
            'device_name' => 'test-device',
        ]);

        $response->assertStatus(201);

        $user = User::where('email', $email)->first();
        $this->assertNotNull($user);
        $this->assertFalse($user->hasVerifiedEmail());

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_signed_link_marks_email_as_verified(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);
        $this->assertFalse($user->hasVerifiedEmail());

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ]);

        $this->get($url)->assertOk();

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_tampered_hash_is_rejected_and_user_stays_unverified(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1('otro-correo@example.test'), // hash que no corresponde
        ]);

        $this->get($url)->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_unsigned_link_is_rejected(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        // Sin firma válida el middleware 'signed' rechaza (403).
        $this->get("/email/verify/{$user->id}/" . sha1($user->getEmailForVerification()))
            ->assertForbidden();
    }

    public function test_resend_requires_authentication(): void
    {
        $this->postJson('/api/v1/email/verification-notification')->assertUnauthorized();
    }

    public function test_authenticated_user_can_resend_verification(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email_verified_at' => null]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/email/verification-notification')->assertOk();

        Notification::assertSentTo($user, VerifyEmail::class);
    }
}
