<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Models\Company;
use App\Models\User;
use App\Services\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompanyManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_company_with_owner_and_deterministic_slug(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // 1. Create first company
        $response1 = $this->postJson('/api/v1/companies', [
            'name' => 'Acme Innovations',
            'email' => 'contact@acme.test',
            'phone' => '+1 555-0101',
            'website' => 'https://acme.test',
            'city' => 'San Francisco',
            'currency' => 'USD',
        ]);

        $response1->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Acme Innovations',
                    'slug' => 'acme-innovations',
                    'email' => 'contact@acme.test',
                    'status' => 'active',
                    'is_active' => true,
                    'owner_id' => $user->id,
                    'is_owner' => true,
                ],
            ]);

        $company1 = Company::query()->where('slug', 'acme-innovations')->firstOrFail();
        $this->assertEquals($user->id, $company1->owner_id);
        $this->assertTrue($user->belongsToCompany($company1));
        $this->assertTrue($company1->isOwner($user));

        // 2. Create second company with same name to test deterministic slug
        $response2 = $this->postJson('/api/v1/companies', [
            'name' => 'Acme Innovations',
            'email' => 'contact2@acme.test',
        ]);

        $response2->assertStatus(201)
            ->assertJsonPath('data.slug', 'acme-innovations-2');

        // 3. Create third company with same name
        $response3 = $this->postJson('/api/v1/companies', [
            'name' => 'Acme Innovations',
        ]);

        $response3->assertStatus(201)
            ->assertJsonPath('data.slug', 'acme-innovations-3');
    }

    public function test_company_creation_cannot_override_owner_id_or_status_from_request(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/companies', [
            'name' => 'Secure Holdings',
            'owner_id' => $otherUser->id,
            'status' => 'inactive',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.owner_id', $user->id)
            ->assertJsonPath('data.status', 'active');

        $company = Company::query()->where('name', 'Secure Holdings')->firstOrFail();
        $this->assertEquals($user->id, $company->owner_id);
        $this->assertEquals(CompanyStatus::Active, $company->status);
    }

    public function test_guest_cannot_create_company(): void
    {
        $response = $this->postJson('/api/v1/companies', [
            'name' => 'Unauthorized Company',
        ]);

        $response->assertStatus(401);
    }

    public function test_company_owner_can_update_workspace_profile(): void
    {
        $owner = User::factory()->create();
        $company = Company::factory()->forOwner($owner)->create(['name' => 'Initial Name', 'slug' => 'initial-name']);
        $owner->companies()->attach($company->id);

        Sanctum::actingAs($owner);

        $response = $this->putJson("/api/v1/companies/{$company->id}", [
            'name' => 'Updated Name',
            'email' => 'new@company.test',
            'phone' => '+1 800-555-1234',
            'website' => 'https://updated.test',
            'city' => 'Chicago',
            'currency' => 'EUR',
            'timezone' => 'America/Chicago',
            // Attempt to maliciously inject slug, owner_id, and status
            'slug' => 'tampered-slug',
            'owner_id' => 99999,
            'status' => 'inactive',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Updated Name',
                    'slug' => 'initial-name', // Stable slug not modified
                    'email' => 'new@company.test',
                    'owner_id' => $owner->id,
                    'status' => 'active',
                ],
            ]);

        $company->refresh();
        $this->assertEquals('Updated Name', $company->name);
        $this->assertEquals('initial-name', $company->slug);
        $this->assertEquals($owner->id, $company->owner_id);
        $this->assertEquals(CompanyStatus::Active, $company->status);
    }

    public function test_non_owner_member_cannot_update_company(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $company = Company::factory()->forOwner($owner)->create();
        $owner->companies()->attach($company->id);
        $member->companies()->attach($company->id);

        Sanctum::actingAs($member);

        $response = $this->putJson("/api/v1/companies/{$company->id}", [
            'name' => 'Hacked Company Name',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Only the company owner can update workspace settings.',
            ]);
    }

    public function test_non_member_cannot_update_company(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();

        $company = Company::factory()->forOwner($owner)->create();
        $owner->companies()->attach($company->id);

        Sanctum::actingAs($stranger);

        $response = $this->putJson("/api/v1/companies/{$company->id}", [
            'name' => 'Foreign Update',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'You do not have access to this company.',
            ]);
    }

    public function test_owner_can_deactivate_company(): void
    {
        $owner = User::factory()->create();
        $company = Company::factory()->forOwner($owner)->create();
        $owner->companies()->attach($company->id);

        Sanctum::actingAs($owner);

        $response = $this->postJson("/api/v1/companies/{$company->id}/deactivate");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $company->id,
                    'status' => 'inactive',
                    'is_active' => false,
                ],
            ]);

        $company->refresh();
        $this->assertFalse($company->isActive());
        $this->assertEquals(CompanyStatus::Inactive, $company->status);
    }

    public function test_non_owner_cannot_deactivate_company(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $company = Company::factory()->forOwner($owner)->create();
        $owner->companies()->attach($company->id);
        $member->companies()->attach($company->id);

        Sanctum::actingAs($member);

        $response = $this->postJson("/api/v1/companies/{$company->id}/deactivate");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Only the company owner can deactivate this workspace.',
            ]);
    }

    public function test_inactive_company_blocks_workspace_access_while_retaining_data(): void
    {
        $owner = User::factory()->create();
        $company = Company::factory()->forOwner($owner)->inactive()->create(['name' => 'Dormant Enterprise']);
        $owner->companies()->attach($company->id);

        Sanctum::actingAs($owner);

        // Attempting to access dormant workspace via show
        $response = $this->getJson("/api/v1/companies/{$company->id}");
        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'This company is inactive.',
            ]);

        // Attempting to access dormant workspace via current
        $responseCurrent = $this->withHeader('X-Company-Id', (string) $company->id)
            ->getJson('/api/v1/companies/current');
        $responseCurrent->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'This company is inactive.',
            ]);

        // Data is strictly retained
        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'name' => 'Dormant Enterprise',
            'status' => 'inactive',
        ]);
    }

    public function test_owner_can_reactivate_company(): void
    {
        $owner = User::factory()->create();
        $company = Company::factory()->forOwner($owner)->inactive()->create();
        $owner->companies()->attach($company->id);

        Sanctum::actingAs($owner);

        $response = $this->postJson("/api/v1/companies/{$company->id}/reactivate");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $company->id,
                    'status' => 'active',
                    'is_active' => true,
                ],
            ]);

        $company->refresh();
        $this->assertTrue($company->isActive());
        $this->assertEquals(CompanyStatus::Active, $company->status);

        // Now accessible again
        $accessResponse = $this->getJson("/api/v1/companies/{$company->id}");
        $accessResponse->assertStatus(200);
    }

    public function test_non_owner_cannot_reactivate_company(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $company = Company::factory()->forOwner($owner)->inactive()->create();
        $owner->companies()->attach($company->id);
        $member->companies()->attach($company->id);

        Sanctum::actingAs($member);

        $response = $this->postJson("/api/v1/companies/{$company->id}/reactivate");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Only the company owner can reactivate this workspace.',
            ]);
    }

    public function test_user_can_switch_company_via_route_parameter(): void
    {
        $user = User::factory()->create();
        $company1 = Company::factory()->forOwner($user)->create();
        $company2 = Company::factory()->forOwner($user)->create();

        $user->companies()->attach($company1->id);
        $user->companies()->attach($company2->id);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/companies/{$company2->id}/switch");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $company2->id,
                ],
            ]);
    }

    public function test_switching_to_inactive_company_is_rejected(): void
    {
        $user = User::factory()->create();
        $inactiveCompany = Company::factory()->forOwner($user)->inactive()->create();
        $user->companies()->attach($inactiveCompany->id);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/companies/{$inactiveCompany->id}/switch");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'This company is inactive.',
            ]);
    }

    public function test_switching_to_unrelated_company_is_rejected(): void
    {
        $user = User::factory()->create();
        $foreignCompany = Company::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/companies/{$foreignCompany->id}/switch");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'You do not have access to this company.',
            ]);
    }

    public function test_header_spoofing_cannot_bypass_membership_authorization(): void
    {
        $userA = User::factory()->create();
        $companyA = Company::factory()->forOwner($userA)->create();
        $userA->companies()->attach($companyA->id);

        $userB = User::factory()->create();
        $companyB = Company::factory()->forOwner($userB)->create();
        $userB->companies()->attach($companyB->id);

        Sanctum::actingAs($userA);

        // User A attempts to send X-Company-Id for Company B
        $response = $this->withHeader('X-Company-Id', (string) $companyB->id)
            ->getJson('/api/v1/companies/current');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'You do not have access to this company.',
            ]);
    }

    public function test_company_context_clears_safely(): void
    {
        $context = app(CompanyContext::class);
        $company = Company::factory()->create();

        $context->setCompany($company);
        $this->assertEquals($company->id, $context->id());
        $this->assertTrue($context->hasCompany());

        $context->clear();
        $this->assertNull($context->id());
        $this->assertNull($context->getCompany());
        $this->assertFalse($context->hasCompany());
    }
}
