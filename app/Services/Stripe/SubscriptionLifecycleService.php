<?php

declare(strict_types=1);

namespace App\Services\Stripe;

use App\Enums\SubscriptionStatus;
use App\Models\BillingHistory;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubscriptionLifecycleService
{
    /**
     * Map Stripe subscription status to internal SubscriptionStatus enum.
     */
    public function mapStripeStatus(string $stripeStatus): SubscriptionStatus
    {
        return match ($stripeStatus) {
            'active' => SubscriptionStatus::Active,
            'trialing' => SubscriptionStatus::Trialing,
            'past_due', 'unpaid' => SubscriptionStatus::PastDue,
            'canceled' => SubscriptionStatus::Cancelled,
            default => SubscriptionStatus::Expired,
        };
    }

    /**
     * Handle checkout.session.completed webhook.
     *
     * @param  array<string, mixed>  $sessionData
     */
    public function handleCheckoutSessionCompleted(array $sessionData, int $stripeEventTimestamp): void
    {
        $mode = $sessionData['mode'] ?? null;
        if ($mode !== 'subscription') {
            return;
        }

        $stripeSubscriptionId = $sessionData['subscription'] ?? null;
        if (! $stripeSubscriptionId) {
            return;
        }

        // Subscriptions are managed comprehensively in customer.subscription.created/updated,
        // but session.completed links the customer and company ID metadata if not set.
        $companyId = $sessionData['metadata']['company_id'] ?? null;
        $customerId = $sessionData['customer'] ?? null;

        $company = null;
        if ($companyId) {
            $company = Company::find($companyId);
        }

        if (! $company && $customerId) {
            $company = Company::where('stripe_customer_id', (string) $customerId)->first();
        }

        if ($company && $customerId && empty($company->stripe_customer_id)) {
            $company->stripe_customer_id = (string) $customerId;
            $company->save();
        }

        // Verify payment status before activating subscription
        $paymentStatus = $sessionData['payment_status'] ?? null;
        if ($paymentStatus !== 'paid' && $paymentStatus !== 'no_payment_required') {
            Log::info("Checkout session completed but payment_status is '{$paymentStatus}'. Skipping activation.");

            return;
        }

        // Retrieve and sync subscription state
        try {
            $stripeClient = app(StripeBillingService::class)->getClient();
            $stripeSub = $stripeClient->subscriptions->retrieve((string) $stripeSubscriptionId);
            if ($stripeSub) {
                $subData = $stripeSub->toArray();
                if ($company && ! isset($subData['metadata']['company_id'])) {
                    $subData['metadata']['company_id'] = (string) $company->id;
                }
                if (! empty($sessionData['metadata']['plan_id']) && ! isset($subData['metadata']['plan_id'])) {
                    $subData['metadata']['plan_id'] = (string) $sessionData['metadata']['plan_id'];
                }

                $this->handleSubscriptionCreatedOrUpdated($subData, $stripeEventTimestamp);
            }
        } catch (\Throwable $e) {
            Log::error("Failed to sync Stripe subscription {$stripeSubscriptionId} in checkout.session.completed: {$e->getMessage()}");
        }
    }

    /**
     * Handle customer.subscription.created and customer.subscription.updated.
     *
     * @param  array<string, mixed>  $data
     */
    public function handleSubscriptionCreatedOrUpdated(array $data, int $stripeEventTimestamp): void
    {
        DB::transaction(function () use ($data, $stripeEventTimestamp): void {
            $stripeSubId = (string) ($data['id'] ?? '');
            if ($stripeSubId === '') {
                return;
            }

            // Find existing subscription by Stripe subscription ID
            $subscription = Subscription::where('stripe_subscription_id', $stripeSubId)->first();

            // Guard against out-of-order events
            if ($subscription && $subscription->last_stripe_event_created_at) {
                if ($stripeEventTimestamp < $subscription->last_stripe_event_created_at->timestamp) {
                    Log::info("Skipping out-of-order Stripe event for subscription {$stripeSubId}. Event time: {$stripeEventTimestamp}, Last processed: {$subscription->last_stripe_event_created_at->timestamp}");

                    return;
                }
            }

            // Determine Company
            $company = null;
            if ($subscription) {
                $company = $subscription->company;
            } else {
                $companyId = $data['metadata']['company_id'] ?? null;
                if ($companyId) {
                    $company = Company::find($companyId);
                }

                if (! $company && ! empty($data['customer'])) {
                    $company = Company::where('stripe_customer_id', (string) $data['customer'])->first();
                }
            }

            if (! $company) {
                Log::warning("Cannot locate company for Stripe subscription {$stripeSubId}");

                return;
            }

            // Determine Plan from price ID
            $priceId = $data['items']['data'][0]['price']['id'] ?? null;
            $plan = null;
            $interval = 'monthly';

            if ($priceId) {
                $plan = Plan::where('stripe_monthly_price_id', $priceId)
                    ->orWhere('stripe_yearly_price_id', $priceId)
                    ->first();

                if ($plan) {
                    $interval = ($plan->stripe_yearly_price_id === $priceId) ? 'yearly' : 'monthly';
                }
            }

            // Fallback: lookup plan from metadata
            if (! $plan && ! empty($data['metadata']['plan_id'])) {
                $plan = Plan::find($data['metadata']['plan_id']);
            }

            // Default to Free plan if none resolved
            if (! $plan) {
                $plan = Plan::where('slug', Plan::FREE)->first() ?? Plan::first();
            }

            $status = $this->mapStripeStatus((string) ($data['status'] ?? 'active'));

            $periodStart = isset($data['current_period_start'])
                ? Carbon::createFromTimestamp((int) $data['current_period_start'])
                : null;
            $periodEnd = isset($data['current_period_end'])
                ? Carbon::createFromTimestamp((int) $data['current_period_end'])
                : null;
            $trialEnd = isset($data['trial_end']) && $data['trial_end'] !== null
                ? Carbon::createFromTimestamp((int) $data['trial_end'])
                : null;
            $canceledAt = isset($data['canceled_at']) && $data['canceled_at'] !== null
                ? Carbon::createFromTimestamp((int) $data['canceled_at'])
                : null;
            $cancelAtPeriodEnd = (bool) ($data['cancel_at_period_end'] ?? false);

            if (! $subscription) {
                // Check if company has an existing subscription with null stripe_subscription_id (e.g. Free plan)
                $existingFreeSub = $company->subscriptions()
                    ->whereNull('stripe_subscription_id')
                    ->latest('id')
                    ->first();

                if ($existingFreeSub) {
                    $subscription = $existingFreeSub;
                } else {
                    $subscription = new Subscription([
                        'company_id' => $company->id,
                    ]);
                }
            }

            // Prevent duplicate active subscriptions: expire any other subscriptions for this company
            $company->subscriptions()
                ->where('id', '!=', $subscription->id ?? 0)
                ->where('status', SubscriptionStatus::Active)
                ->update(['status' => SubscriptionStatus::Expired]);

            $subscription->company_id = $company->id;
            $subscription->plan_id = $plan->id;
            $subscription->stripe_subscription_id = $stripeSubId;
            $subscription->stripe_price_id = $priceId;
            $subscription->billing_interval = $interval;
            $subscription->status = $status;
            $subscription->current_period_start = $periodStart;
            $subscription->current_period_end = $periodEnd;
            $subscription->trial_ends_at = $trialEnd;
            $subscription->cancel_at_period_end = $cancelAtPeriodEnd;
            $subscription->cancelled_at = $canceledAt;
            $subscription->last_stripe_event_created_at = Carbon::createFromTimestamp($stripeEventTimestamp);

            if ($subscription->starts_at === null) {
                $subscription->starts_at = $periodStart ?? now();
            }

            $subscription->save();
        });
    }

    /**
     * Handle customer.subscription.deleted.
     *
     * @param  array<string, mixed>  $data
     */
    public function handleSubscriptionDeleted(array $data, int $stripeEventTimestamp): void
    {
        DB::transaction(function () use ($data, $stripeEventTimestamp): void {
            $stripeSubId = (string) ($data['id'] ?? '');
            if ($stripeSubId === '') {
                return;
            }

            $subscription = Subscription::where('stripe_subscription_id', $stripeSubId)->first();
            if (! $subscription) {
                return;
            }

            if ($subscription->last_stripe_event_created_at) {
                if ($stripeEventTimestamp < $subscription->last_stripe_event_created_at->timestamp) {
                    Log::info("Skipping out-of-order delete event for subscription {$stripeSubId}");

                    return;
                }
            }

            $subscription->status = SubscriptionStatus::Cancelled;
            $subscription->cancel_at_period_end = false;
            $subscription->cancelled_at = isset($data['canceled_at']) && $data['canceled_at'] !== null
                ? Carbon::createFromTimestamp((int) $data['canceled_at'])
                : now();
            $subscription->ends_at = now();
            $subscription->last_stripe_event_created_at = Carbon::createFromTimestamp($stripeEventTimestamp);
            $subscription->save();
        });
    }

    /**
     * Handle invoice.paid event — Record verified payment in billing_histories.
     *
     * @param  array<string, mixed>  $data
     */
    public function handleInvoicePaid(array $data, string $eventId, int $stripeEventTimestamp): void
    {
        DB::transaction(function () use ($data, $eventId, $stripeEventTimestamp): void {
            $invoiceId = (string) ($data['id'] ?? '');

            // Deduplication
            if ($invoiceId !== '' && BillingHistory::where('stripe_invoice_id', $invoiceId)->where('type', 'payment')->exists()) {
                return;
            }

            $company = null;
            $customerId = $data['customer'] ?? null;
            if ($customerId) {
                $company = Company::where('stripe_customer_id', (string) $customerId)->first();
            }

            $subscription = null;
            $stripeSubId = $data['subscription'] ?? null;
            if ($stripeSubId) {
                $subscription = Subscription::where('stripe_subscription_id', (string) $stripeSubId)->first();
                if (! $company && $subscription) {
                    $company = $subscription->company;
                }
            }

            if (! $company) {
                Log::warning("Cannot locate company for paid invoice {$invoiceId}");

                return;
            }

            BillingHistory::create([
                'company_id' => $company->id,
                'subscription_id' => $subscription?->id,
                'stripe_invoice_id' => $invoiceId,
                'stripe_payment_intent_id' => $data['payment_intent'] ?? null,
                'stripe_event_id' => $eventId,
                'type' => 'payment',
                'amount' => (int) ($data['amount_paid'] ?? 0),
                'currency' => (string) ($data['currency'] ?? 'usd'),
                'status' => 'paid',
                'billing_reason' => $data['billing_reason'] ?? null,
                'hosted_invoice_url' => $data['hosted_invoice_url'] ?? null,
                'invoice_pdf_url' => $data['invoice_pdf'] ?? null,
                'description' => "Invoice payment: {$invoiceId}",
                'event_at' => Carbon::createFromTimestamp($stripeEventTimestamp),
            ]);
        });
    }

    /**
     * Handle invoice.payment_failed event — Record failed billing attempt in billing_histories.
     *
     * @param  array<string, mixed>  $data
     */
    public function handleInvoicePaymentFailed(array $data, string $eventId, int $stripeEventTimestamp): void
    {
        DB::transaction(function () use ($data, $eventId, $stripeEventTimestamp): void {
            $invoiceId = (string) ($data['id'] ?? '');

            if ($eventId !== '' && BillingHistory::where('stripe_event_id', $eventId)->exists()) {
                return;
            }

            $company = null;
            $customerId = $data['customer'] ?? null;
            if ($customerId) {
                $company = Company::where('stripe_customer_id', (string) $customerId)->first();
            }

            $subscription = null;
            $stripeSubId = $data['subscription'] ?? null;
            if ($stripeSubId) {
                $subscription = Subscription::where('stripe_subscription_id', (string) $stripeSubId)->first();
                if (! $company && $subscription) {
                    $company = $subscription->company;
                }

                // Update subscription status to PastDue if active
                if ($subscription && $subscription->status === SubscriptionStatus::Active) {
                    $subscription->status = SubscriptionStatus::PastDue;
                    $subscription->save();
                }
            }

            if (! $company) {
                Log::warning("Cannot locate company for failed invoice {$invoiceId}");

                return;
            }

            BillingHistory::create([
                'company_id' => $company->id,
                'subscription_id' => $subscription?->id,
                'stripe_invoice_id' => $invoiceId,
                'stripe_payment_intent_id' => $data['payment_intent'] ?? null,
                'stripe_event_id' => $eventId,
                'type' => 'payment_failed',
                'amount' => (int) ($data['amount_due'] ?? 0),
                'currency' => (string) ($data['currency'] ?? 'usd'),
                'status' => 'failed',
                'billing_reason' => $data['billing_reason'] ?? null,
                'hosted_invoice_url' => $data['hosted_invoice_url'] ?? null,
                'invoice_pdf_url' => $data['invoice_pdf'] ?? null,
                'description' => "Payment failed for invoice: {$invoiceId}",
                'event_at' => Carbon::createFromTimestamp($stripeEventTimestamp),
            ]);
        });
    }

    /**
     * Handle charge.refunded event — Record refund in billing_histories without affecting subscription status.
     *
     * @param  array<string, mixed>  $data
     */
    public function handleChargeRefunded(array $data, string $eventId, int $stripeEventTimestamp): void
    {
        DB::transaction(function () use ($data, $eventId, $stripeEventTimestamp): void {
            if ($eventId !== '' && BillingHistory::where('stripe_event_id', $eventId)->exists()) {
                return;
            }

            $customerId = $data['customer'] ?? null;
            $company = null;
            if ($customerId) {
                $company = Company::where('stripe_customer_id', (string) $customerId)->first();
            }

            if (! $company) {
                Log::warning("Cannot locate company for refunded charge {$data['id']}");

                return;
            }

            $subscription = $company->currentSubscription();

            BillingHistory::create([
                'company_id' => $company->id,
                'subscription_id' => $subscription?->id,
                'stripe_invoice_id' => $data['invoice'] ?? null,
                'stripe_payment_intent_id' => $data['payment_intent'] ?? null,
                'stripe_event_id' => $eventId,
                'type' => 'refund',
                'amount' => (int) ($data['amount_refunded'] ?? 0),
                'currency' => (string) ($data['currency'] ?? 'usd'),
                'status' => 'refunded',
                'billing_reason' => null,
                'hosted_invoice_url' => null,
                'invoice_pdf_url' => null,
                'description' => "Refund issued for charge: {$data['id']}",
                'event_at' => Carbon::createFromTimestamp($stripeEventTimestamp),
            ]);
        });
    }
}
