<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Stripe\Checkout\Session as StripeSession;
use Stripe\StripeClient;
use Throwable;

class InvoicePaymentService
{
    private ?StripeClient $client = null;

    /**
     * Get or instantiate the Stripe Client.
     */
    public function getClient(): StripeClient
    {
        if ($this->client === null) {
            $secret = (string) config('stripe.secret');
            if (empty($secret)) {
                throw new InvalidArgumentException('Stripe secret key is not configured.');
            }

            if (config('stripe.suppress_notices', true)) {
                putenv('STRIPE_SUPPRESS_NOTICES=true');
                $_ENV['STRIPE_SUPPRESS_NOTICES'] = 'true';
            }

            $this->client = new StripeClient($secret);
        }

        return $this->client;
    }

    /**
     * Set a custom StripeClient instance (for testing/mocking).
     */
    public function setClient(StripeClient $client): void
    {
        $this->client = $client;
    }

    /**
     * Ensure a secure payment token exists for an invoice and return it.
     */
    public function ensurePaymentToken(Invoice $invoice, bool $forceRegenerate = false): string
    {
        return $invoice->ensurePaymentToken($forceRegenerate);
    }

    /**
     * Retrieve public customer-safe details for an invoice by its payment link token.
     *
     * @return array<string, mixed>
     */
    public function getPublicInvoiceData(string $token): array
    {
        /** @var Invoice|null $invoice */
        $invoice = Invoice::with(['company.stripeConnectAccount', 'customer', 'items'])
            ->where('payment_token', $token)
            ->first();

        if (! $invoice) {
            throw ValidationException::withMessages([
                'token' => ['Invalid or non-existent invoice payment link.'],
            ]);
        }

        $isExpired = $invoice->payment_token_expires_at !== null
            && $invoice->payment_token_expires_at->isPast();

        $company = $invoice->company;
        $connectAccount = $company->stripeConnectAccount;
        $isConnectReady = $connectAccount !== null && $connectAccount->isReadyForPayments();

        $snapshot = $invoice->customer_snapshot ?? [];
        $customerName = $snapshot['name'] ?? $invoice->customer?->name ?? 'Customer';
        $customerEmail = $snapshot['email'] ?? $invoice->customer?->email ?? '';

        $items = $invoice->items->map(function ($item) {
            return [
                'id' => $item->id,
                'product_name' => $item->product_name,
                'description' => $item->description,
                'quantity' => (float) $item->quantity,
                'unit' => $item->unit,
                'unit_price' => $item->unit_price,
                'unit_price_decimal' => round($item->unit_price / 100, 2),
                'tax_rate' => (float) $item->tax_rate,
                'tax_amount' => $item->tax_amount,
                'subtotal' => $item->subtotal,
                'total' => $item->total,
                'total_decimal' => round($item->total / 100, 2),
            ];
        })->values()->all();

        return [
            'token' => $invoice->payment_token,
            'is_expired' => $isExpired,
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'status' => $invoice->status->value,
            'status_label' => $invoice->status->label(),
            'is_payable' => ! $isExpired && $invoice->isPayable() && $isConnectReady,
            'issue_date' => $invoice->issue_date->format('Y-m-d'),
            'due_date' => $invoice->due_date->format('Y-m-d'),
            'currency' => strtoupper($invoice->currency),
            'subtotal' => $invoice->subtotal,
            'subtotal_decimal' => $invoice->subtotal_decimal,
            'discount_type' => $invoice->discount_type->value,
            'discount_value' => (float) $invoice->discount_value,
            'discount_amount' => $invoice->discount_amount,
            'discount_amount_decimal' => $invoice->discount_amount_decimal,
            'tax_amount' => $invoice->tax_amount,
            'tax_amount_decimal' => $invoice->tax_amount_decimal,
            'total_amount' => $invoice->total_amount,
            'total_amount_decimal' => $invoice->total_amount_decimal,
            'amount_paid' => $invoice->amount_paid,
            'amount_paid_decimal' => $invoice->amount_paid_decimal,
            'balance_due' => $invoice->balance_due,
            'balance_due_decimal' => $invoice->balance_due_decimal,
            'notes' => $invoice->notes,
            'terms' => $invoice->terms,
            'stripe_connect_ready' => $isConnectReady,
            'stripe_publishable_key' => (string) config('stripe.key'),
            'stripe_account_id' => $connectAccount?->stripe_account_id,
            'company' => [
                'name' => $company->name,
                'email' => $company->email,
                'country' => $company->country,
                'logo_url' => $company->logo_url,
            ],
            'customer' => [
                'name' => $customerName,
                'email' => $customerEmail,
                'company_name' => $snapshot['company_name'] ?? $invoice->customer?->company_name ?? null,
            ],
            'items' => $items,
        ];
    }

    /**
     * Create a Stripe PaymentIntent for custom in-application Stripe Elements payment routed to the connected account.
     *
     * @return array{
     *     client_secret: string,
     *     payment_intent_id: string,
     *     payment_reference: string,
     *     stripe_account_id: string,
     *     publishable_key: string,
     * }
     */
    public function createPaymentIntent(Invoice $invoice): array
    {
        // 1. Validate invoice eligibility
        if ($invoice->status === InvoiceStatus::Draft) {
            throw ValidationException::withMessages([
                'invoice' => ['Draft invoices cannot be paid. The invoice must be sent first.'],
            ]);
        }

        if ($invoice->status === InvoiceStatus::Cancelled) {
            throw ValidationException::withMessages([
                'invoice' => ['Cancelled invoices cannot be paid.'],
            ]);
        }

        if ($invoice->balance_due <= 0) {
            throw ValidationException::withMessages([
                'invoice' => ['This invoice has already been fully paid.'],
            ]);
        }

        if ($invoice->payment_token_expires_at !== null && $invoice->payment_token_expires_at->isPast()) {
            throw ValidationException::withMessages([
                'token' => ['This invoice payment link has expired.'],
            ]);
        }

        // 2. Validate company Stripe Connect account readiness
        $company = $invoice->company;
        $connectAccount = $company->stripeConnectAccount;

        if (! $connectAccount || ! $connectAccount->isReadyForPayments()) {
            throw ValidationException::withMessages([
                'stripe_connect' => ['This company is currently unable to accept online card payments via Stripe.'],
            ]);
        }

        $connectedAccountId = $connectAccount->stripe_account_id;
        $customerEmail = $invoice->customer_snapshot['email'] ?? $invoice->customer?->email ?? null;

        // 3. Generate unique internal payment reference
        $paymentReference = 'PAY-'.strtoupper(Str::random(16));

        // 4. Build Stripe PaymentIntent parameters
        $intentParams = [
            'amount' => $invoice->balance_due,
            'currency' => strtolower($invoice->currency),
            'automatic_payment_methods' => [
                'enabled' => true,
            ],
            'description' => "Payment for Invoice #{$invoice->invoice_number}",
            'metadata' => [
                'type' => 'invoice_payment',
                'company_id' => (string) $company->id,
                'invoice_id' => (string) $invoice->id,
                'customer_id' => (string) ($invoice->customer_id ?? ''),
                'payment_reference' => $paymentReference,
                'platform' => 'BillFlow',
            ],
        ];

        if ($customerEmail) {
            $intentParams['receipt_email'] = $customerEmail;
        }

        try {
            $paymentIntent = $this->getClient()->paymentIntents->create(
                $intentParams,
                [
                    'stripe_account' => $connectedAccountId,
                    'idempotency_key' => 'pi_'.$paymentReference,
                ]
            );
        } catch (Throwable $e) {
            Log::error("Failed to create Stripe PaymentIntent for Invoice {$invoice->id}: {$e->getMessage()}", [
                'invoice_id' => $invoice->id,
                'company_id' => $company->id,
                'connected_account' => $connectedAccountId,
            ]);

            throw ValidationException::withMessages([
                'stripe' => ['Unable to initiate payment with Stripe: '.$e->getMessage()],
            ]);
        }

        // 5. Record local pending Payment record
        Payment::create([
            'company_id' => $company->id,
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'payment_reference' => $paymentReference,
            'stripe_payment_intent_id' => $paymentIntent->id,
            'stripe_account_id' => $connectedAccountId,
            'amount' => $invoice->balance_due,
            'currency' => strtoupper($invoice->currency),
            'status' => PaymentStatus::Pending,
            'metadata' => [
                'client_secret' => $paymentIntent->client_secret,
                'intent_created' => $paymentIntent->created ?? time(),
            ],
        ]);

        return [
            'client_secret' => (string) $paymentIntent->client_secret,
            'payment_intent_id' => (string) $paymentIntent->id,
            'payment_reference' => $paymentReference,
            'stripe_account_id' => $connectedAccountId,
            'publishable_key' => (string) config('stripe.key'),
        ];
    }

    /**
     * Create a Stripe Checkout Session for customer invoice payment routed to the company's connected account.
     *
     * @return array{
     *     checkout_url: string,
     *     session_id: string,
     *     payment_reference: string,
     * }
     */
    public function createCheckoutSession(Invoice $invoice, string $successUrl, string $cancelUrl): array
    {
        // 1. Validate invoice eligibility
        if ($invoice->status === InvoiceStatus::Draft) {
            throw ValidationException::withMessages([
                'invoice' => ['Draft invoices cannot be paid. The invoice must be sent first.'],
            ]);
        }

        if ($invoice->status === InvoiceStatus::Cancelled) {
            throw ValidationException::withMessages([
                'invoice' => ['Cancelled invoices cannot be paid.'],
            ]);
        }

        if ($invoice->balance_due <= 0) {
            throw ValidationException::withMessages([
                'invoice' => ['This invoice has already been fully paid.'],
            ]);
        }

        if ($invoice->payment_token_expires_at !== null && $invoice->payment_token_expires_at->isPast()) {
            throw ValidationException::withMessages([
                'token' => ['This invoice payment link has expired.'],
            ]);
        }

        // 2. Validate company Stripe Connect account readiness
        $company = $invoice->company;
        $connectAccount = $company->stripeConnectAccount;

        if (! $connectAccount || ! $connectAccount->isReadyForPayments()) {
            throw ValidationException::withMessages([
                'stripe_connect' => ['This company is currently unable to accept online card payments via Stripe.'],
            ]);
        }

        $connectedAccountId = $connectAccount->stripe_account_id;
        $customerEmail = $invoice->customer_snapshot['email'] ?? $invoice->customer?->email ?? null;

        // 3. Generate unique internal payment reference
        $paymentReference = 'PAY-'.strtoupper(Str::random(16));

        // 4. Build Stripe Checkout Session parameters
        $sessionParams = [
            'mode' => 'payment',
            'line_items' => [
                [
                    'price_data' => [
                        'currency' => strtolower($invoice->currency),
                        'product_data' => [
                            'name' => "Invoice #{$invoice->invoice_number}",
                            'description' => "Payment for invoice {$invoice->invoice_number} from {$company->name}",
                        ],
                        'unit_amount' => $invoice->balance_due,
                    ],
                    'quantity' => 1,
                ],
            ],
            'client_reference_id' => $paymentReference,
            'payment_intent_data' => [
                'description' => "Payment for Invoice {$invoice->invoice_number}",
                'metadata' => [
                    'company_id' => (string) $company->id,
                    'invoice_id' => (string) $invoice->id,
                    'payment_reference' => $paymentReference,
                    'platform' => 'BillFlow',
                ],
            ],
            'metadata' => [
                'type' => 'invoice_payment',
                'company_id' => (string) $company->id,
                'invoice_id' => (string) $invoice->id,
                'customer_id' => (string) ($invoice->customer_id ?? ''),
                'payment_reference' => $paymentReference,
                'platform' => 'BillFlow',
            ],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
        ];

        if ($customerEmail) {
            $sessionParams['customer_email'] = $customerEmail;
        }

        // 5. Create Checkout Session routed directly to the connected Stripe account
        try {
            /** @var StripeSession $session */
            $session = $this->getClient()->checkout->sessions->create(
                $sessionParams,
                [
                    'stripe_account' => $connectedAccountId,
                    'idempotency_key' => 'cs_'.$paymentReference,
                ]
            );
        } catch (Throwable $e) {
            Log::error("Failed to create Stripe Checkout Session for Invoice {$invoice->id}: {$e->getMessage()}", [
                'invoice_id' => $invoice->id,
                'company_id' => $company->id,
                'connected_account' => $connectedAccountId,
            ]);

            throw ValidationException::withMessages([
                'stripe' => ['Unable to initiate payment session with Stripe: '.$e->getMessage()],
            ]);
        }

        // 6. Record local pending Payment record
        Payment::create([
            'company_id' => $company->id,
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'payment_reference' => $paymentReference,
            'stripe_checkout_session_id' => $session->id,
            'stripe_payment_intent_id' => is_string($session->payment_intent) ? $session->payment_intent : null,
            'stripe_account_id' => $connectedAccountId,
            'amount' => $invoice->balance_due,
            'currency' => strtoupper($invoice->currency),
            'status' => PaymentStatus::Pending,
            'metadata' => [
                'checkout_url' => $session->url,
                'session_created' => $session->created ?? time(),
            ],
        ]);

        return [
            'checkout_url' => $session->url,
            'session_id' => $session->id,
            'payment_reference' => $paymentReference,
        ];
    }

    /**
     * Check payment status and reconcile if needed.
     *
     * @return array<string, mixed>
     */
    public function getPaymentStatus(string $token, ?string $sessionId = null, ?string $paymentIntentId = null): array
    {
        /** @var Invoice|null $invoice */
        $invoice = Invoice::with(['company.stripeConnectAccount', 'customer'])
            ->where('payment_token', $token)
            ->first();

        if (! $invoice) {
            throw ValidationException::withMessages([
                'token' => ['Invalid invoice payment link.'],
            ]);
        }

        $payment = null;
        if (! empty($paymentIntentId)) {
            $payment = Payment::where('stripe_payment_intent_id', $paymentIntentId)
                ->where('invoice_id', $invoice->id)
                ->first();

            if ($payment && $payment->status === PaymentStatus::Pending) {
                $this->reconcilePaymentIntentWithStripe($payment);
                $payment->refresh();
                $invoice->refresh();
            }
        } elseif (! empty($sessionId)) {
            $payment = Payment::where('stripe_checkout_session_id', $sessionId)
                ->where('invoice_id', $invoice->id)
                ->first();

            // If local payment record is still pending, attempt real-time reconciliation with Stripe
            if ($payment && $payment->status === PaymentStatus::Pending) {
                $this->reconcileSessionWithStripe($payment);
                $payment->refresh();
                $invoice->refresh();
            }
        }

        if (! $payment) {
            $payment = $invoice->payments()->latest('id')->first();
            if ($payment && $payment->status === PaymentStatus::Pending) {
                if (! empty($payment->stripe_payment_intent_id)) {
                    $this->reconcilePaymentIntentWithStripe($payment);
                } elseif (! empty($payment->stripe_checkout_session_id)) {
                    $this->reconcileSessionWithStripe($payment);
                }
                $payment->refresh();
                $invoice->refresh();
            }
        }

        return [
            'invoice_number' => $invoice->invoice_number,
            'invoice_status' => $invoice->status->value,
            'invoice_status_label' => $invoice->status->label(),
            'total_amount' => $invoice->total_amount,
            'total_amount_decimal' => $invoice->total_amount_decimal,
            'amount_paid' => $invoice->amount_paid,
            'amount_paid_decimal' => $invoice->amount_paid_decimal,
            'balance_due' => $invoice->balance_due,
            'balance_due_decimal' => $invoice->balance_due_decimal,
            'is_paid' => $invoice->status === InvoiceStatus::Paid,
            'payment' => $payment ? [
                'payment_reference' => $payment->payment_reference,
                'amount' => $payment->amount,
                'amount_decimal' => $payment->amount_decimal,
                'currency' => $payment->currency,
                'status' => $payment->status->value,
                'status_label' => $payment->status->label(),
                'paid_at' => $payment->paid_at?->toIso8601String(),
                'receipt_url' => $payment->receipt_url,
                'failure_reason' => $payment->failure_reason,
            ] : null,
        ];
    }

    /**
     * Reconcile a single pending payment intent with the Stripe API on the connected account.
     */
    public function reconcilePaymentIntentWithStripe(Payment $payment): void
    {
        if (empty($payment->stripe_payment_intent_id) || empty($payment->stripe_account_id)) {
            return;
        }

        try {
            $intent = $this->getClient()->paymentIntents->retrieve(
                $payment->stripe_payment_intent_id,
                [],
                ['stripe_account' => $payment->stripe_account_id]
            );

            if ($intent->status === 'succeeded') {
                $this->handlePaymentIntentSucceeded($intent->toArray(), $payment->stripe_account_id);
            } elseif ($intent->status === 'canceled') {
                $payment->status = PaymentStatus::Cancelled;
                $payment->failure_reason = 'Payment intent was canceled.';
                $payment->save();
            } elseif ($intent->last_payment_error) {
                $this->handlePaymentIntentFailed($intent->toArray(), $payment->stripe_account_id);
            }
        } catch (Throwable $e) {
            Log::info("Payment intent reconciliation skipped for {$payment->stripe_payment_intent_id}: {$e->getMessage()}");
        }
    }

    /**
     * Reconcile a single pending checkout session with the Stripe API on the connected account.
     */
    public function reconcileSessionWithStripe(Payment $payment): void
    {
        if (empty($payment->stripe_checkout_session_id) || empty($payment->stripe_account_id)) {
            return;
        }

        try {
            $session = $this->getClient()->checkout->sessions->retrieve(
                $payment->stripe_checkout_session_id,
                ['expand' => ['payment_intent']],
                ['stripe_account' => $payment->stripe_account_id]
            );

            if ($session->payment_status === 'paid') {
                $sessionData = $session->toArray();
                $this->handleCheckoutSessionCompleted($sessionData, $payment->stripe_account_id);
            } elseif ($session->status === 'expired') {
                $sessionData = $session->toArray();
                $this->handleCheckoutSessionExpired($sessionData, $payment->stripe_account_id);
            }
        } catch (Throwable $e) {
            Log::info("Session reconciliation skipped for {$payment->stripe_checkout_session_id}: {$e->getMessage()}");
        }
    }

    /**
     * Handle checkout.session.completed for customer invoice payments.
     *
     * @param  array<string, mixed>  $sessionData
     */
    public function handleCheckoutSessionCompleted(array $sessionData, ?string $connectedAccountId = null): ?Payment
    {
        $sessionId = (string) ($sessionData['id'] ?? '');
        $paymentReference = (string) ($sessionData['client_reference_id'] ?? ($sessionData['metadata']['payment_reference'] ?? ''));
        $invoiceId = (int) ($sessionData['metadata']['invoice_id'] ?? 0);
        $paymentStatus = (string) ($sessionData['payment_status'] ?? '');

        if ($paymentStatus !== 'paid' && $paymentStatus !== 'no_payment_required') {
            Log::info("Checkout session {$sessionId} received but payment_status is '{$paymentStatus}'. Skipping payment finalization.");

            return null;
        }

        return DB::transaction(function () use ($sessionId, $paymentReference, $invoiceId, $sessionData, $connectedAccountId) {
            // Find existing payment record
            $paymentQuery = Payment::query();
            if ($sessionId !== '') {
                $paymentQuery->where('stripe_checkout_session_id', $sessionId);
            } elseif ($paymentReference !== '') {
                $paymentQuery->where('payment_reference', $paymentReference);
            }

            /** @var Payment|null $payment */
            $payment = $paymentQuery->lockForUpdate()->first();

            $invoice = null;
            if ($payment) {
                $invoice = Invoice::lockForUpdate()->find($payment->invoice_id);
            } elseif ($invoiceId > 0) {
                $invoice = Invoice::lockForUpdate()->find($invoiceId);
            }

            if (! $invoice) {
                Log::warning("Cannot locate invoice for completed checkout session {$sessionId}");

                return null;
            }

            // If payment already succeeded, return without duplicate processing
            if ($payment && $payment->status === PaymentStatus::Succeeded) {
                return $payment;
            }

            $amount = (int) ($sessionData['amount_total'] ?? ($payment?->amount ?? $invoice->balance_due));
            $currency = strtoupper((string) ($sessionData['currency'] ?? ($payment?->currency ?? $invoice->currency)));
            $paymentIntentId = is_string($sessionData['payment_intent'] ?? null)
                ? $sessionData['payment_intent']
                : ($sessionData['payment_intent']['id'] ?? null);

            $paymentMethodType = $sessionData['payment_method_types'][0] ?? 'card';

            if (! $payment) {
                $payment = new Payment([
                    'company_id' => $invoice->company_id,
                    'invoice_id' => $invoice->id,
                    'customer_id' => $invoice->customer_id,
                    'payment_reference' => $paymentReference ?: 'PAY-'.strtoupper(Str::random(16)),
                ]);
            }

            $payment->stripe_checkout_session_id = $sessionId ?: $payment->stripe_checkout_session_id;
            $payment->stripe_payment_intent_id = $paymentIntentId ?: $payment->stripe_payment_intent_id;
            $payment->stripe_account_id = $connectedAccountId ?: ($payment->stripe_account_id ?: ($invoice->company->stripeConnectAccount?->stripe_account_id ?? ''));
            $payment->amount = $amount;
            $payment->currency = $currency;
            $payment->status = PaymentStatus::Succeeded;
            $payment->payment_method_type = $paymentMethodType;
            $payment->paid_at = now();
            $payment->failure_reason = null;
            $payment->save();

            // Authoritatively recalculate invoice amounts paid and status
            $this->recalculateInvoiceBalance($invoice);

            return $payment;
        });
    }

    /**
     * Handle checkout.session.expired for customer invoice payments.
     *
     * @param  array<string, mixed>  $sessionData
     */
    public function handleCheckoutSessionExpired(array $sessionData, ?string $connectedAccountId = null): ?Payment
    {
        $sessionId = (string) ($sessionData['id'] ?? '');
        if ($sessionId === '') {
            return null;
        }

        return DB::transaction(function () use ($sessionId) {
            $payment = Payment::where('stripe_checkout_session_id', $sessionId)
                ->lockForUpdate()
                ->first();

            if (! $payment || $payment->status->isFinal()) {
                return $payment;
            }

            $payment->status = PaymentStatus::Cancelled;
            $payment->failure_reason = 'Stripe Checkout Session expired before completion.';
            $payment->save();

            return $payment;
        });
    }

    /**
     * Handle payment_intent.succeeded for customer invoice payments.
     *
     * @param  array<string, mixed>  $intentData
     */
    public function handlePaymentIntentSucceeded(array $intentData, ?string $connectedAccountId = null): ?Payment
    {
        $intentId = (string) ($intentData['id'] ?? '');
        $paymentReference = (string) ($intentData['metadata']['payment_reference'] ?? '');
        $invoiceId = (int) ($intentData['metadata']['invoice_id'] ?? 0);

        return DB::transaction(function () use ($intentId, $paymentReference, $invoiceId, $intentData, $connectedAccountId) {
            $paymentQuery = Payment::query();
            if ($intentId !== '') {
                $paymentQuery->where('stripe_payment_intent_id', $intentId);
            } elseif ($paymentReference !== '') {
                $paymentQuery->where('payment_reference', $paymentReference);
            }

            /** @var Payment|null $payment */
            $payment = $paymentQuery->lockForUpdate()->first();

            $invoice = null;
            if ($payment) {
                $invoice = Invoice::lockForUpdate()->find($payment->invoice_id);
            } elseif ($invoiceId > 0) {
                $invoice = Invoice::lockForUpdate()->find($invoiceId);
            }

            if (! $invoice) {
                return null;
            }

            if ($payment && $payment->status === PaymentStatus::Succeeded) {
                return $payment;
            }

            $amount = (int) ($intentData['amount_received'] ?? ($intentData['amount'] ?? ($payment?->amount ?? $invoice->balance_due)));
            $currency = strtoupper((string) ($intentData['currency'] ?? ($payment?->currency ?? $invoice->currency)));

            $paymentMethodType = $intentData['payment_method_types'][0] ?? 'card';

            if (! $payment) {
                $payment = new Payment([
                    'company_id' => $invoice->company_id,
                    'invoice_id' => $invoice->id,
                    'customer_id' => $invoice->customer_id,
                    'payment_reference' => $paymentReference ?: 'PAY-'.strtoupper(Str::random(16)),
                ]);
            }

            $payment->stripe_payment_intent_id = $intentId ?: $payment->stripe_payment_intent_id;
            $payment->stripe_account_id = $connectedAccountId ?: ($payment->stripe_account_id ?: ($invoice->company->stripeConnectAccount?->stripe_account_id ?? ''));
            $payment->amount = $amount;
            $payment->currency = $currency;
            $payment->status = PaymentStatus::Succeeded;
            $payment->payment_method_type = $paymentMethodType;
            $payment->paid_at = now();
            $payment->failure_reason = null;
            $payment->save();

            $this->recalculateInvoiceBalance($invoice);

            return $payment;
        });
    }

    /**
     * Handle payment_intent.payment_failed for customer invoice payments.
     *
     * @param  array<string, mixed>  $intentData
     */
    public function handlePaymentIntentFailed(array $intentData, ?string $connectedAccountId = null): ?Payment
    {
        $intentId = (string) ($intentData['id'] ?? '');
        $errorMessage = $intentData['last_payment_error']['message'] ?? 'Payment attempt failed.';

        return DB::transaction(function () use ($intentId, $errorMessage) {
            $payment = Payment::where('stripe_payment_intent_id', $intentId)
                ->lockForUpdate()
                ->first();

            if (! $payment || $payment->status === PaymentStatus::Succeeded) {
                return $payment;
            }

            $payment->status = PaymentStatus::Failed;
            $payment->failure_reason = $errorMessage;
            $payment->save();

            return $payment;
        });
    }

    /**
     * Authoritatively recalculate amounts paid, balance due, and status based on succeeded payment records.
     * Uses row locking and prevents overpayment.
     */
    public function recalculateInvoiceBalance(Invoice $invoice): Invoice
    {
        $totalPaid = (int) $invoice->payments()
            ->where('status', PaymentStatus::Succeeded)
            ->sum('amount');

        $invoice->amount_paid = $totalPaid;
        $invoice->balance_due = max(0, $invoice->total_amount - $totalPaid);

        if ($invoice->balance_due === 0 && $invoice->total_amount > 0) {
            $invoice->status = InvoiceStatus::Paid;
        } elseif ($totalPaid > 0 && $invoice->balance_due > 0) {
            $invoice->status = InvoiceStatus::PartiallyPaid;
        } elseif ($invoice->status === InvoiceStatus::Paid || $invoice->status === InvoiceStatus::PartiallyPaid) {
            $invoice->status = ($invoice->due_date && $invoice->due_date->isPast())
                ? InvoiceStatus::Overdue
                : InvoiceStatus::Sent;
        }

        $invoice->save();

        return $invoice;
    }
}
