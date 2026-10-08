<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompanyAccessSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_api_request_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/companies');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_unauthorized_user_cannot_access_another_companys_data(): void
    {
        $user = User::factory()->create();
        $otherUserCompany = Company::factory()->create();

        Sanctum::actingAs($user);

        // Attempt to access company the user doesn't belong to
        $response = $this->getJson("/api/v1/companies/{$otherUserCompany->id}");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'You do not have access to this company.',
            ]);
    }

    public function test_inactive_company_cannot_be_accessed(): void
    {
        $user = User::factory()->create();
        $inactiveCompany = Company::factory()->inactive()->create();
        $user->companies()->attach($inactiveCompany->id);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/companies/{$inactiveCompany->id}");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'This company is inactive.',
            ]);
    }

    public function test_authorized_user_can_access_their_active_company(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $user->companies()->attach($company->id);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/companies/{$company->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $company->id,
                    'is_active' => true,
                ],
            ]);
    }

    public function test_company_access_via_header_resolves_and_validates(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $user->companies()->attach($company->id);

        Sanctum::actingAs($user);

        $response = $this->withHeader('X-Company-Id', (string) $company->id)
            ->getJson('/api/v1/companies/current');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $company->id,
                ],
            ]);
    }

    public function test_invalid_company_id_in_header_is_rejected(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $user->companies()->attach($company->id);

        Sanctum::actingAs($user);

        $response = $this->withHeader('X-Company-Id', '999999')
            ->getJson('/api/v1/companies/current');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Company not found.',
            ]);
    }
}
