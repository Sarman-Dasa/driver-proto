<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompanyTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_belong_to_multiple_companies(): void
    {
        $user = User::factory()->create();
        $company1 = Company::factory()->create(['name' => 'Company One']);
        $company2 = Company::factory()->create(['name' => 'Company Two']);

        $user->companies()->attach($company1->id);
        $user->companies()->attach($company2->id);

        $this->assertTrue($user->belongsToCompany($company1));
        $this->assertTrue($user->belongsToCompany($company2));
        $this->assertCount(2, $user->companies);
    }

    public function test_duplicate_company_membership_is_prevented(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();

        $user->companies()->attach($company->id);

        $this->expectException(QueryException::class);
        $user->companies()->attach($company->id);
    }

    public function test_authenticated_user_can_list_their_companies(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $user->companies()->attach($company->id);

        // Another company not belonging to user
        Company::factory()->create(['name' => 'Foreign Corp']);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/companies');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $company->id);
    }

    public function test_authenticated_user_can_create_a_new_company(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/companies', [
            'name' => 'New Global Workspace',
            'email' => 'workspace@global.test',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'New Global Workspace',
                    'email' => 'workspace@global.test',
                ],
            ]);

        $this->assertDatabaseHas('companies', [
            'name' => 'New Global Workspace',
        ]);

        $this->assertTrue($user->belongsToCompany($response->json('data.id')));
    }

    public function test_authenticated_user_can_switch_company(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $user->companies()->attach($company->id);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/companies/switch', [
            'company_id' => $company->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $company->id,
                ],
            ]);
    }
}
