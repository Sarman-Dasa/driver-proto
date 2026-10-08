<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PasswordManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_change_password_with_correct_current_password(): void
    {
        $user = User::factory()->create([
            'password' => 'OldPassword123!',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/auth/change-password', [
            'current_password' => 'OldPassword123!',
            'password' => 'NewPassword456!',
            'password_confirmation' => 'NewPassword456!',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Password changed successfully.',
            ]);

        $user->refresh();
        $this->assertTrue(Hash::check('NewPassword456!', $user->password));
    }

    public function test_user_cannot_change_password_with_incorrect_current_password(): void
    {
        $user = User::factory()->create([
            'password' => 'OldPassword123!',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/auth/change-password', [
            'current_password' => 'WrongCurrentPassword!',
            'password' => 'NewPassword456!',
            'password_confirmation' => 'NewPassword456!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);

        $user->refresh();
        $this->assertTrue(Hash::check('OldPassword123!', $user->password));
    }

    public function test_password_change_validates_new_password_strength_and_confirmation(): void
    {
        $user = User::factory()->create([
            'password' => 'OldPassword123!',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/auth/change-password', [
            'current_password' => 'OldPassword123!',
            'password' => 'short',
            'password_confirmation' => 'mismatch',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_password_change_revokes_other_active_tokens(): void
    {
        $user = User::factory()->create([
            'password' => 'OldPassword123!',
        ]);

        // Create multiple tokens
        $otherToken = $user->createToken('other-device');
        $currentToken = $user->createToken('current-device');

        $response = $this->withHeader('Authorization', 'Bearer '.$currentToken->plainTextToken)
            ->postJson('/api/v1/auth/change-password', [
                'current_password' => 'OldPassword123!',
                'password' => 'NewPassword456!',
                'password_confirmation' => 'NewPassword456!',
            ]);

        $response->assertStatus(200);

        // Other token must be deleted
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $otherToken->accessToken->id,
        ]);

        // Current token must remain valid
        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $currentToken->accessToken->id,
        ]);
    }

    public function test_forgot_password_does_not_reveal_if_user_exists_account_enumeration_protection(): void
    {
        // Request for a non-existent email
        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'nonexistent@billflow.test',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'If an account exists with that email address, a password reset link has been sent.',
            ]);

        // Request for an existing email
        $user = User::factory()->create(['email' => 'realuser@billflow.test']);
        $response2 = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'realuser@billflow.test',
        ]);

        $response2->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'If an account exists with that email address, a password reset link has been sent.',
            ]);
    }

    public function test_user_can_reset_password_with_valid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'resetme@billflow.test',
            'password' => 'OldPassword123!',
        ]);

        $token = Password::createToken($user);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => 'resetme@billflow.test',
            'password' => 'NewStrongPassword789!',
            'password_confirmation' => 'NewStrongPassword789!',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $user->refresh();
        $this->assertTrue(Hash::check('NewStrongPassword789!', $user->password));
    }

    public function test_password_reset_fails_with_invalid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'resetme@billflow.test',
        ]);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token' => 'invalid-token-value',
            'email' => 'resetme@billflow.test',
            'password' => 'NewStrongPassword789!',
            'password_confirmation' => 'NewStrongPassword789!',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_password_reset_invalidates_all_active_tokens(): void
    {
        $user = User::factory()->create([
            'email' => 'resetme@billflow.test',
        ]);

        $user->createToken('token1');
        $user->createToken('token2');
        $this->assertCount(2, $user->tokens);

        $token = Password::createToken($user);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => 'resetme@billflow.test',
            'password' => 'NewStrongPassword789!',
            'password_confirmation' => 'NewStrongPassword789!',
        ]);

        $response->assertStatus(200);

        $user->refresh();
        $this->assertCount(0, $user->tokens);
    }
}
