<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use App\Mail\PasswordResetCodeMail;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class PasswordResetAndForcedChangeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        RateLimiter::clear('forgot-password:*');
        RateLimiter::clear('reset-password-attempt:*');
    }

    /**
     * Test que valida que un usuario creado por administrador tenga must_change_password en true por defecto.
     */
    public function test_admin_created_user_has_must_change_password_enabled(): void
    {
        $owner = User::factory()->create(['must_change_password' => false]);
        $org = Organization::factory()->create(['owner_id' => $owner->id]);
        $owner->update(['organization_id' => $org->id]);

        $this->actingAs($owner, 'sanctum');

        $response = $this->postJson('/api/v1/users', [
            'name' => 'Nuevo Empleado',
            'email' => 'empleado@test.com',
            'password' => 'password123',
            'role_id' => null,
            'stores' => [],
        ]);

        $response->assertStatus(201);
        $this->assertTrue((bool)$response->json('must_change_password'));

        // Verificar en base de datos
        $user = User::where('email', 'empleado@test.com')->first();
        $this->assertTrue((bool)$user->must_change_password);
    }

    /**
     * Test que valida que el registro público setea must_change_password en false.
     */
    public function test_publicly_registered_user_has_must_change_password_disabled(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'name' => 'Owner Publico',
            'email' => 'owner_public@test.com',
            'password' => 'password123',
            'password_confirm' => 'password123',
            'device_name' => 'TestDevice',
        ]);

        $response->assertStatus(201);
        
        $user = User::where('email', 'owner_public@test.com')->first();
        $this->assertFalse((bool)$user->must_change_password);
    }

    /**
     * Test que valida que al cambiar la contraseña se limpie must_change_password a false.
     */
    public function test_changing_password_removes_must_change_password_flag(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old_password'),
            'must_change_password' => true
        ]);

        $this->actingAs($user, 'sanctum');

        $response = $this->putJson('/api/v1/user/password', [
            'current_password' => 'old_password',
            'password' => 'new_password123',
            'password_confirmation' => 'new_password123',
        ]);

        $response->assertStatus(200);
        $user->refresh();
        $this->assertFalse((bool)$user->must_change_password);
    }

    /**
     * Test que valida el flujo de solicitar código de recuperación de contraseña.
     */
    public function test_user_can_request_password_reset_code(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'user_reset@test.com',
            'name' => 'Juan Reset'
        ]);

        $response = $this->postJson('/api/v1/forgot-password', [
            'email' => 'user_reset@test.com'
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['message']);

        // Verificar que se insertó el token hasheado en la base de datos central
        $tokenRecord = DB::connection('central')
            ->table('password_reset_tokens')
            ->where('email', 'user_reset@test.com')
            ->first();
            
        $this->assertNotNull($tokenRecord);
        $this->assertNotNull($tokenRecord->token);

        // Verificar que el correo electrónico se envió
        Mail::assertSent(PasswordResetCodeMail::class, function ($mail) use ($user) {
            return $mail->hasTo('user_reset@test.com') && 
                   $mail->name === $user->name && 
                   strlen($mail->code) === 6;
        });
    }

    /**
     * Test que valida el restablecimiento exitoso con código.
     */
    public function test_user_can_reset_password_with_valid_code(): void
    {
        $user = User::factory()->create([
            'email' => 'user_reset2@test.com',
            'must_change_password' => true
        ]);

        $code = '123456';

        // Insertar el token hasheado
        DB::connection('central')
            ->table('password_reset_tokens')
            ->insert([
                'email' => 'user_reset2@test.com',
                'token' => Hash::make($code),
                'created_at' => now(),
            ]);

        $response = $this->postJson('/api/v1/reset-password', [
            'email' => 'user_reset2@test.com',
            'code' => $code,
            'password' => 'new_password123',
            'password_confirmation' => 'new_password123'
        ]);

        $response->assertStatus(200);
        
        // Verificar que se haya limpiado el token y actualizado la contraseña
        $tokenExists = DB::connection('central')
            ->table('password_reset_tokens')
            ->where('email', 'user_reset2@test.com')
            ->exists();
        $this->assertFalse($tokenExists);

        $user->refresh();
        $this->assertTrue(Hash::check('new_password123', $user->password));
        $this->assertFalse((bool)$user->must_change_password);
    }
}
