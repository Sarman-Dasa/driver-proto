<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Jobs\ProcessStripeWebhookJob;
use App\Models\Company;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Stripe\StripeBillingService;
use App\Services\Stripe\SubscriptionLifecycleService;
use Database\Seeders\PlanAndFeatureSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Stripe\Checkout\Session as CheckoutSession;
use Stripe\Service\Checkout\SessionService;
use Stripe\Service\SubscriptionService;
use Stripe\StripeClient;
use Tests\TestCase;

class StripeSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $memberWithoutBilling;

    private Company $company;

    private Plan $freePlan;

    private Plan $proPlan;

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
            'stripe_customer_id' => 'cus_test123',
        ]);

        $ownerRole = Role::where('slug', Role::OWNER)->firstOrFail();
        $this->company->users()->attach($this->owner->id, ['role_id' => $ownerRole->id]);

        // Default Free subscription
        Subscription::create([
            'company_id' => $this->company->id,
            'plan_id' => $this->freePlan->id,
            'status' => SubscriptionStatus::Active,
            'starts_at' => now(),
        ]);

        // Member without billing permission
        $this->memberWithoutBilling = User::factory()->create();
        $memberRole = Role::where('slug', Role::MEMBER)->firstOrFail();
        $this->company->users()->attach($this->memberWithoutBilling->id, ['role_id' => $memberRole->id]);
    }

    public function test_non_member_cannot_access_billing(): void
    {
        $outsider = User::factory()->create();

        $response = $this->actingAs($outsider)
            ->getJson("/api/v1/companies/{$this->company->id}/subscription");

        $response->assertStatus(403);
    }

    public function test_member_without_billing_permission_cannot_manage_subscription(): void
    {
        // Can not view if viewer role doesn't have billing.view
        $viewResponse = $this->actingAs($this->memberWithoutBilling)
            ->getJson("/api/v1/companies/{$this->company->id}/subscription");

        $viewResponse->assertStatus(403);

        // Can not checkout
        $checkoutResponse = $this->actingAs($this->memberWithoutBilling)
            ->postJson("/api/v1/companies/{$this->company->id}/subscription/checkout", [
                'plan_id' => $this->proPlan->id,
                'interval' => 'monthly',
            ]);

        $checkoutResponse->assertStatus(403);
    }

    public function test_owner_can_view_current_subscription(): void
    {
        $response = $this->actingAs($this->owner)
            ->getJson("/api/v1/companies/{$this->company->id}/subscription");

        $response->assertStatus(200)
            ->assertJsonPath('data.subscription.plan.id', $this->freePlan->id)
            ->assertJsonPath('data.has_stripe_customer', true);
    }

    public function test_owner_can_initiate_stripe_checkout(): void
    {
        $mockStripe = $this->createMock(StripeBillingService::class);
        $mockSession = CheckoutSession::constructFrom([
            'id' => 'cs_test_session_123',
            'url' => 'https://checkout.stripe.com/c/pay/cs_test_session_123',
        ]);

        $mockStripe->expects($this->once())
            ->method('createCheckoutSession')
            ->willReturn($mockSession);

        $this->app->instance(StripeBillingService::class, $mockStripe);

        $response = $this->actingAs($this->owner)
            ->postJson("/api/v1/companies/{$this->company->id}/subscription/checkout", [
                'plan_id' => $this->proPlan->id,
                'interval' => 'monthly',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.checkout_url', 'https://checkout.stripe.com/c/pay/cs_test_session_123')
            ->assertJsonPath('data.session_id', 'cs_test_session_123');
    }

    public function test_webhook_dispatches_async_job_and_is_idempotent(): void
    {
        Queue::fake();

        config([
            'stripe.webhook_secret' => 'whsec_test_secret',
        ]);

        $payload = json_encode([
            'id' => 'evt_test_123',
            'object' => 'event',
            'type' => 'customer.subscription.created',
            'created' => time(),
            'data' => [
                'object' => [
                    'id' => 'sub_stripe_123',
                    'status' => 'active',
                ],
            ],
        ]);

        $timestamp = time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", 'whsec_test_secret');
        $sigHeader = "t={$timestamp},v1={$signature}";

        // First delivery
        $response = $this->call(
            'POST',
            '/api/v1/webhooks/stripe',
            [],
            [],
            [],
            ['HTTP_STRIPE_SIGNATURE' => $sigHeader, 'CONTENT_TYPE' => 'application/json'],
            $payload
        );

        $response->assertStatus(200)
            ->assertJsonPath('received', true);

        Queue::assertPushed(ProcessStripeWebhookJob::class);
        $this->assertDatabaseHas('stripe_webhook_events', [
            'stripe_event_id' => 'evt_test_123',
            'status' => 'pending',
        ]);

        // Duplicate delivery with same event ID
        $duplicateResponse = $this->call(
            'POST',
            '/api/v1/webhooks/stripe',
            [],
            [],
            [],
            ['HTTP_STRIPE_SIGNATURE' => $sigHeader, 'CONTENT_TYPE' => 'application/json'],
            $payload
        );

        $duplicateResponse->assertStatus(200)
            ->assertJsonPath('duplicate', true);

        // Queue was only pushed once for the new event
        Queue::assertPushed(ProcessStripeWebhookJob::class, 1);
    }

    public function test_subscription_creation_and_update_prevents_duplicate_records(): void
    {
        $lifecycleService = app(SubscriptionLifecycleService::class);
        $eventTimestamp = time();

        $subData = [
            'id' => 'sub_stripe_abc123',
            'customer' => 'cus_test123',
            'status' => 'active',
            'current_period_start' => $eventTimestamp,
            'current_period_end' => $eventTimestamp + 2592000,
            'items' => [
                'data' => [
                    [
                        'price' => [
                            'id' => 'price_pro_monthly',
                        ],
                    ],
                ],
            ],
            'metadata' => [
                'company_id' => (string) $this->company->id,
            ],
        ];

        // Process subscription creation
        $lifecycleService->handleSubscriptionCreatedOrUpdated($subData, $eventTimestamp);

        // Verify company subscription was transitioned and updated without creating duplicates
        $subscriptions = Subscription::where('company_id', $this->company->id)->get();
        $this->assertCount(1, $subscriptions);

        $currentSub = $this->company->fresh()->currentSubscription();
        $this->assertNotNull($currentSub);
        $this->assertEquals('sub_stripe_abc123', $currentSub->stripe_subscription_id);
        $this->assertEquals($this->proPlan->id, $currentSub->plan_id);
        $this->assertEquals(SubscriptionStatus::Active, $currentSub->status);
    }

    public function test_out_of_order_webhook_does_not_overwrite_newer_subscription_state(): void
    {
        $lifecycleService = app(SubscriptionLifecycleService::class);

        $t1 = time() - 3600; // 1 hour ago
        $t2 = time();        // now

        // Process newer event first (e.g. status was renewed to active)
        $newerData = [
            'id' => 'sub_stripe_order_test',
            'customer' => 'cus_test123',
            'status' => 'active',
            'current_period_start' => $t2,
            'current_period_end' => $t2 + 2592000,
            'items' => [
                'data' => [
                    [
                        'price' => ['id' => 'price_pro_monthly'],
                    ],
                ],
            ],
            'metadata' => ['company_id' => (string) $this->company->id],
        ];
        $lifecycleService->handleSubscriptionCreatedOrUpdated($newerData, $t2);

        $sub = Subscription::where('stripe_subscription_id', 'sub_stripe_order_test')->firstOrFail();
        $this->assertEquals(SubscriptionStatus::Active, $sub->status);

        // Now an older delayed event arrives (e.g. past_due from an earlier failed attempt)
        $olderData = [
            'id' => 'sub_stripe_order_test',
            'customer' => 'cus_test123',
            'status' => 'past_due',
            'current_period_start' => $t1,
            'current_period_end' => $t1 + 2592000,
            'items' => [
                'data' => [
                    [
                        'price' => ['id' => 'price_pro_monthly'],
                    ],
                ],
            ],
            'metadata' => ['company_id' => (string) $this->company->id],
        ];
        $lifecycleService->handleSubscriptionCreatedOrUpdated($olderData, $t1);

        // Assert that subscription was NOT overwritten and remains Active
        $sub->refresh();
        $this->assertEquals(SubscriptionStatus::Active, $sub->status);
        $this->assertEquals($t2, $sub->last_stripe_event_created_at->timestamp);
    }

    public function test_billing_history_records_accurate_payments_failures_and_refunds(): void
    {
        $lifecycleService = app(SubscriptionLifecycleService::class);
        $timestamp = time();

        // 1. Invoice paid
        $invoiceData = [
            'id' => 'in_test_123',
            'customer' => 'cus_test123',
            'amount_paid' => 1900,
            'currency' => 'usd',
            'payment_intent' => 'pi_test_123',
            'billing_reason' => 'subscription_cycle',
            'hosted_invoice_url' => 'https://invoice.stripe.com/123',
            'invoice_pdf' => 'https://invoice.stripe.com/123/pdf',
        ];
        $lifecycleService->handleInvoicePaid($invoiceData, 'evt_in_paid', $timestamp);

        $this->assertDatabaseHas('billing_histories', [
            'company_id' => $this->company->id,
            'stripe_invoice_id' => 'in_test_123',
            'amount' => 1900,
            'status' => 'paid',
            'type' => 'payment',
        ]);

        // 2. Invoice payment failed
        $failedInvoiceData = [
            'id' => 'in_test_fail_456',
            'customer' => 'cus_test123',
            'amount_due' => 1900,
            'currency' => 'usd',
            'payment_intent' => 'pi_test_fail_456',
            'billing_reason' => 'subscription_cycle',
            'hosted_invoice_url' => 'https://invoice.stripe.com/456',
            'invoice_pdf' => null,
        ];
        $lifecycleService->handleInvoicePaymentFailed($failedInvoiceData, 'evt_in_fail', $timestamp + 10);

        $this->assertDatabaseHas('billing_histories', [
            'company_id' => $this->company->id,
            'stripe_invoice_id' => 'in_test_fail_456',
            'amount' => 1900,
            'status' => 'failed',
            'type' => 'payment_failed',
        ]);

        // 3. Charge refund: recorded accurately without modifying subscription status
        $refundData = [
            'id' => 'ch_test_refund_789',
            'customer' => 'cus_test123',
            'amount_refunded' => 1900,
            'currency' => 'usd',
            'invoice' => 'in_test_123',
            'payment_intent' => 'pi_test_123',
        ];
        $lifecycleService->handleChargeRefunded($refundData, 'evt_ch_refund', $timestamp + 20);

        $this->assertDatabaseHas('billing_histories', [
            'company_id' => $this->company->id,
            'type' => 'refund',
            'amount' => 1900,
            'status' => 'refunded',
        ]);

        // History API reflects all events
        $historyResponse = $this->actingAs($this->owner)
            ->getJson("/api/v1/companies/{$this->company->id}/subscription/history");

        $historyResponse->assertStatus(200)
            ->assertJsonCount(3, 'data.data');
    }

    public function test_cancellation_and_resumption_flows(): void
    {
        // Set company subscription to Stripe active
        $subscription = $this->company->currentSubscription();
        $subscription->update([
            'stripe_subscription_id' => 'sub_cancel_test',
            'plan_id' => $this->proPlan->id,
            'status' => SubscriptionStatus::Active,
        ]);

        $mockStripe = $this->createMock(StripeBillingService::class);
        $mockStripe->expects($this->once())
            ->method('cancelSubscription');
        $mockStripe->expects($this->once())
            ->method('resumeSubscription');

        $this->app->instance(StripeBillingService::class, $mockStripe);

        // Cancel at period end
        $cancelResponse = $this->actingAs($this->owner)
            ->postJson("/api/v1/companies/{$this->company->id}/subscription/cancel", [
                'immediately' => false,
            ]);

        $cancelResponse->assertStatus(200)
            ->assertJsonPath('data.subscription.cancel_at_period_end', true);

        $subscription->refresh();
        $this->assertTrue($subscription->cancel_at_period_end);

        // Resume subscription
        $resumeResponse = $this->actingAs($this->owner)
            ->postJson("/api/v1/companies/{$this->company->id}/subscription/resume");

        $resumeResponse->assertStatus(200)
            ->assertJsonPath('data.subscription.cancel_at_period_end', false);

        $subscription->refresh();
        $this->assertFalse($subscription->cancel_at_period_end);
    }

    public function test_newly_registered_user_can_immediately_initiate_checkout_for_their_workspace(): void
    {
        $mockStripe = $this->createMock(StripeBillingService::class);
        $mockSession = CheckoutSession::constructFrom([
            'id' => 'cs_test_new_owner_123',
            'url' => 'https://checkout.stripe.com/c/pay/cs_test_new_owner_123',
        ]);
        $mockStripe->expects($this->once())
            ->method('createCheckoutSession')
            ->willReturn($mockSession);
        $this->app->instance(StripeBillingService::class, $mockStripe);

        // 1. Register new user
        $registerResponse = $this->postJson('/api/v1/auth/register', [
            'name' => 'Brand New Founder',
            'email' => 'founder@brandnew.test',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'company_name' => 'Founder Studios',
        ]);

        $registerResponse->assertStatus(201);
        $token = $registerResponse->json('data.token');
        $userId = $registerResponse->json('data.user.id');

        $newUser = User::findOrFail($userId);
        $newCompany = $newUser->companies()->firstOrFail();

        // Verify company setup is complete and authorized
        $this->assertEquals($newUser->id, $newCompany->owner_id);
        $this->assertTrue($newUser->isOwnerOf($newCompany));
        $this->assertTrue($newUser->hasPermissionInCompany($newCompany, 'billing.manage'));
        $this->assertNotNull($newCompany->currentSubscription());

        // 2. Newly registered owner immediately selects a plan and initiates checkout
        $checkoutResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/companies/{$newCompany->id}/subscription/checkout", [
                'plan_id' => $this->proPlan->id,
                'interval' => 'monthly',
            ]);

        $checkoutResponse->assertStatus(200)
            ->assertJsonPath('data.checkout_url', 'https://checkout.stripe.com/c/pay/cs_test_new_owner_123');
    }

    public function test_authorized_billing_manager_can_initiate_checkout(): void
    {
        $mockStripe = $this->createMock(StripeBillingService::class);
        $mockSession = CheckoutSession::constructFrom([
            'id' => 'cs_test_mgr_123',
            'url' => 'https://checkout.stripe.com/c/pay/cs_test_mgr_123',
        ]);
        $mockStripe->expects($this->once())
            ->method('createCheckoutSession')
            ->willReturn($mockSession);
        $this->app->instance(StripeBillingService::class, $mockStripe);

        // Create billing manager role with billing.manage permission
        $managerRole = Role::create([
            'name' => 'Billing Manager',
            'slug' => 'billing-manager',
            'is_system' => false,
        ]);
        $billingManagePermission = Permission::where('slug', 'billing.manage')->firstOrFail();
        $managerRole->permissions()->attach($billingManagePermission->id);

        $billingManager = User::factory()->create();
        $this->company->users()->attach($billingManager->id, ['role_id' => $managerRole->id]);

        $response = $this->actingAs($billingManager)
            ->postJson("/api/v1/companies/{$this->company->id}/subscription/checkout", [
                'plan_id' => $this->proPlan->id,
                'interval' => 'monthly',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.checkout_url', 'https://checkout.stripe.com/c/pay/cs_test_mgr_123');
    }

    public function test_unauthorized_member_cannot_initiate_checkout_and_gets_workspace_denial(): void
    {
        $response = $this->actingAs($this->memberWithoutBilling)
            ->postJson("/api/v1/companies/{$this->company->id}/subscription/checkout", [
                'plan_id' => $this->proPlan->id,
                'interval' => 'monthly',
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('message', 'You do not have permission to perform this action in this workspace.');
    }

    public function test_cross_company_access_is_prevented_even_if_user_is_owner_of_another_company(): void
    {
        $otherOwner = User::factory()->create();
        $otherCompany = Company::factory()->create([
            'owner_id' => $otherOwner->id,
        ]);
        $ownerRole = Role::where('slug', Role::OWNER)->firstOrFail();
        $otherCompany->users()->attach($otherOwner->id, ['role_id' => $ownerRole->id]);

        // otherOwner tries to manage billing on $this->company
        $response = $this->actingAs($otherOwner)
            ->postJson("/api/v1/companies/{$this->company->id}/subscription/checkout", [
                'plan_id' => $this->proPlan->id,
                'interval' => 'monthly',
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('message', 'You do not have access to this company.');
    }

    public function test_owner_can_verify_completed_checkout_session_and_activate_subscription(): void
    {
        $mockStripeClient = $this->createMock(StripeClient::class);
        $mockSessions = $this->createMock(SessionService::class);
        $mockSubs = $this->createMock(SubscriptionService::class);

        $fakeSession = CheckoutSession::constructFrom([
            'id' => 'cs_test_success_123',
            'status' => 'complete',
            'payment_status' => 'paid',
            'customer' => 'cus_test_cust_123',
            'metadata' => [
                'company_id' => (string) $this->company->id,
                'plan_id' => (string) $this->proPlan->id,
            ],
            'subscription' => 'sub_stripe_verified_123',
        ]);

        $fakeStripeSub = \Stripe\Subscription::constructFrom([
            'id' => 'sub_stripe_verified_123',
            'status' => 'active',
            'created' => time(),
            'current_period_start' => time(),
            'current_period_end' => time() + (30 * 86400),
            'items' => [
                'data' => [
                    [
                        'price' => [
                            'id' => $this->proPlan->stripe_monthly_price_id,
                        ],
                    ],
                ],
            ],
            'metadata' => [
                'company_id' => (string) $this->company->id,
                'plan_id' => (string) $this->proPlan->id,
            ],
        ]);

        $mockSessions->expects($this->once())
            ->method('retrieve')
            ->with('cs_test_success_123', ['expand' => ['subscription']])
            ->willReturn($fakeSession);

        $mockSubs->expects($this->once())
            ->method('retrieve')
            ->with('sub_stripe_verified_123')
            ->willReturn($fakeStripeSub);

        $mockStripeClient->checkout = new class($mockSessions)
        {
            public function __construct(public $sessions) {}
        };
        $mockStripeClient->subscriptions = $mockSubs;

        $mockStripeService = $this->createMock(StripeBillingService::class);
        $mockStripeService->method('getClient')->willReturn($mockStripeClient);
        $this->app->instance(StripeBillingService::class, $mockStripeService);

        $response = $this->actingAs($this->owner)
            ->postJson("/api/v1/companies/{$this->company->id}/subscription/verify-session", [
                'session_id' => 'cs_test_success_123',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.subscription.plan.id', $this->proPlan->id)
            ->assertJsonPath('data.subscription.status', 'active')
            ->assertJsonPath('data.subscription.stripe_subscription_id', 'sub_stripe_verified_123');

        $this->assertDatabaseHas('subscriptions', [
            'company_id' => $this->company->id,
            'plan_id' => $this->proPlan->id,
            'stripe_subscription_id' => 'sub_stripe_verified_123',
            'status' => 'active',
        ]);
    }

    public function test_verify_session_rejects_session_belonging_to_another_company(): void
    {
        $mockStripeClient = $this->createMock(StripeClient::class);
        $mockSessions = $this->createMock(SessionService::class);

        $fakeSession = CheckoutSession::constructFrom([
            'id' => 'cs_test_other_company',
            'status' => 'complete',
            'payment_status' => 'paid',
            'customer' => 'cus_other',
            'metadata' => [
                'company_id' => '999999', // Different company
            ],
            'subscription' => 'sub_other',
        ]);

        $mockSessions->expects($this->once())
            ->method('retrieve')
            ->willReturn($fakeSession);

        $mockStripeClient->checkout = new class($mockSessions)
        {
            public function __construct(public $sessions) {}
        };

        $mockStripeService = $this->createMock(StripeBillingService::class);
        $mockStripeService->method('getClient')->willReturn($mockStripeClient);
        $this->app->instance(StripeBillingService::class, $mockStripeService);

        $response = $this->actingAs($this->owner)
            ->postJson("/api/v1/companies/{$this->company->id}/subscription/verify-session", [
                'session_id' => 'cs_test_other_company',
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('message', 'This checkout session does not belong to this workspace.');
    }

    public function test_verify_session_rejects_unpaid_session(): void
    {
        $mockStripeClient = $this->createMock(StripeClient::class);
        $mockSessions = $this->createMock(SessionService::class);

        $fakeSession = CheckoutSession::constructFrom([
            'id' => 'cs_test_unpaid',
            'status' => 'open',
            'payment_status' => 'unpaid',
            'metadata' => [
                'company_id' => (string) $this->company->id,
            ],
        ]);

        $mockSessions->expects($this->once())
            ->method('retrieve')
            ->willReturn($fakeSession);

        $mockStripeClient->checkout = new class($mockSessions)
        {
            public function __construct(public $sessions) {}
        };

        $mockStripeService = $this->createMock(StripeBillingService::class);
        $mockStripeService->method('getClient')->willReturn($mockStripeClient);
        $this->app->instance(StripeBillingService::class, $mockStripeService);

        $response = $this->actingAs($this->owner)
            ->postJson("/api/v1/companies/{$this->company->id}/subscription/verify-session", [
                'session_id' => 'cs_test_unpaid',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Checkout session is not complete or payment is still pending.');
    }
}
