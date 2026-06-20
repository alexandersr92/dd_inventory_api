<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class PasswordUpdateTest extends TestCase
{
    use DatabaseTransactions;

    public function test_user_can_update_password_with_correct_current_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password')
        ]);

        \Laravel\Sanctum\Sanctum::actingAs($user);

        $response = $this->putJson('/api/v1/user/password', [
            'current_password' => 'old-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Contraseña actualizada correctamente.'
        ]);

        $user->refresh();
        $this->assertTrue(Hash::check('new-password-123', $user->password));
    }

    public function test_user_cannot_update_password_with_incorrect_current_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password')
        ]);

        \Laravel\Sanctum\Sanctum::actingAs($user);

        $response = $this->putJson('/api/v1/user/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'La contraseña actual no es correcta.'
        ]);

        $user->refresh();
        $this->assertTrue(Hash::check('old-password', $user->password));
    }

    public function test_password_update_requires_matching_confirmation(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password')
        ]);

        \Laravel\Sanctum\Sanctum::actingAs($user);

        $response = $this->putJson('/api/v1/user/password', [
            'current_password' => 'old-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'different-password',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    public function test_password_update_requires_minimum_length(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password')
        ]);

        \Laravel\Sanctum\Sanctum::actingAs($user);

        $response = $this->putJson('/api/v1/user/password', [
            'current_password' => 'old-password',
            'password' => '12345',
            'password_confirmation' => '12345',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }
}
