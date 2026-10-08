<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\VerifyPendingEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_retrieve_profile(): void
    {
        $user = User::factory()->create([
            'name' => 'Jane Doe',
            'email' => 'jane@billflow.test',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/profile');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'name' => 'Jane Doe',
                    'email' => 'jane@billflow.test',
                ],
            ]);
    }

    public function test_unauthenticated_user_cannot_access_profile(): void
    {
        $response = $this->getJson('/api/v1/profile');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_user_can_update_name_without_changing_email(): void
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'keepsame@billflow.test',
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson('/api/v1/profile', [
            'name' => 'Updated Name',
            'email' => 'keepsame@billflow.test',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Profile updated successfully.',
                'data' => [
                    'name' => 'Updated Name',
                    'email' => 'keepsame@billflow.test',
                    'pending_email' => null,
                ],
            ]);

        $user->refresh();
        $this->assertSame('Updated Name', $user->name);
        $this->assertSame('keepsame@billflow.test', $user->email);
        $this->assertNull($user->pending_email);
    }

    public function test_updating_email_sets_pending_email_and_sends_verification_without_changing_active_email(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'name' => 'Dev User',
            'email' => 'old@billflow.test',
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson('/api/v1/profile', [
            'name' => 'Dev User',
            'email' => 'new@billflow.test',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'email' => 'old@billflow.test',
                    'pending_email' => 'new@billflow.test',
                ],
            ]);

        $user->refresh();
        // Crucial security check: old email remains active!
        $this->assertSame('old@billflow.test', $user->email);
        $this->assertSame('new@billflow.test', $user->pending_email);

        // Verification notification sent
        Notification::assertSentOnDemand(
            VerifyPendingEmailNotification::class,
            function (VerifyPendingEmailNotification $notification, array $channels, object $notifiable) {
                return $notifiable->routes['mail'] === 'new@billflow.test';
            },
        );
    }

    public function test_user_cannot_update_email_to_an_already_taken_email(): void
    {
        User::factory()->create(['email' => 'existing@billflow.test']);
        $user = User::factory()->create(['email' => 'myemail@billflow.test']);

        Sanctum::actingAs($user);

        $response = $this->putJson('/api/v1/profile', [
            'name' => 'My Name',
            'email' => 'existing@billflow.test',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_user_can_verify_pending_email_change_and_activate_new_email(): void
    {
        $user = User::factory()->create([
            'email' => 'original@billflow.test',
            'pending_email' => 'activated@billflow.test',
        ]);

        Sanctum::actingAs($user);

        $hash = sha1('activated@billflow.test');

        $response = $this->postJson('/api/v1/profile/verify-email-change', [
            'hash' => $hash,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'email' => 'activated@billflow.test',
                    'pending_email' => null,
                ],
            ]);

        $user->refresh();
        $this->assertSame('activated@billflow.test', $user->email);
        $this->assertNull($user->pending_email);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_pending_email_verification_fails_with_invalid_hash(): void
    {
        $user = User::factory()->create([
            'email' => 'original@billflow.test',
            'pending_email' => 'activated@billflow.test',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/profile/verify-email-change', [
            'hash' => 'invalid-hash-string',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        $user->refresh();
        $this->assertSame('original@billflow.test', $user->email);
        $this->assertSame('activated@billflow.test', $user->pending_email);
    }

    public function test_user_can_cancel_pending_email_change(): void
    {
        $user = User::factory()->create([
            'email' => 'original@billflow.test',
            'pending_email' => 'unwanted@billflow.test',
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/v1/profile/pending-email');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'email' => 'original@billflow.test',
                    'pending_email' => null,
                ],
            ]);

        $user->refresh();
        $this->assertNull($user->pending_email);
    }
}
