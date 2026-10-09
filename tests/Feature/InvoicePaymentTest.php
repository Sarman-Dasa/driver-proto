<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Jobs\ProcessStripeWebhookJob;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Role;
use App\Models\StripeConnectAccount;
use App\Models\StripeWebhookEvent;
use App\Models\User;
use App\Services\Payment\InvoicePaymentService;
use App\Services\Stripe\StripeConnectService;
use App\Services\Stripe\SubscriptionLifecycleService;
use Database\Seeders\PlanAndFeatureSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Stripe\Checkout\Session as StripeSession;
use Stripe\Service\Checkout\SessionService;
use Stripe\StripeClient;
use Tests\TestCase;

class InvoicePaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $member;

    private Company $company;

    private StripeConnectAccount $connectAccount;

    private Customer $customer;

    private InvoicePaymentService $paymentService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(PlanAndFeatureSeeder::class);

        $this->owner = User::factory()->create();
        $this->member = User::factory()->create();

        $this->company = Company::factory()->create([
            'owner_id' => $this->owner->id,
            'name' => 'Acme Corporation',
            'currency' => 'USD',
        ]);

        $ownerRole = Role::where('slug', Role::OWNER)->firstOrFail();
        $memberRole = Role::where('slug', Role::MEMBER)->firstOrFail();

        $this->company->users()->attach($this->owner->id, ['role_id' => $ownerRole->id]);
        $this->company->users()->attach($this->member->id, ['role_id' => $memberRole->id]);

        // Connect Account setup with payment-ready status
        $this->connectAccount = StripeConnectAccount::create([
            'company_id' => $this->company->id,
            'stripe_account_id' => 'acct_test_connected123',
            'account_type' => 'express',
            'country' => 'US',
            'currency' => 'USD',
            'charges_enabled' => true,
            'payouts_enabled' => true,
            'details_submitted' => true,
            'status' => StripeConnectAccount::STATUS_ACTIVE,
            'capabilities' => ['card_payments' => 'active', 'transfers' => 'active'],
            'requirements' => ['disabled_reason' => null],
            'connected_at' => now(),
            'last_synced_at' => now(),
        ]);

        $this->customer = Customer::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Jane Client',
            'email' => 'jane@example.com',
        ]);

        $this->paymentService = app(InvoicePaymentService::class);
        $this->app->instance(InvoicePaymentService::class, $this->paymentService);
    }

    public function test_can_generate_invoice_payment_link_as_authorized_user(): void
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'status' => InvoiceStatus::Sent->value,
            'total_amount' => 15000,
            'balance_due' => 15000,
        ]);

        $response = $this->actingAs($this->owner)
            ->withHeaders(['X-Company-Id' => $this->company->id])
            ->postJson("/api/v1/companies/{$this->company->id}/invoices/{$invoice->id}/payment-link");

        $response->assertOk()
            ->assertJsonPath('data.is_payable', true)
            ->assertJsonPath('data.balance_due', 15000);

        $token = $response->json('data.token');
        $this->assertNotEmpty($token);
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'payment_token' => $token,
        ]);
    }

    public function test_unauthorized_user_cannot_generate_payment_link(): void
    {
        $otherCompany = Company::factory()->create();
        $otherInvoice = Invoice::factory()->create([
            'company_id' => $otherCompany->id,
        ]);

        $response = $this->actingAs($this->owner)
            ->withHeaders(['X-Company-Id' => $this->company->id])
            ->postJson("/api/v1/companies/{$this->company->id}/invoices/{$otherInvoice->id}/payment-link");

        $response->assertNotFound();
    }

    public function test_can_retrieve_public_invoice_details_with_valid_token(): void
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'status' => InvoiceStatus::Sent->value,
            'total_amount' => 20000,
            'balance_due' => 20000,
            'payment_token' => 'secure_token_1234567890',
            'payment_token_expires_at' => now()->addDays(30),
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_name' => 'Web Development Services',
            'quantity' => 1,
            'unit_price' => 20000,
            'subtotal' => 20000,
            'tax_amount' => 0,
            'total' => 20000,
        ]);

        $response = $this->getJson("/api/v1/public/invoices/{$invoice->payment_token}");

        $response->assertOk()
            ->assertJsonPath('data.invoice_number', $invoice->invoice_number)
            ->assertJsonPath('data.company.name', 'Acme Corporation')
            ->assertJsonPath('data.total_amount', 20000)
            ->assertJsonPath('data.balance_due', 20000)
            ->assertJsonPath('data.is_payable', true)
            ->assertJsonPath('data.stripe_connect_ready', true)
            ->assertJsonPath('data.items.0.product_name', 'Web Development Services');
    }

    public function test_cannot_retrieve_public_invoice_details_with_invalid_or_expired_token(): void
    {
        $response = $this->getJson('/api/v1/public/invoices/non_existent_token');
        $response->assertStatus(422);

        $expiredInvoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'status' => InvoiceStatus::Sent->value,
            'payment_token' => 'expired_token_123',
            'payment_token_expires_at' => now()->subDay(),
        ]);

        $expiredResponse = $this->getJson("/api/v1/public/invoices/{$expiredInvoice->payment_token}");
        $expiredResponse->assertOk()
            ->assertJsonPath('data.is_expired', true)
            ->assertJsonPath('data.is_payable', false);
    }

    public function test_cannot_create_checkout_session_for_draft_invoice(): void
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'status' => InvoiceStatus::Draft->value,
            'payment_token' => 'draft_invoice_token',
        ]);

        $response = $this->postJson("/api/v1/public/invoices/{$invoice->payment_token}/checkout");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['invoice']);
    }

    public function test_cannot_create_checkout_session_for_cancelled_invoice(): void
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'status' => InvoiceStatus::Cancelled->value,
            'payment_token' => 'cancelled_invoice_token',
        ]);

        $response = $this->postJson("/api/v1/public/invoices/{$invoice->payment_token}/checkout");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['invoice']);
    }

    public function test_cannot_create_checkout_session_for_fully_paid_invoice(): void
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'status' => InvoiceStatus::Paid->value,
            'total_amount' => 10000,
            'amount_paid' => 10000,
            'balance_due' => 0,
            'payment_token' => 'paid_invoice_token',
        ]);

        $response = $this->postJson("/api/v1/public/invoices/{$invoice->payment_token}/checkout");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['invoice']);
    }

    public function test_cannot_create_checkout_session_if_stripe_connect_not_ready(): void
    {
        $this->connectAccount->update(['charges_enabled' => false]);

        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'status' => InvoiceStatus::Sent->value,
            'total_amount' => 10000,
            'balance_due' => 10000,
            'payment_token' => 'not_ready_connect_token',
        ]);

        $response = $this->postJson("/api/v1/public/invoices/{$invoice->payment_token}/checkout");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['stripe_connect']);
    }

    public function test_creates_checkout_session_on_connected_stripe_account_with_correct_parameters(): void
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-2026-001',
            'status' => InvoiceStatus::Sent->value,
            'total_amount' => 25000,
            'balance_due' => 25000,
            'currency' => 'USD',
            'payment_token' => 'token_for_checkout_creation',
        ]);

        // Mock Stripe Checkout Session creation routed to connected account
        $mockSession = StripeSession::constructFrom([
            'id' => 'cs_test_sample_session_123',
            'url' => 'https://checkout.stripe.com/pay/cs_test_sample_session_123',
            'payment_intent' => 'pi_test_sample_intent_123',
            'created' => time(),
        ]);

        $sessionServiceMock = Mockery::mock(SessionService::class);
        $sessionServiceMock->shouldReceive('create')
            ->once()
            ->withArgs(function ($params, $opts) {
                return $opts['stripe_account'] === 'acct_test_connected123'
                    && str_starts_with((string) ($opts['idempotency_key'] ?? ''), 'cs_PAY-')
                    && $params['mode'] === 'payment'
                    && $params['line_items'][0]['price_data']['unit_amount'] === 25000
                    && $params['metadata']['type'] === 'invoice_payment'
                    && str_contains($params['success_url'], '/result?session_id={CHECKOUT_SESSION_ID}&status=success')
                    && str_contains($params['cancel_url'], '/result?session_id={CHECKOUT_SESSION_ID}&status=cancelled');
            })
            ->andReturn($mockSession);

        $clientMock = Mockery::mock(StripeClient::class);
        $checkoutServiceMock = new \stdClass;
        $checkoutServiceMock->sessions = $sessionServiceMock;
        $clientMock->checkout = $checkoutServiceMock;

        $this->paymentService->setClient($clientMock);

        $response = $this->postJson("/api/v1/public/invoices/{$invoice->payment_token}/checkout");

        $response->assertOk()
            ->assertJsonPath('data.checkout_url', 'https://checkout.stripe.com/pay/cs_test_sample_session_123')
            ->assertJsonPath('data.session_id', 'cs_test_sample_session_123');

        $this->assertDatabaseHas('payments', [
            'invoice_id' => $invoice->id,
            'company_id' => $this->company->id,
            'stripe_checkout_session_id' => 'cs_test_sample_session_123',
            'stripe_account_id' => 'acct_test_connected123',
            'amount' => 25000,
            'status' => PaymentStatus::Pending->value,
        ]);
    }

    public function test_processes_checkout_session_completed_webhook_and_marks_invoice_paid(): void
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'status' => InvoiceStatus::Sent->value,
            'total_amount' => 30000,
            'amount_paid' => 0,
            'balance_due' => 30000,
            'payment_token' => 'token_for_completed_webhook',
        ]);

        $payment = Payment::create([
            'company_id' => $this->company->id,
            'invoice_id' => $invoice->id,
            'customer_id' => $this->customer->id,
            'payment_reference' => 'PAY-INV-30000',
            'stripe_checkout_session_id' => 'cs_test_completed_session_1',
            'stripe_account_id' => 'acct_test_connected123',
            'amount' => 30000,
            'currency' => 'USD',
            'status' => PaymentStatus::Pending,
        ]);

        $webhookPayload = [
            'id' => 'evt_test_checkout_session_completed',
            'type' => 'checkout.session.completed',
            'created' => time(),
            'account' => 'acct_test_connected123',
            'data' => [
                'object' => [
                    'id' => 'cs_test_completed_session_1',
                    'mode' => 'payment',
                    'payment_status' => 'paid',
                    'amount_total' => 30000,
                    'currency' => 'usd',
                    'payment_intent' => 'pi_test_intent_completed_1',
                    'client_reference_id' => 'PAY-INV-30000',
                    'metadata' => [
                        'type' => 'invoice_payment',
                        'company_id' => (string) $this->company->id,
                        'invoice_id' => (string) $invoice->id,
                        'payment_reference' => 'PAY-INV-30000',
                    ],
                ],
            ],
        ];

        $event = StripeWebhookEvent::create([
            'stripe_event_id' => 'evt_test_checkout_session_completed',
            'type' => 'checkout.session.completed',
            'payload' => $webhookPayload,
            'status' => 'pending',
            'stripe_event_created_at' => now(),
        ]);

        $job = new ProcessStripeWebhookJob($event->id);
        $job->handle(
            app(SubscriptionLifecycleService::class),
            app(StripeConnectService::class),
            $this->paymentService,
        );

        $payment->refresh();
        $invoice->refresh();

        $this->assertEquals(PaymentStatus::Succeeded, $payment->status);
        $this->assertEquals('pi_test_intent_completed_1', $payment->stripe_payment_intent_id);
        $this->assertEquals(30000, $invoice->amount_paid);
        $this->assertEquals(0, $invoice->balance_due);
        $this->assertEquals(InvoiceStatus::Paid, $invoice->status);
    }

    public function test_processes_partial_checkout_session_completed_webhook(): void
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'status' => InvoiceStatus::Sent->value,
            'total_amount' => 50000,
            'amount_paid' => 0,
            'balance_due' => 50000,
        ]);

        $payment = Payment::create([
            'company_id' => $this->company->id,
            'invoice_id' => $invoice->id,
            'customer_id' => $this->customer->id,
            'payment_reference' => 'PAY-PARTIAL-1',
            'stripe_checkout_session_id' => 'cs_test_partial_session_1',
            'stripe_account_id' => 'acct_test_connected123',
            'amount' => 20000,
            'currency' => 'USD',
            'status' => PaymentStatus::Pending,
        ]);

        $sessionData = [
            'id' => 'cs_test_partial_session_1',
            'mode' => 'payment',
            'payment_status' => 'paid',
            'amount_total' => 20000,
            'currency' => 'usd',
            'payment_intent' => 'pi_test_partial_intent_1',
            'client_reference_id' => 'PAY-PARTIAL-1',
            'metadata' => [
                'type' => 'invoice_payment',
                'company_id' => (string) $this->company->id,
                'invoice_id' => (string) $invoice->id,
            ],
        ];

        $this->paymentService->handleCheckoutSessionCompleted($sessionData, 'acct_test_connected123');

        $payment->refresh();
        $invoice->refresh();

        $this->assertEquals(PaymentStatus::Succeeded, $payment->status);
        $this->assertEquals(20000, $invoice->amount_paid);
        $this->assertEquals(30000, $invoice->balance_due);
        $this->assertEquals(InvoiceStatus::PartiallyPaid, $invoice->status);
    }

    public function test_webhook_idempotency_prevents_duplicate_payments_and_balance_updates(): void
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'status' => InvoiceStatus::Sent->value,
            'total_amount' => 10000,
            'amount_paid' => 0,
            'balance_due' => 10000,
        ]);

        $payment = Payment::create([
            'company_id' => $this->company->id,
            'invoice_id' => $invoice->id,
            'customer_id' => $this->customer->id,
            'payment_reference' => 'PAY-IDEMP-1',
            'stripe_checkout_session_id' => 'cs_test_idemp_1',
            'stripe_account_id' => 'acct_test_connected123',
            'amount' => 10000,
            'currency' => 'USD',
            'status' => PaymentStatus::Pending,
        ]);

        $sessionData = [
            'id' => 'cs_test_idemp_1',
            'mode' => 'payment',
            'payment_status' => 'paid',
            'amount_total' => 10000,
            'currency' => 'usd',
            'payment_intent' => 'pi_test_idemp_intent_1',
            'client_reference_id' => 'PAY-IDEMP-1',
            'metadata' => [
                'type' => 'invoice_payment',
                'company_id' => (string) $this->company->id,
                'invoice_id' => (string) $invoice->id,
            ],
        ];

        // First call
        $this->paymentService->handleCheckoutSessionCompleted($sessionData, 'acct_test_connected123');
        $invoice->refresh();
        $this->assertEquals(10000, $invoice->amount_paid);
        $this->assertEquals(0, $invoice->balance_due);

        // Duplicate call
        $this->paymentService->handleCheckoutSessionCompleted($sessionData, 'acct_test_connected123');
        $invoice->refresh();
        $this->assertEquals(10000, $invoice->amount_paid);
        $this->assertEquals(0, $invoice->balance_due);

        // Succeeded payments count should still be 1
        $this->assertEquals(1, $invoice->payments()->where('status', PaymentStatus::Succeeded)->count());
    }

    public function test_processes_checkout_session_expired_webhook(): void
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'status' => InvoiceStatus::Sent->value,
        ]);

        $payment = Payment::create([
            'company_id' => $this->company->id,
            'invoice_id' => $invoice->id,
            'customer_id' => $this->customer->id,
            'payment_reference' => 'PAY-EXPIRE-1',
            'stripe_checkout_session_id' => 'cs_test_expired_1',
            'stripe_account_id' => 'acct_test_connected123',
            'amount' => 10000,
            'currency' => 'USD',
            'status' => PaymentStatus::Pending,
        ]);

        $sessionData = [
            'id' => 'cs_test_expired_1',
        ];

        $this->paymentService->handleCheckoutSessionExpired($sessionData, 'acct_test_connected123');

        $payment->refresh();
        $this->assertEquals(PaymentStatus::Cancelled, $payment->status);
    }

    public function test_processes_payment_intent_succeeded_and_failed_webhooks(): void
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'status' => InvoiceStatus::Sent->value,
            'total_amount' => 15000,
            'balance_due' => 15000,
        ]);

        $payment = Payment::create([
            'company_id' => $this->company->id,
            'invoice_id' => $invoice->id,
            'customer_id' => $this->customer->id,
            'payment_reference' => 'PAY-INTENT-1',
            'stripe_payment_intent_id' => 'pi_test_intent_succ_1',
            'stripe_account_id' => 'acct_test_connected123',
            'amount' => 15000,
            'currency' => 'USD',
            'status' => PaymentStatus::Pending,
        ]);

        // Failed intent webhook
        $failedData = [
            'id' => 'pi_test_intent_succ_1',
            'last_payment_error' => ['message' => 'Card was declined.'],
        ];

        $this->paymentService->handlePaymentIntentFailed($failedData, 'acct_test_connected123');
        $payment->refresh();
        $this->assertEquals(PaymentStatus::Failed, $payment->status);
        $this->assertEquals('Card was declined.', $payment->failure_reason);

        // Subsequent retry succeeded intent webhook
        $succData = [
            'id' => 'pi_test_intent_succ_1',
            'amount_received' => 15000,
            'currency' => 'usd',
            'metadata' => [
                'invoice_id' => (string) $invoice->id,
                'payment_reference' => 'PAY-INTENT-1',
            ],
            'payment_method_types' => ['card'],
        ];

        $this->paymentService->handlePaymentIntentSucceeded($succData, 'acct_test_connected123');
        $payment->refresh();
        $invoice->refresh();

        $this->assertEquals(PaymentStatus::Succeeded, $payment->status);
        $this->assertEquals(InvoiceStatus::Paid, $invoice->status);
        $this->assertEquals(0, $invoice->balance_due);
    }

    public function test_company_payments_index_is_scoped_to_current_company(): void
    {
        $otherCompany = Company::factory()->create();

        // Payments for this company
        Payment::factory()->count(2)->create([
            'company_id' => $this->company->id,
        ]);

        // Payment for other company
        Payment::factory()->create([
            'company_id' => $otherCompany->id,
        ]);

        $response = $this->actingAs($this->owner)
            ->withHeaders(['X-Company-Id' => $this->company->id])
            ->getJson("/api/v1/companies/{$this->company->id}/payments");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_user_cannot_view_another_company_payment_details_idor(): void
    {
        $otherCompany = Company::factory()->create();
        $otherPayment = Payment::factory()->create([
            'company_id' => $otherCompany->id,
        ]);

        $response = $this->actingAs($this->owner)
            ->withHeaders(['X-Company-Id' => $this->company->id])
            ->getJson("/api/v1/companies/{$this->company->id}/payments/{$otherPayment->id}");

        $response->assertNotFound();
    }

    public function test_subscription_webhook_events_do_not_interfere_with_invoice_payment_events(): void
    {
        $subscriptionWebhookPayload = [
            'id' => 'evt_test_subscription_session_1',
            'type' => 'checkout.session.completed',
            'created' => time(),
            'data' => [
                'object' => [
                    'id' => 'cs_test_subscription_session_1',
                    'mode' => 'subscription',
                    'payment_status' => 'paid',
                    'subscription' => 'sub_test_sub_1',
                    'metadata' => [
                        'company_id' => (string) $this->company->id,
                    ],
                ],
            ],
        ];

        $event = StripeWebhookEvent::create([
            'stripe_event_id' => 'evt_test_subscription_session_1',
            'type' => 'checkout.session.completed',
            'payload' => $subscriptionWebhookPayload,
            'status' => 'pending',
            'stripe_event_created_at' => now(),
        ]);

        // Mock subscription lifecycle retrieval
        $job = new ProcessStripeWebhookJob($event->id);

        $mockLifecycle = Mockery::mock(SubscriptionLifecycleService::class);
        $mockLifecycle->shouldReceive('handleCheckoutSessionCompleted')
            ->once();

        $job->handle(
            $mockLifecycle,
            app(StripeConnectService::class),
            $this->paymentService,
        );

        $this->assertEquals('processed', $event->fresh()->status);
    }
}
