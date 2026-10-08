<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthenticationHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_responses_never_expose_password_remember_token_or_secrets(): void
    {
        $user = User::factory()->create([
            'email' => 'sensitive@billflow.test',
            'remember_token' => 'secret_token_value',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(200);

        $json = $response->json();
        $this->assertArrayNotHasKey('password', $json['data']);
        $this->assertArrayNotHasKey('remember_token', $json['data']);
        $this->assertArrayNotHasKey('password_confirmation', $json['data']);
    }

    public function test_login_rate_limiting_throttles_repeated_attempts(): void
    {
        $user = User::factory()->create([
            'email' => 'ratelimit@billflow.test',
            'password' => 'CorrectPassword123!',
        ]);

        // Rate limit for auth.login is 5 attempts per minute
        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson('/api/v1/auth/login', [
                'email' => 'ratelimit@billflow.test',
                'password' => 'WrongPassword!',
            ]);
            $response->assertStatus(422);
        }

        // 6th attempt must be throttled with HTTP 429
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'ratelimit@billflow.test',
            'password' => 'WrongPassword!',
        ]);

        $response->assertStatus(429)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_password_reset_rate_limiting_throttles_repeated_requests(): void
    {
        // Rate limit for auth.password-reset is 5 attempts per minute
        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson('/api/v1/auth/forgot-password', [
                'email' => 'test@billflow.test',
            ]);
            $response->assertStatus(200);
        }

        // 6th attempt must be throttled with HTTP 429
        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'test@billflow.test',
        ]);

        $response->assertStatus(429);
    }

    public function test_unauthorized_user_cannot_verify_another_users_email_email_verification_bypass(): void
    {
        $victim = User::factory()->unverified()->create();
        $attacker = User::factory()->create();

        Sanctum::actingAs($attacker);

        $response = $this->postJson('/api/v1/auth/verify-email', [
            'id' => $victim->id,
            'hash' => sha1($victim->getEmailForVerification()),
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'You are not authorized to verify this account.',
            ]);

        $victim->refresh();
        $this->assertFalse($victim->hasVerifiedEmail());
    }

    public function test_expired_email_verification_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->postJson('/api/v1/auth/verify-email', [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
            'expires' => now()->subMinute()->timestamp,
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'The verification link has expired.',
            ]);

        $user->refresh();
        $this->assertFalse($user->hasVerifiedEmail());
    }

    public function test_protected_endpoints_are_inaccessible_after_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        // Accessible before logout
        $responseBefore = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me');
        $responseBefore->assertStatus(200);

        // Perform logout
        $logoutResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/logout');
        $logoutResponse->assertStatus(200);

        $this->assertCount(0, $user->fresh()->tokens);

        // Reset in-memory guard cache
        $this->app['auth']->forgetGuards();

        // Inaccessible after logout with the revoked token
        $responseAfter = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me');
        $responseAfter->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_email_verification_can_be_resent_for_unverified_user(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/auth/email/resend');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Verification link sent!',
            ]);
    }
}
