<?php

declare(strict_types=1);

namespace App\Services\Stripe;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use InvalidArgumentException;
use Stripe\BillingPortal\Session as PortalSession;
use Stripe\Checkout\Session as CheckoutSession;
use Stripe\Customer;
use Stripe\StripeClient;
use Stripe\Subscription as StripeSubscription;

class StripeBillingService
{
    private ?StripeClient $client = null;

    public function __construct(
        ?StripeClient $client = null,
    ) {
        $this->client = $client;
    }

    /**
     * Get or lazy-instantiate the Stripe client.
     */
    public function getClient(): StripeClient
    {
        if ($this->client === null) {
            $secret = (string) config('stripe.secret', config('services.stripe.secret'));
            $this->client = new StripeClient($secret);
        }

        return $this->client;
    }

    /**
     * Set a custom StripeClient instance (useful for testing and mocking).
     */
    public function setClient(StripeClient $client): self
    {
        $this->client = $client;

        return $this;
    }

    /**
     * Get an existing Stripe Customer or create one for the company.
     */
    public function getOrCreateCustomer(Company $company): Customer
    {
        $client = $this->getClient();

        if ($company->stripe_customer_id !== null && $company->stripe_customer_id !== '') {
            try {
                $customer = $client->customers->retrieve($company->stripe_customer_id);
                if (! isset($customer->deleted) || ! $customer->deleted) {
                    return $customer;
                }
            } catch (\Throwable) {
                // If retrieval fails (e.g., deleted in Stripe), fall through to recreate
            }
        }

        $customer = $client->customers->create([
            'name' => $company->name,
            'email' => $company->email ?? $company->owner?->email,
            'metadata' => [
                'company_id' => (string) $company->id,
                'company_slug' => $company->slug,
            ],
        ]);

        $company->stripe_customer_id = $customer->id;
        $company->save();

        return $customer;
    }

    /**
     * Create a Stripe Checkout Session for subscription upgrade or renewal.
     */
    public function createCheckoutSession(
        Company $company,
        Plan $plan,
        string $billingInterval,
        string $successUrl,
        string $cancelUrl,
    ): CheckoutSession {
        $priceId = $this->resolvePriceId($plan, $billingInterval);

        $customer = $this->getOrCreateCustomer($company);

        return $this->getClient()->checkout->sessions->create([
            'customer' => $customer->id,
            'mode' => 'subscription',
            'line_items' => [
                [
                    'price' => $priceId,
                    'quantity' => 1,
                ],
            ],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'client_reference_id' => (string) $company->id,
            'metadata' => [
                'company_id' => (string) $company->id,
                'plan_id' => (string) $plan->id,
                'billing_interval' => $billingInterval,
            ],
            'subscription_data' => [
                'metadata' => [
                    'company_id' => (string) $company->id,
                    'plan_id' => (string) $plan->id,
                    'billing_interval' => $billingInterval,
                ],
            ],
        ]);
    }

    /**
     * Create a Stripe Billing Customer Portal session for managing invoices and payment methods.
     */
    public function createCustomerPortalSession(Company $company, string $returnUrl): PortalSession
    {
        $customer = $this->getOrCreateCustomer($company);

        return $this->getClient()->billingPortal->sessions->create([
            'customer' => $customer->id,
            'return_url' => $returnUrl,
        ]);
    }

    /**
     * Cancel an active Stripe subscription (at period end or immediately).
     */
    public function cancelSubscription(Subscription $subscription, bool $immediately = false): StripeSubscription
    {
        if (empty($subscription->stripe_subscription_id)) {
            throw new InvalidArgumentException('Subscription does not have a linked Stripe subscription ID.');
        }

        $client = $this->getClient();

        if ($immediately) {
            return $client->subscriptions->cancel($subscription->stripe_subscription_id);
        }

        return $client->subscriptions->update($subscription->stripe_subscription_id, [
            'cancel_at_period_end' => true,
        ]);
    }

    /**
     * Resume a cancelled subscription that is set to cancel at period end.
     */
    public function resumeSubscription(Subscription $subscription): StripeSubscription
    {
        if (empty($subscription->stripe_subscription_id)) {
            throw new InvalidArgumentException('Subscription does not have a linked Stripe subscription ID.');
        }

        return $this->getClient()->subscriptions->update($subscription->stripe_subscription_id, [
            'cancel_at_period_end' => false,
        ]);
    }

    /**
     * Resolve a valid Stripe Price ID for the plan and interval.
     * If the configured price does not exist in Stripe, it is automatically
     * provisioned in the connected Stripe account and saved to the database.
     */
    public function resolvePriceId(Plan $plan, string $billingInterval): string
    {
        $priceId = $billingInterval === 'yearly'
            ? $plan->stripe_yearly_price_id
            : $plan->stripe_monthly_price_id;

        if (! empty($priceId) && str_starts_with($priceId, 'price_')) {
            try {
                $retrieved = $this->getClient()->prices->retrieve($priceId);
                if ($retrieved && $retrieved->active) {
                    return $retrieved->id;
                }
            } catch (\Throwable) {
                // If price does not exist in Stripe, fall through to provision automatically
            }
        }

        return $this->provisionStripePrice($plan, $billingInterval);
    }

    /**
     * Automatically provision or retrieve matching Stripe Product and recurring Price.
     */
    public function provisionStripePrice(Plan $plan, string $billingInterval): string
    {
        $client = $this->getClient();

        // 1. Find or create Stripe Product
        $product = null;
        try {
            $existingProducts = $client->products->all(['limit' => 50, 'active' => true]);
            foreach ($existingProducts->data as $p) {
                if (
                    ($p->metadata['plan_slug'] ?? null) === $plan->slug
                    || $p->name === "BillFlow {$plan->name}"
                ) {
                    $product = $p;
                    break;
                }
            }
        } catch (\Throwable) {
            // ignore
        }

        if (! $product) {
            $product = $client->products->create([
                'name' => "BillFlow {$plan->name}",
                'description' => $plan->description ?? "BillFlow {$plan->name} subscription",
                'metadata' => [
                    'plan_id' => (string) $plan->id,
                    'plan_slug' => $plan->slug,
                ],
            ]);
        }

        // 2. Check existing prices on product
        $intervalName = $billingInterval === 'yearly' ? 'year' : 'month';
        $amount = $billingInterval === 'yearly' ? (int) $plan->price_yearly : (int) $plan->price_monthly;
        $currency = strtolower($plan->currency ?: 'usd');

        try {
            $existingPrices = $client->prices->all([
                'product' => $product->id,
                'active' => true,
                'limit' => 20,
            ]);

            foreach ($existingPrices->data as $price) {
                if (
                    $price->unit_amount === $amount
                    && strtolower($price->currency) === $currency
                    && ($price->recurring->interval ?? null) === $intervalName
                ) {
                    $this->updatePlanStripePriceId($plan, $billingInterval, $price->id);

                    return $price->id;
                }
            }
        } catch (\Throwable) {
            // ignore
        }

        // 3. Create the new recurring Price
        $newPrice = $client->prices->create([
            'product' => $product->id,
            'unit_amount' => $amount,
            'currency' => $currency,
            'recurring' => [
                'interval' => $intervalName,
            ],
            'metadata' => [
                'plan_id' => (string) $plan->id,
                'plan_slug' => $plan->slug,
                'billing_interval' => $billingInterval,
            ],
        ]);

        $this->updatePlanStripePriceId($plan, $billingInterval, $newPrice->id);

        return $newPrice->id;
    }

    protected function updatePlanStripePriceId(Plan $plan, string $billingInterval, string $priceId): void
    {
        if ($billingInterval === 'yearly') {
            $plan->stripe_yearly_price_id = $priceId;
        } else {
            $plan->stripe_monthly_price_id = $priceId;
        }
        $plan->save();
    }
}
