<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Jobs\ProcessStripeWebhookJob;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Role;
use App\Models\StripeConnectAccount;
use App\Models\StripeWebhookEvent;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Stripe\StripeConnectService;
use App\Services\Stripe\SubscriptionLifecycleService;
use Database\Seeders\PlanAndFeatureSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Stripe\Account as StripeAccount;
use Stripe\AccountLink;
use Stripe\LoginLink;
use Stripe\Service\AccountLinkService;
use Stripe\Service\AccountService;
use Stripe\Service\V2\Core\CoreServiceFactory;
use Stripe\Service\V2\V2ServiceFactory;
use Stripe\StripeClient;
use Stripe\V2\Core\Account;
use Tests\TestCase;

class StripeConnectTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $memberWithoutStripe;

    private Company $company;

    private Plan $freePlan;

    private Plan $proPlan;

    private StripeConnectService $connectService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(PlanAndFeatureSeeder::class);

        $this->freePlan = Plan::where('slug', Plan::FREE)->firstOrFail();
        $this->proPlan = Plan::where('slug', Plan::PRO)->firstOrFail();

        $this->owner = User::factory()->create();
        $this->company = Company::factory()->create([
            'owner_id' => $this->owner->id,
            'stripe_customer_id' => 'cus_billflow_saas_sub_123',
        ]);

        $ownerRole = Role::where('slug', Role::OWNER)->firstOrFail();
        $this->company->users()->attach($this->owner->id, ['role_id' => $ownerRole->id]);

        // Default Free plan subscription
        Subscription::create([
            'company_id' => $this->company->id,
            'plan_id' => $this->freePlan->id,
            'status' => SubscriptionStatus::Active,
            'starts_at' => now(),
        ]);

        // Member without Stripe permissions
        $this->memberWithoutStripe = User::factory()->create();
        $memberRole = Role::where('slug', Role::MEMBER)->firstOrFail();
        $this->company->users()->attach($this->memberWithoutStripe->id, ['role_id' => $memberRole->id]);

        $this->connectService = new StripeConnectService;
        $this->app->instance(StripeConnectService::class, $this->connectService);
    }

    public function test_non_member_cannot_access_stripe_connect(): void
    {
        $outsider = User::factory()->create();

        $response = $this->actingAs($outsider)
            ->getJson("/api/v1/companies/{$this->company->id}/stripe-connect");

        $response->assertStatus(403);
    }

    public function test_member_without_permission_cannot_access_stripe_connect(): void
    {
        $response = $this->actingAs($this->memberWithoutStripe)
            ->getJson("/api/v1/companies/{$this->company->id}/stripe-connect");

        $response->assertStatus(403);
    }

    public function test_owner_can_view_stripe_connect_status_when_not_connected(): void
    {
        $response = $this->actingAs($this->owner)
            ->getJson("/api/v1/companies/{$this->company->id}/stripe-connect");

        $response->assertStatus(200)
            ->assertJsonPath('data.summary.is_connected', false)
            ->assertJsonPath('data.summary.status', 'not_connected')
            ->assertJsonPath('data.is_ready_for_payments', false)
            ->assertJsonPath('data.can_manage', true);
    }

    public function test_free_plan_company_is_blocked_from_onboarding_with_upgrade_prompt(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson("/api/v1/companies/{$this->company->id}/stripe-connect/onboard");

        $response->assertStatus(403)
            ->assertJsonPath('errors.upgrade_required', true);
    }

    public function test_pro_plan_company_can_initiate_stripe_connect_onboarding_via_accounts_v2(): void
    {
        // Upgrade company to Pro plan
        Subscription::where('company_id', $this->company->id)->update([
            'plan_id' => $this->proPlan->id,
        ]);

        // Mock Stripe API for Accounts v2
        $mockStripe = Mockery::mock(StripeClient::class);
        $mockV2 = Mockery::mock(V2ServiceFactory::class);
        $mockCore = Mockery::mock(CoreServiceFactory::class);
        $mockV2Accounts = Mockery::mock(\Stripe\Service\V2\Core\AccountService::class);
        $mockV2AccountLinks = Mockery::mock(\Stripe\Service\V2\Core\AccountLinkService::class);

        $mockStripe->v2 = $mockV2;
        $mockV2->core = $mockCore;
        $mockCore->accounts = $mockV2Accounts;
        $mockCore->accountLinks = $mockV2AccountLinks;

        $fakeV2Account = Account::constructFrom([
            'id' => 'acct_v2_test123',
            'dashboard' => 'express',
            'identity' => ['country' => 'us'],
            'defaults' => ['currency' => 'usd'],
        ]);

        $mockV2Accounts->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($args) {
                return isset($args['contact_email'])
                    && $args['dashboard'] === 'express'
                    && isset($args['configuration']['merchant']['capabilities']['card_payments'])
                    && isset($args['defaults']['responsibilities']['fees_collector']);
            }))
            ->andReturn($fakeV2Account);

        $fakeV2AccountLink = \Stripe\V2\Core\AccountLink::constructFrom([
            'url' => 'https://connect.stripe.com/setup/v2/test_session_123',
        ]);

        $mockV2AccountLinks->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($args) {
                return $args['account'] === 'acct_v2_test123';
            }))
            ->andReturn($fakeV2AccountLink);

        $this->connectService->setClient($mockStripe);

        $response = $this->actingAs($this->owner)
            ->postJson("/api/v1/companies/{$this->company->id}/stripe-connect/onboard");

        $response->assertStatus(200)
            ->assertJsonPath('data.url', 'https://connect.stripe.com/setup/v2/test_session_123');

        $this->assertDatabaseHas('stripe_connect_accounts', [
            'company_id' => $this->company->id,
            'stripe_account_id' => 'acct_v2_test123',
            'account_type' => 'express',
            'status' => StripeConnectAccount::STATUS_PENDING,
        ]);

        // BillFlow SaaS subscription stripe_customer_id must NOT be altered
        $this->assertEquals('cus_billflow_saas_sub_123', $this->company->fresh()->stripe_customer_id);
    }

    public function test_onboard_v2_fallback_to_v1_and_fallback_account_links(): void
    {
        Subscription::where('company_id', $this->company->id)->update([
            'plan_id' => $this->proPlan->id,
        ]);

        $mockStripe = Mockery::mock(StripeClient::class);
        $mockV2 = Mockery::mock(V2ServiceFactory::class);
        $mockCore = Mockery::mock(CoreServiceFactory::class);
        $mockV2Accounts = Mockery::mock(\Stripe\Service\V2\Core\AccountService::class);
        $mockV2AccountLinks = Mockery::mock(\Stripe\Service\V2\Core\AccountLinkService::class);
        $mockV1Accounts = Mockery::mock(AccountService::class);
        $mockV1AccountLinks = Mockery::mock(AccountLinkService::class);

        $mockStripe->v2 = $mockV2;
        $mockV2->core = $mockCore;
        $mockCore->accounts = $mockV2Accounts;
        $mockCore->accountLinks = $mockV2AccountLinks;
        $mockStripe->accounts = $mockV1Accounts;
        $mockStripe->accountLinks = $mockV1AccountLinks;

        // V2 accounts->create throws exception to trigger fallback
        $mockV2Accounts->shouldReceive('create')
            ->once()
            ->andThrow(new \Exception('V2 preview not enabled'));

        $fakeV1Account = StripeAccount::constructFrom([
            'id' => 'acct_fallback_123',
            'type' => 'express',
            'country' => 'US',
            'default_currency' => 'usd',
            'charges_enabled' => false,
            'payouts_enabled' => false,
            'details_submitted' => false,
        ]);

        $mockV1Accounts->shouldReceive('create')
            ->once()
            ->andReturn($fakeV1Account);

        // V2 account link throws exception to trigger fallback to V1 account link
        $mockV2AccountLinks->shouldReceive('create')
            ->once()
            ->andThrow(new \Exception('V2 links not supported'));

        $fakeV1AccountLink = AccountLink::constructFrom([
            'url' => 'https://connect.stripe.com/setup/v1/fallback_link',
        ]);

        $mockV1AccountLinks->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($args) {
                return $args['account'] === 'acct_fallback_123' && $args['type'] === 'account_onboarding';
            }))
            ->andReturn($fakeV1AccountLink);

        $this->connectService->setClient($mockStripe);

        $response = $this->actingAs($this->owner)
            ->postJson("/api/v1/companies/{$this->company->id}/stripe-connect/onboard");

        $response->assertStatus(200)
            ->assertJsonPath('data.url', 'https://connect.stripe.com/setup/v1/fallback_link');

        $this->assertDatabaseHas('stripe_connect_accounts', [
            'company_id' => $this->company->id,
            'stripe_account_id' => 'acct_fallback_123',
            'account_type' => 'express',
        ]);
    }

    public function test_onboard_reuses_existing_connected_account_without_duplicate_creation(): void
    {
        Subscription::where('company_id', $this->company->id)->update([
            'plan_id' => $this->proPlan->id,
        ]);

        StripeConnectAccount::create([
            'company_id' => $this->company->id,
            'stripe_account_id' => 'acct_existing_999',
            'account_type' => 'express',
            'country' => 'US',
            'currency' => 'USD',
            'charges_enabled' => false,
            'payouts_enabled' => false,
            'details_submitted' => false,
            'status' => StripeConnectAccount::STATUS_PENDING,
        ]);

        $mockStripe = Mockery::mock(StripeClient::class);
        $mockAccounts = Mockery::mock(AccountService::class);
        $mockAccountLinks = Mockery::mock(AccountLinkService::class);

        $mockStripe->accounts = $mockAccounts;
        $mockStripe->accountLinks = $mockAccountLinks;

        // accounts->create must NOT be called
        $mockAccounts->shouldNotReceive('create');

        $fakeAccountLink = new AccountLink;
        $fakeAccountLink->url = 'https://connect.stripe.com/setup/s/existing_link';

        $mockAccountLinks->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($args) {
                return $args['account'] === 'acct_existing_999';
            }))
            ->andReturn($fakeAccountLink);

        $this->connectService->setClient($mockStripe);

        $response = $this->actingAs($this->owner)
            ->postJson("/api/v1/companies/{$this->company->id}/stripe-connect/onboard");

        $response->assertStatus(200)
            ->assertJsonPath('data.url', 'https://connect.stripe.com/setup/s/existing_link');

        $this->assertCount(1, StripeConnectAccount::where('company_id', $this->company->id)->get());
    }

    public function test_onboard_api_error_returns_clean_500(): void
    {
        Subscription::where('company_id', $this->company->id)->update([
            'plan_id' => $this->proPlan->id,
        ]);

        $mockStripe = Mockery::mock(StripeClient::class);
        $mockV2 = Mockery::mock(V2ServiceFactory::class);
        $mockCore = Mockery::mock(CoreServiceFactory::class);
        $mockV2Accounts = Mockery::mock(\Stripe\Service\V2\Core\AccountService::class);
        $mockV1Accounts = Mockery::mock(AccountService::class);

        $mockStripe->v2 = $mockV2;
        $mockV2->core = $mockCore;
        $mockCore->accounts = $mockV2Accounts;
        $mockStripe->accounts = $mockV1Accounts;

        $mockV2Accounts->shouldReceive('create')
            ->once()
            ->andThrow(new \Exception('Fatal Stripe v2 API outage'));

        $mockV1Accounts->shouldReceive('create')
            ->once()
            ->andThrow(new \Exception('Fatal Stripe v1 API outage'));

        $this->connectService->setClient($mockStripe);

        $response = $this->actingAs($this->owner)
            ->postJson("/api/v1/companies/{$this->company->id}/stripe-connect/onboard");

        $response->assertStatus(500)
            ->assertJsonPath('success', false);
    }

    public function test_sync_updates_account_status_and_readiness(): void
    {
        $account = StripeConnectAccount::create([
            'company_id' => $this->company->id,
            'stripe_account_id' => 'acct_sync_test',
            'account_type' => 'express',
            'country' => 'US',
            'currency' => 'USD',
            'charges_enabled' => false,
            'payouts_enabled' => false,
            'details_submitted' => false,
            'status' => StripeConnectAccount::STATUS_PENDING,
        ]);

        $mockStripe = Mockery::mock(StripeClient::class);
        $mockAccounts = Mockery::mock(AccountService::class);
        $mockStripe->accounts = $mockAccounts;

        $stripeData = [
            'id' => 'acct_sync_test',
            'type' => 'express',
            'country' => 'US',
            'default_currency' => 'usd',
            'charges_enabled' => true,
            'payouts_enabled' => true,
            'details_submitted' => true,
            'capabilities' => ['card_payments' => 'active', 'transfers' => 'active'],
            'requirements' => ['currently_due' => [], 'past_due' => [], 'disabled_reason' => null],
        ];

        $fakeAccount = StripeAccount::constructFrom($stripeData);

        $mockAccounts->shouldReceive('retrieve')
            ->once()
            ->with('acct_sync_test', [])
            ->andReturn($fakeAccount);

        $this->connectService->setClient($mockStripe);

        $response = $this->actingAs($this->owner)
            ->postJson("/api/v1/companies/{$this->company->id}/stripe-connect/sync");

        $response->assertStatus(200)
            ->assertJsonPath('data.is_ready_for_payments', true)
            ->assertJsonPath('data.account.charges_enabled', true)
            ->assertJsonPath('data.account.payouts_enabled', true)
            ->assertJsonPath('data.account.details_submitted', true)
            ->assertJsonPath('data.account.status', StripeConnectAccount::STATUS_ACTIVE);

        $account->refresh();
        $this->assertTrue($account->isReadyForPayments());
        $this->assertEquals(StripeConnectAccount::STATUS_ACTIVE, $account->status);
    }

    public function test_dashboard_link_generation(): void
    {
        $account = StripeConnectAccount::create([
            'company_id' => $this->company->id,
            'stripe_account_id' => 'acct_dash_test',
            'account_type' => 'express',
            'country' => 'US',
            'currency' => 'USD',
            'charges_enabled' => true,
            'payouts_enabled' => true,
            'details_submitted' => true,
            'status' => StripeConnectAccount::STATUS_ACTIVE,
        ]);

        $mockStripe = Mockery::mock(StripeClient::class);
        $mockAccounts = Mockery::mock(AccountService::class);
        $mockStripe->accounts = $mockAccounts;

        $fakeLink = LoginLink::constructFrom([
            'url' => 'https://connect.stripe.com/express/acct_dash_test/dashboard',
        ]);

        $mockAccounts->shouldReceive('createLoginLink')
            ->once()
            ->with('acct_dash_test', [])
            ->andReturn($fakeLink);

        $this->connectService->setClient($mockStripe);

        $response = $this->actingAs($this->owner)
            ->postJson("/api/v1/companies/{$this->company->id}/stripe-connect/dashboard-link");

        $response->assertStatus(200)
            ->assertJsonPath('data.url', 'https://connect.stripe.com/express/acct_dash_test/dashboard');
    }

    public function test_disconnect_marks_account_disabled(): void
    {
        $account = StripeConnectAccount::create([
            'company_id' => $this->company->id,
            'stripe_account_id' => 'acct_disconnect_test',
            'account_type' => 'express',
            'country' => 'US',
            'currency' => 'USD',
            'charges_enabled' => true,
            'payouts_enabled' => true,
            'details_submitted' => true,
            'status' => StripeConnectAccount::STATUS_ACTIVE,
        ]);

        $mockStripe = Mockery::mock(StripeClient::class);
        $mockAccounts = Mockery::mock(AccountService::class);
        $mockStripe->accounts = $mockAccounts;

        $mockAccounts->shouldReceive('delete')
            ->once()
            ->with('acct_disconnect_test', [])
            ->andReturn(null);

        $this->connectService->setClient($mockStripe);

        $response = $this->actingAs($this->owner)
            ->postJson("/api/v1/companies/{$this->company->id}/stripe-connect/disconnect");

        $response->assertStatus(200);

        $account->refresh();
        $this->assertEquals(StripeConnectAccount::STATUS_DISABLED, $account->status);
        $this->assertFalse($account->charges_enabled);
        $this->assertFalse($account->isReadyForPayments());
    }

    public function test_cross_company_access_is_prevented(): void
    {
        $otherOwner = User::factory()->create();
        $otherCompany = Company::factory()->create(['owner_id' => $otherOwner->id]);
        $ownerRole = Role::where('slug', Role::OWNER)->firstOrFail();
        $otherCompany->users()->attach($otherOwner->id, ['role_id' => $ownerRole->id]);

        // Attempting to access other company's connect endpoint
        $response = $this->actingAs($this->owner)
            ->getJson("/api/v1/companies/{$otherCompany->id}/stripe-connect");

        $response->assertStatus(403);
    }

    public function test_webhook_account_updated_syncs_account_state(): void
    {
        $account = StripeConnectAccount::create([
            'company_id' => $this->company->id,
            'stripe_account_id' => 'acct_webhook_test',
            'account_type' => 'express',
            'country' => 'US',
            'currency' => 'USD',
            'charges_enabled' => false,
            'payouts_enabled' => false,
            'details_submitted' => false,
            'status' => StripeConnectAccount::STATUS_PENDING,
        ]);

        $eventPayload = [
            'id' => 'evt_test_account_updated',
            'created' => time(),
            'data' => [
                'object' => [
                    'id' => 'acct_webhook_test',
                    'type' => 'express',
                    'country' => 'US',
                    'default_currency' => 'usd',
                    'charges_enabled' => true,
                    'payouts_enabled' => true,
                    'details_submitted' => true,
                    'capabilities' => ['card_payments' => 'active', 'transfers' => 'active'],
                    'requirements' => ['currently_due' => [], 'past_due' => [], 'disabled_reason' => null],
                ],
            ],
        ];

        $eventRecord = StripeWebhookEvent::create([
            'stripe_event_id' => 'evt_test_account_updated',
            'type' => 'account.updated',
            'payload' => $eventPayload,
            'status' => 'pending',
            'stripe_event_created_at' => now(),
        ]);

        $job = new ProcessStripeWebhookJob($eventRecord->id);
        $job->handle(
            app(SubscriptionLifecycleService::class),
            $this->connectService
        );

        $account->refresh();
        $this->assertTrue($account->charges_enabled);
        $this->assertTrue($account->payouts_enabled);
        $this->assertTrue($account->details_submitted);
        $this->assertEquals(StripeConnectAccount::STATUS_ACTIVE, $account->status);
        $this->assertTrue($account->isReadyForPayments());
    }

    public function test_webhook_account_deauthorized_marks_account_disabled(): void
    {
        $account = StripeConnectAccount::create([
            'company_id' => $this->company->id,
            'stripe_account_id' => 'acct_deauth_test',
            'account_type' => 'express',
            'country' => 'US',
            'currency' => 'USD',
            'charges_enabled' => true,
            'payouts_enabled' => true,
            'details_submitted' => true,
            'status' => StripeConnectAccount::STATUS_ACTIVE,
        ]);

        $eventPayload = [
            'id' => 'evt_test_deauth',
            'created' => time(),
            'data' => [
                'object' => [
                    'id' => 'acct_deauth_test',
                ],
            ],
        ];

        $eventRecord = StripeWebhookEvent::create([
            'stripe_event_id' => 'evt_test_deauth',
            'type' => 'account.application.deauthorized',
            'payload' => $eventPayload,
            'status' => 'pending',
            'stripe_event_created_at' => now(),
        ]);

        $job = new ProcessStripeWebhookJob($eventRecord->id);
        $job->handle(
            app(SubscriptionLifecycleService::class),
            $this->connectService
        );

        $account->refresh();
        $this->assertEquals(StripeConnectAccount::STATUS_DISABLED, $account->status);
        $this->assertFalse($account->charges_enabled);
        $this->assertFalse($account->isReadyForPayments());
    }

    public function test_stripe_connect_does_not_interfere_with_billflow_subscription_customer_id(): void
    {
        $this->assertEquals('cus_billflow_saas_sub_123', $this->company->stripe_customer_id);

        $connectAccount = StripeConnectAccount::create([
            'company_id' => $this->company->id,
            'stripe_account_id' => 'acct_isolated_123',
            'account_type' => 'express',
            'country' => 'US',
            'currency' => 'USD',
            'charges_enabled' => true,
            'payouts_enabled' => true,
            'details_submitted' => true,
            'status' => StripeConnectAccount::STATUS_ACTIVE,
        ]);

        $this->assertEquals('cus_billflow_saas_sub_123', $this->company->fresh()->stripe_customer_id);
        $this->assertEquals('acct_isolated_123', $this->company->fresh()->stripeConnectAccount->stripe_account_id);
        $this->assertNotEquals(
            $this->company->fresh()->stripe_customer_id,
            $this->company->fresh()->stripeConnectAccount->stripe_account_id
        );
    }
}
