<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Company\CreateCompanyAction;
use App\Enums\CompanyStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Company;
use App\Models\Feature;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use App\Services\FeatureAccessService;
use App\Services\UsageLimitService;
use Database\Seeders\PlanAndFeatureSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanAndFeatureTest extends TestCase
{
    use RefreshDatabase;

    private FeatureAccessService $featureAccessService;

    private UsageLimitService $usageLimitService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(PlanAndFeatureSeeder::class);

        $this->featureAccessService = app(FeatureAccessService::class);
        $this->usageLimitService = app(UsageLimitService::class);
    }

    public function test_plans_and_features_catalog_seeded_correctly(): void
    {
        $this->assertDatabaseHas('plans', ['slug' => 'free', 'is_default' => true, 'price_monthly' => 0]);
        $this->assertDatabaseHas('plans', ['slug' => 'pro', 'is_default' => false, 'price_monthly' => 1900]);
        $this->assertDatabaseHas('plans', ['slug' => 'business', 'is_default' => false, 'price_monthly' => 4900]);

        $this->assertDatabaseCount('features', 11);
        $this->assertDatabaseHas('features', ['key' => Feature::CUSTOMERS]);
        $this->assertDatabaseHas('features', ['key' => Feature::INVOICES]);
        $this->assertDatabaseHas('features', ['key' => Feature::EXPENSES]);
        $this->assertDatabaseHas('features', ['key' => Feature::ONLINE_PAYMENTS]);
        $this->assertDatabaseHas('features', ['key' => Feature::STRIPE_CONNECT]);
        $this->assertDatabaseHas('features', ['key' => Feature::REPORTS_BASIC]);
        $this->assertDatabaseHas('features', ['key' => Feature::REPORTS_ADVANCED]);
        $this->assertDatabaseHas('features', ['key' => Feature::TEAM_MEMBERS]);
        $this->assertDatabaseHas('features', ['key' => Feature::API_ACCESS]);
        $this->assertDatabaseHas('features', ['key' => Feature::MULTIPLE_COMPANIES]);
        $this->assertDatabaseHas('features', ['key' => Feature::PAYMENT_REMINDERS]);
    }

    public function test_newly_created_company_automatically_receives_free_plan_and_active_subscription(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        /** @var CreateCompanyAction $action */
        $action = app(CreateCompanyAction::class);
        $company = $action->execute($user, ['name' => 'Auto Subscribed LLC']);

        $subscription = $company->currentSubscription();

        $this->assertNotNull($subscription);
        $this->assertEquals(SubscriptionStatus::Active, $subscription->status);
        $this->assertEquals(Plan::FREE, $subscription->plan->slug);
        $this->assertTrue($subscription->isActive());
    }

    public function test_feature_access_rules_across_plans(): void
    {
        $user = User::factory()->create();

        // 1. Free Company
        $freeCompany = Company::factory()->create(['owner_id' => $user->id, 'status' => CompanyStatus::Active]);
        $freePlan = Plan::query()->where('slug', Plan::FREE)->firstOrFail();
        $freeCompany->subscriptions()->create([
            'plan_id' => $freePlan->id,
            'status' => SubscriptionStatus::Active,
        ]);

        // 2. Pro Company
        $proCompany = Company::factory()->create(['owner_id' => $user->id, 'status' => CompanyStatus::Active]);
        $proPlan = Plan::query()->where('slug', Plan::PRO)->firstOrFail();
        $proCompany->subscriptions()->create([
            'plan_id' => $proPlan->id,
            'status' => SubscriptionStatus::Active,
        ]);

        // 3. Business Company
        $bizCompany = Company::factory()->create(['owner_id' => $user->id, 'status' => CompanyStatus::Active]);
        $bizPlan = Plan::query()->where('slug', Plan::BUSINESS)->firstOrFail();
        $bizCompany->subscriptions()->create([
            'plan_id' => $bizPlan->id,
            'status' => SubscriptionStatus::Active,
        ]);

        // Expenses
        $this->assertFalse($this->featureAccessService->canUseFeature($freeCompany, Feature::EXPENSES));
        $this->assertTrue($this->featureAccessService->canUseFeature($proCompany, Feature::EXPENSES));
        $this->assertTrue($this->featureAccessService->canUseFeature($bizCompany, Feature::EXPENSES));

        // Stripe Connect
        $this->assertFalse($this->featureAccessService->canUseFeature($freeCompany, Feature::STRIPE_CONNECT));
        $this->assertTrue($this->featureAccessService->canUseFeature($proCompany, Feature::STRIPE_CONNECT));
        $this->assertTrue($this->featureAccessService->canUseFeature($bizCompany, Feature::STRIPE_CONNECT));

        // API Access
        $this->assertFalse($this->featureAccessService->canUseFeature($freeCompany, Feature::API_ACCESS));
        $this->assertFalse($this->featureAccessService->canUseFeature($proCompany, Feature::API_ACCESS));
        $this->assertTrue($this->featureAccessService->canUseFeature($bizCompany, Feature::API_ACCESS));
    }

    public function test_plan_limits_across_tiers(): void
    {
        $user = User::factory()->create();

        $freeCompany = Company::factory()->create(['owner_id' => $user->id, 'status' => CompanyStatus::Active]);
        $freeCompany->subscriptions()->create([
            'plan_id' => Plan::where('slug', Plan::FREE)->first()->id,
            'status' => SubscriptionStatus::Active,
        ]);

        $proCompany = Company::factory()->create(['owner_id' => $user->id, 'status' => CompanyStatus::Active]);
        $proCompany->subscriptions()->create([
            'plan_id' => Plan::where('slug', Plan::PRO)->first()->id,
            'status' => SubscriptionStatus::Active,
        ]);

        $bizCompany = Company::factory()->create(['owner_id' => $user->id, 'status' => CompanyStatus::Active]);
        $bizCompany->subscriptions()->create([
            'plan_id' => Plan::where('slug', Plan::BUSINESS)->first()->id,
            'status' => SubscriptionStatus::Active,
        ]);

        // Customers limit: Free -> 5, Pro -> null (unlimited), Business -> null (unlimited)
        $this->assertEquals(5, $this->usageLimitService->getLimit($freeCompany, Feature::CUSTOMERS));
        $this->assertNull($this->usageLimitService->getLimit($proCompany, Feature::CUSTOMERS));
        $this->assertNull($this->usageLimitService->getLimit($bizCompany, Feature::CUSTOMERS));

        // Team members limit: Free -> 1, Pro -> 5, Business -> null (unlimited)
        $this->assertEquals(1, $this->usageLimitService->getLimit($freeCompany, Feature::TEAM_MEMBERS));
        $this->assertEquals(5, $this->usageLimitService->getLimit($proCompany, Feature::TEAM_MEMBERS));
        $this->assertNull($this->usageLimitService->getLimit($bizCompany, Feature::TEAM_MEMBERS));

        // Invoices monthly limit: Free -> 10, Pro -> null (unlimited), Business -> null (unlimited)
        $this->assertEquals(10, $this->usageLimitService->getLimit($freeCompany, Feature::INVOICES));
        $this->assertNull($this->usageLimitService->getLimit($proCompany, Feature::INVOICES));
        $this->assertNull($this->usageLimitService->getLimit($bizCompany, Feature::INVOICES));
    }

    public function test_mandatory_multi_company_plan_isolation(): void
    {
        $user = User::factory()->create();

        $companyA = Company::factory()->create(['name' => 'Company A', 'status' => CompanyStatus::Active]);
        $companyA->subscriptions()->create([
            'plan_id' => Plan::where('slug', Plan::FREE)->first()->id,
            'status' => SubscriptionStatus::Active,
        ]);

        $companyB = Company::factory()->create(['name' => 'Company B', 'status' => CompanyStatus::Active]);
        $companyB->subscriptions()->create([
            'plan_id' => Plan::where('slug', Plan::PRO)->first()->id,
            'status' => SubscriptionStatus::Active,
        ]);

        // User belongs to both companies
        $companyA->users()->attach($user->id);
        $companyB->users()->attach($user->id);

        // Verification: Company A has Free (expenses denied), Company B has Pro (expenses allowed)
        $this->assertFalse($this->featureAccessService->canUseFeature($companyA, Feature::EXPENSES));
        $this->assertTrue($this->featureAccessService->canUseFeature($companyB, Feature::EXPENSES));
    }

    public function test_role_and_plan_combined_authorization(): void
    {
        $user = User::factory()->create();
        $adminRole = Role::where('slug', Role::ADMIN)->first();

        $companyA = Company::factory()->create(['status' => CompanyStatus::Active]);
        $companyA->subscriptions()->create([
            'plan_id' => Plan::where('slug', Plan::FREE)->first()->id,
            'status' => SubscriptionStatus::Active,
        ]);
        $companyA->users()->attach($user->id, ['role_id' => $adminRole->id]);

        $companyB = Company::factory()->create(['status' => CompanyStatus::Active]);
        $companyB->subscriptions()->create([
            'plan_id' => Plan::where('slug', Plan::PRO)->first()->id,
            'status' => SubscriptionStatus::Active,
        ]);
        $companyB->users()->attach($user->id, ['role_id' => $adminRole->id]);

        // Company A: Admin has 'customers.create' permission AND Free plan has 'customers' feature
        $this->assertTrue($user->hasPermissionInCompany($companyA, 'customers.create'));
        $this->assertTrue($this->featureAccessService->canUseFeature($companyA, Feature::CUSTOMERS));
        $this->assertTrue($user->canInCompany('customers.create', $companyA) && $companyA->canUseFeature(Feature::CUSTOMERS));

        // Company A: Admin has 'expenses.create' permission, BUT Free plan has 'expenses' disabled
        $this->assertTrue($user->hasPermissionInCompany($companyA, 'expenses.create'));
        $this->assertFalse($this->featureAccessService->canUseFeature($companyA, Feature::EXPENSES));
        $this->assertFalse($user->canInCompany('expenses.create', $companyA) && $companyA->canUseFeature(Feature::EXPENSES));

        // Company B: Admin has 'expenses.create' permission AND Pro plan has 'expenses' enabled
        $this->assertTrue($user->hasPermissionInCompany($companyB, 'expenses.create'));
        $this->assertTrue($this->featureAccessService->canUseFeature($companyB, Feature::EXPENSES));
        $this->assertTrue($user->canInCompany('expenses.create', $companyB) && $companyB->canUseFeature(Feature::EXPENSES));
    }

    public function test_limit_behavior_and_remaining_calculation(): void
    {
        $company = Company::factory()->create(['status' => CompanyStatus::Active]);
        $company->subscriptions()->create([
            'plan_id' => Plan::where('slug', Plan::FREE)->first()->id,
            'status' => SubscriptionStatus::Active,
        ]);

        // Customer limit is 5 on Free plan
        $this->assertTrue($this->usageLimitService->canCreate($company, Feature::CUSTOMERS, currentUsage: 4));
        $this->assertEquals(1, $this->usageLimitService->remaining($company, Feature::CUSTOMERS, currentUsage: 4));
        $this->assertFalse($this->featureAccessService->hasReachedLimit($company, Feature::CUSTOMERS, currentUsage: 4));

        $this->assertFalse($this->usageLimitService->canCreate($company, Feature::CUSTOMERS, currentUsage: 5));
        $this->assertEquals(0, $this->usageLimitService->remaining($company, Feature::CUSTOMERS, currentUsage: 5));
        $this->assertTrue($this->featureAccessService->hasReachedLimit($company, Feature::CUSTOMERS, currentUsage: 5));

        $this->assertFalse($this->usageLimitService->canCreate($company, Feature::CUSTOMERS, currentUsage: 6));
        $this->assertEquals(0, $this->usageLimitService->remaining($company, Feature::CUSTOMERS, currentUsage: 6));
        $this->assertTrue($this->featureAccessService->hasReachedLimit($company, Feature::CUSTOMERS, currentUsage: 6));
    }

    public function test_downgrade_validation_and_data_retention_rule(): void
    {
        $company = Company::factory()->create(['status' => CompanyStatus::Active]);
        $proPlan = Plan::where('slug', Plan::PRO)->first();
        $freePlan = Plan::where('slug', Plan::FREE)->first();

        $company->subscriptions()->create([
            'plan_id' => $proPlan->id,
            'status' => SubscriptionStatus::Active,
        ]);

        // Attach 3 team members
        $users = User::factory()->count(3)->create();
        $company->users()->attach($users->pluck('id'));

        // Target Free plan allows 1 team member. Current usage is 3.
        $downgradeResult = $this->usageLimitService->canDowngrade($company, $freePlan);

        $this->assertFalse($downgradeResult['allowed']);
        $this->assertArrayHasKey(Feature::TEAM_MEMBERS, $downgradeResult['violations']);
        $this->assertEquals(3, $downgradeResult['violations'][Feature::TEAM_MEMBERS]['current']);
        $this->assertEquals(1, $downgradeResult['violations'][Feature::TEAM_MEMBERS]['limit']);

        // Even after downgrade simulation, NO records are deleted
        $this->assertEquals(3, $company->users()->count());
    }

    public function test_inactive_company_denied_feature_access(): void
    {
        $company = Company::factory()->create(['status' => CompanyStatus::Inactive]);
        $company->subscriptions()->create([
            'plan_id' => Plan::where('slug', Plan::PRO)->first()->id,
            'status' => SubscriptionStatus::Active,
        ]);

        $this->assertFalse($this->featureAccessService->canUseFeature($company, Feature::EXPENSES));
        $this->assertFalse($this->featureAccessService->canUseFeature($company, Feature::CUSTOMERS));
    }

    public function test_expired_subscription_denied_paid_features_and_falls_back_to_free(): void
    {
        $company = Company::factory()->create(['status' => CompanyStatus::Active]);
        $company->subscriptions()->create([
            'plan_id' => Plan::where('slug', Plan::PRO)->first()->id,
            'status' => SubscriptionStatus::Expired,
        ]);

        // Paid features on Pro (expenses, stripe_connect) are denied
        $this->assertFalse($this->featureAccessService->canUseFeature($company, Feature::EXPENSES));
        $this->assertFalse($this->featureAccessService->canUseFeature($company, Feature::STRIPE_CONNECT));

        // Free plan feature (reports_basic) is accessible via deterministic fallback
        $this->assertTrue($this->featureAccessService->canUseFeature($company, Feature::REPORTS_BASIC));
    }

    public function test_api_endpoints_for_plans_and_features(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $company = Company::factory()->create(['owner_id' => $user->id, 'status' => CompanyStatus::Active]);
        $company->subscriptions()->create([
            'plan_id' => Plan::where('slug', Plan::PRO)->first()->id,
            'status' => SubscriptionStatus::Active,
        ]);
        $company->users()->attach($user->id);

        // 1. GET /api/v1/plans
        $response = $this->getJson('/api/v1/plans');
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data');

        // 2. GET /api/v1/companies/{company}/plan
        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/companies/{$company->id}/plan");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.plan.slug', 'pro')
            ->assertJsonPath('data.features.expenses.enabled', true);

        // 3. GET /api/v1/companies/{company}/features
        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/companies/{$company->id}/features");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.expenses.enabled', true)
            ->assertJsonPath('data.team_members.limit', 5);

        // 4. Non-member IDOR protection
        $otherUser = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($otherUser, 'sanctum')
            ->getJson("/api/v1/companies/{$company->id}/plan")
            ->assertForbidden();
    }
}
