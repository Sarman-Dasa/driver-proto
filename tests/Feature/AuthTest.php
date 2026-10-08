<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_workspace_is_created(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Alice Developer',
            'email' => 'alice@billflow.test',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'company_name' => 'Alice Innovations',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Registration successful.',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => ['id', 'name', 'email'],
                    'token',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'alice@billflow.test',
        ]);

        $this->assertDatabaseHas('companies', [
            'name' => 'Alice Innovations',
        ]);

        $user = User::query()->where('email', 'alice@billflow.test')->firstOrFail();
        $this->assertCount(1, $user->companies);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'bob@billflow.test',
            'password' => 'Password123!',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'bob@billflow.test',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Login successful.',
            ])
            ->assertJsonStructure([
                'data' => [
                    'user' => ['id', 'name', 'email'],
                    'token',
                ],
            ]);
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        User::factory()->create([
            'email' => 'charlie@billflow.test',
            'password' => 'Password123!',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'charlie@billflow.test',
            'password' => 'WrongPassword!',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ])
            ->assertJsonValidationErrors(['email']);
    }

    public function test_authenticated_user_can_access_me_endpoint(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'email' => $user->email,
                ],
            ]);
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Logged out successfully.',
            ]);
    }

    public function test_registration_validation_fails_with_invalid_data(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => '',
            'email' => 'not-an-email',
            'password' => 'short',
            'password_confirmation' => 'mismatch',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }
}
