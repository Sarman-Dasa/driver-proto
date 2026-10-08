<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoleAndPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_standard_system_roles_and_permissions_are_seeded(): void
    {
        $this->assertDatabaseHas('roles', ['slug' => Role::OWNER, 'name' => 'Owner', 'is_system' => true]);
        $this->assertDatabaseHas('roles', ['slug' => Role::ADMIN, 'name' => 'Admin', 'is_system' => true]);
        $this->assertDatabaseHas('roles', ['slug' => Role::ACCOUNTANT, 'name' => 'Accountant', 'is_system' => true]);
        $this->assertDatabaseHas('roles', ['slug' => Role::MEMBER, 'name' => 'Member', 'is_system' => true]);

        $ownerRole = Role::where('slug', Role::OWNER)->firstOrFail();
        $adminRole = Role::where('slug', Role::ADMIN)->firstOrFail();
        $accountantRole = Role::where('slug', Role::ACCOUNTANT)->firstOrFail();
        $memberRole = Role::where('slug', Role::MEMBER)->firstOrFail();

        // Owner has full catalog permissions
        $this->assertTrue($ownerRole->hasPermission('company.update'));
        $this->assertTrue($ownerRole->hasPermission('company.deactivate'));
        $this->assertTrue($ownerRole->hasPermission('billing.manage'));
        $this->assertTrue($ownerRole->hasPermission('customers.create'));

        // Admin has operational permissions, but NOT owner-only lifecycle or ownership management
        $this->assertTrue($adminRole->hasPermission('customers.create'));
        $this->assertTrue($adminRole->hasPermission('team.manage'));
        $this->assertFalse($adminRole->hasPermission('company.deactivate'));
        $this->assertFalse($adminRole->hasPermission('billing.manage'));

        // Accountant has financial permissions
        $this->assertTrue($accountantRole->hasPermission('invoices.create'));
        $this->assertTrue($accountantRole->hasPermission('expenses.create'));
        $this->assertTrue($accountantRole->hasPermission('reports.view'));
        $this->assertFalse($accountantRole->hasPermission('team.manage'));
        $this->assertFalse($accountantRole->hasPermission('customers.create'));

        // Member has basic permissions
        $this->assertTrue($memberRole->hasPermission('invoices.create'));
        $this->assertFalse($memberRole->hasPermission('expenses.delete'));
        $this->assertFalse($memberRole->hasPermission('team.manage'));
    }

    public function test_newly_created_company_assigns_owner_role_to_creator(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/companies', [
            'name' => 'Vanguard Technologies',
        ]);

        $response->assertStatus(201);
        $companyId = $response->json('data.id');
        $company = Company::findOrFail($companyId);

        $this->assertTrue($company->isOwner($user));
        $this->assertEquals(Role::OWNER, $user->roleInCompany($company)?->slug);
        $this->assertTrue($user->hasRoleInCompany($company, Role::OWNER));
        $this->assertTrue($user->hasPermissionInCompany($company, 'company.update'));
        $this->assertTrue($user->hasPermissionInCompany($company, 'billing.manage'));
    }

    public function test_multi_company_role_and_permission_isolation(): void
    {
        $user = User::factory()->create();

        // Company A: user is Admin
        $companyA = Company::factory()->create(['name' => 'Company A']);
        $adminRole = Role::where('slug', Role::ADMIN)->firstOrFail();
        $user->companies()->attach($companyA->id, ['role_id' => $adminRole->id]);

        // Company B: user is Accountant
        $companyB = Company::factory()->create(['name' => 'Company B']);
        $accountantRole = Role::where('slug', Role::ACCOUNTANT)->firstOrFail();
        $user->companies()->attach($companyB->id, ['role_id' => $accountantRole->id]);

        // Verify roles are distinct per company
        $this->assertEquals(Role::ADMIN, $user->roleInCompany($companyA)?->slug);
        $this->assertEquals(Role::ACCOUNTANT, $user->roleInCompany($companyB)?->slug);

        // In Company A (Admin): customers.create is ALLOWED
        $this->assertTrue($user->hasPermissionInCompany($companyA, 'customers.create'));

        // In Company B (Accountant): customers.create is DENIED
        $this->assertFalse($user->hasPermissionInCompany($companyB, 'customers.create'));

        // In Company B (Accountant): invoices.create is ALLOWED
        $this->assertTrue($user->hasPermissionInCompany($companyB, 'invoices.create'));

        // In Company A: team.manage is ALLOWED; In Company B: team.manage is DENIED
        $this->assertTrue($user->hasPermissionInCompany($companyA, 'team.manage'));
        $this->assertFalse($user->hasPermissionInCompany($companyB, 'team.manage'));
    }

    public function test_authenticated_user_can_retrieve_workspace_authorization(): void
    {
        $owner = User::factory()->create();
        $company = Company::factory()->forOwner($owner)->create();
        $ownerRole = Role::where('slug', Role::OWNER)->firstOrFail();
        $owner->companies()->attach($company->id, ['role_id' => $ownerRole->id]);

        Sanctum::actingAs($owner);

        $response = $this->getJson("/api/v1/companies/{$company->id}/authorization");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'role' => [
                        'slug' => Role::OWNER,
                        'name' => 'Owner',
                    ],
                    'is_owner' => true,
                ],
            ])
            ->assertJsonPath('data.permissions.0', 'company.view');
    }

    public function test_workspace_authorization_is_denied_for_unauthorized_company(): void
    {
        $user = User::factory()->create();
        $otherCompany = Company::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/companies/{$otherCompany->id}/authorization");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'You do not have access to this company.',
            ]);
    }

    public function test_owner_can_assign_role_to_company_member(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $company = Company::factory()->forOwner($owner)->create();

        $ownerRole = Role::where('slug', Role::OWNER)->firstOrFail();
        $memberRole = Role::where('slug', Role::MEMBER)->firstOrFail();
        $accountantRole = Role::where('slug', Role::ACCOUNTANT)->firstOrFail();

        $owner->companies()->attach($company->id, ['role_id' => $ownerRole->id]);
        $member->companies()->attach($company->id, ['role_id' => $memberRole->id]);

        Sanctum::actingAs($owner);

        $response = $this->postJson("/api/v1/companies/{$company->id}/members/{$member->id}/role", [
            'role_id' => $accountantRole->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $member->id,
                    'role' => [
                        'slug' => Role::ACCOUNTANT,
                        'name' => 'Accountant',
                    ],
                ],
            ]);

        $this->assertEquals(Role::ACCOUNTANT, $member->roleInCompany($company)?->slug);
    }

    public function test_member_without_roles_manage_permission_cannot_assign_roles(): void
    {
        $owner = User::factory()->create();
        $accountant = User::factory()->create();
        $member = User::factory()->create();
        $company = Company::factory()->forOwner($owner)->create();

        $ownerRole = Role::where('slug', Role::OWNER)->firstOrFail();
        $accountantRole = Role::where('slug', Role::ACCOUNTANT)->firstOrFail();
        $memberRole = Role::where('slug', Role::MEMBER)->firstOrFail();
        $adminRole = Role::where('slug', Role::ADMIN)->firstOrFail();

        $owner->companies()->attach($company->id, ['role_id' => $ownerRole->id]);
        $accountant->companies()->attach($company->id, ['role_id' => $accountantRole->id]);
        $member->companies()->attach($company->id, ['role_id' => $memberRole->id]);

        Sanctum::actingAs($accountant);

        $response = $this->postJson("/api/v1/companies/{$company->id}/members/{$member->id}/role", [
            'role_id' => $adminRole->id,
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'You do not have permission to manage roles in this workspace.',
            ]);
    }

    public function test_company_owner_cannot_be_downgraded_or_modified(): void
    {
        $owner = User::factory()->create();
        $company = Company::factory()->forOwner($owner)->create();
        $ownerRole = Role::where('slug', Role::OWNER)->firstOrFail();
        $memberRole = Role::where('slug', Role::MEMBER)->firstOrFail();

        $owner->companies()->attach($company->id, ['role_id' => $ownerRole->id]);

        Sanctum::actingAs($owner);

        // Attempting to downgrade owner to member
        $response = $this->postJson("/api/v1/companies/{$company->id}/members/{$owner->id}/role", [
            'role_id' => $memberRole->id,
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'The workspace owner role cannot be modified or downgraded.',
            ]);

        $this->assertEquals(Role::OWNER, $owner->roleInCompany($company)?->slug);
    }

    public function test_owner_role_cannot_be_assigned_to_standard_member(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $company = Company::factory()->forOwner($owner)->create();

        $ownerRole = Role::where('slug', Role::OWNER)->firstOrFail();
        $memberRole = Role::where('slug', Role::MEMBER)->firstOrFail();

        $owner->companies()->attach($company->id, ['role_id' => $ownerRole->id]);
        $member->companies()->attach($company->id, ['role_id' => $memberRole->id]);

        Sanctum::actingAs($owner);

        // Attempting to assign owner role to non-owner member
        $response = $this->postJson("/api/v1/companies/{$company->id}/members/{$member->id}/role", [
            'role_id' => $ownerRole->id,
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'The owner role cannot be assigned through standard role management.',
            ]);
    }

    public function test_cannot_assign_role_to_non_member_of_company(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $company = Company::factory()->forOwner($owner)->create();

        $ownerRole = Role::where('slug', Role::OWNER)->firstOrFail();
        $adminRole = Role::where('slug', Role::ADMIN)->firstOrFail();

        $owner->companies()->attach($company->id, ['role_id' => $ownerRole->id]);

        Sanctum::actingAs($owner);

        $response = $this->postJson("/api/v1/companies/{$company->id}/members/{$stranger->id}/role", [
            'role_id' => $adminRole->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['member']);
    }

    public function test_authenticated_user_can_list_company_roles_and_members(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $company = Company::factory()->forOwner($owner)->create();

        $ownerRole = Role::where('slug', Role::OWNER)->firstOrFail();
        $memberRole = Role::where('slug', Role::MEMBER)->firstOrFail();

        $owner->companies()->attach($company->id, ['role_id' => $ownerRole->id]);
        $member->companies()->attach($company->id, ['role_id' => $memberRole->id]);

        Sanctum::actingAs($owner);

        // 1. List roles
        $rolesResponse = $this->getJson("/api/v1/companies/{$company->id}/roles");
        $rolesResponse->assertStatus(200)
            ->assertJsonCount(4, 'data');

        // 2. List members
        $membersResponse = $this->getJson("/api/v1/companies/{$company->id}/members");
        $membersResponse->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }
}
