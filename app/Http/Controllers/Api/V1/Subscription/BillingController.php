<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Subscription;

use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\BillingHistoryResource;
use App\Http\Resources\Api\V1\SubscriptionResource;
use App\Models\Company;
use App\Models\Plan;
use App\Models\User;
use App\Services\Stripe\StripeBillingService;
use App\Services\Stripe\SubscriptionLifecycleService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class BillingController extends Controller
{
    public function __construct(
        private readonly StripeBillingService $stripeService,
        private readonly SubscriptionLifecycleService $lifecycleService,
    ) {}

    /**
     * Get current subscription and billing summary for the company.
     */
    public function show(Request $request, Company $company): JsonResponse
    {
        $this->authorizeBillingAccess($request, $company, 'billing.view');

        $subscription = $company->currentSubscription();
        if ($subscription) {
            $subscription->load('plan.planFeatures.feature');
        }

        return ApiResponse::success([
            'subscription' => $subscription ? new SubscriptionResource($subscription) : null,
            'has_stripe_customer' => ! empty($company->stripe_customer_id),
            'currency' => $company->currency ?? 'USD',
        ]);
    }

    /**
     * Create a Stripe Checkout session for subscription upgrade or renewal.
     */
    public function checkout(Request $request, Company $company): JsonResponse
    {
        $this->authorizeBillingAccess($request, $company, 'billing.manage');

        $validated = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'interval' => ['required', 'string', 'in:monthly,yearly'],
            'success_url' => ['nullable', 'string', 'url'],
            'cancel_url' => ['nullable', 'string', 'url'],
        ]);

        /** @var Plan $plan */
        $plan = Plan::findOrFail($validated['plan_id']);

        if ($plan->isFree()) {
            return ApiResponse::error('Free plan cannot be purchased via Stripe checkout.', [], 422);
        }

        $appUrl = config('app.url', 'http://localhost');
        $successUrl = $validated['success_url'] ?? "{$appUrl}/subscription/success?session_id={CHECKOUT_SESSION_ID}";
        $cancelUrl = $validated['cancel_url'] ?? "{$appUrl}/subscription/cancel";

        try {
            $session = $this->stripeService->createCheckoutSession(
                $company,
                $plan,
                $validated['interval'],
                $successUrl,
                $cancelUrl
            );

            return ApiResponse::success([
                'checkout_url' => $session->url,
                'session_id' => $session->id,
            ]);
        } catch (Throwable $e) {
            return ApiResponse::error('Unable to create checkout session: '.$e->getMessage(), [], 500);
        }
    }

    /**
     * Create a Stripe Billing Customer Portal session.
     */
    public function portal(Request $request, Company $company): JsonResponse
    {
        $this->authorizeBillingAccess($request, $company, 'billing.manage');

        $validated = $request->validate([
            'return_url' => ['nullable', 'string', 'url'],
        ]);

        $appUrl = config('app.url', 'http://localhost');
        $returnUrl = $validated['return_url'] ?? "{$appUrl}/settings/subscription";

        try {
            $session = $this->stripeService->createCustomerPortalSession($company, $returnUrl);

            return ApiResponse::success([
                'portal_url' => $session->url,
            ]);
        } catch (Throwable $e) {
            return ApiResponse::error('Unable to create billing portal session: '.$e->getMessage(), [], 500);
        }
    }

    /**
     * Verify a completed Stripe checkout session and synchronize the subscription.
     */
    public function verifySession(Request $request, Company $company): JsonResponse
    {
        $this->authorizeBillingAccess($request, $company, 'billing.manage');

        $validated = $request->validate([
            'session_id' => ['required', 'string', 'starts_with:cs_'],
        ]);

        try {
            $session = $this->stripeService->getClient()->checkout->sessions->retrieve(
                $validated['session_id'],
                ['expand' => ['subscription']]
            );

            // Tenant isolation verification: ensure the session was created for this company
            $sessionCompanyId = (int) ($session->metadata->company_id ?? $session->client_reference_id ?? 0);
            if ($sessionCompanyId !== (int) $company->id) {
                return ApiResponse::error('This checkout session does not belong to this workspace.', [], 403);
            }

            // Verify payment state
            if ($session->status !== 'complete' || ($session->payment_status !== 'paid' && $session->payment_status !== 'no_payment_required')) {
                return ApiResponse::error(
                    'Checkout session is not complete or payment is still pending.',
                    [
                        'status' => $session->status,
                        'payment_status' => $session->payment_status,
                    ],
                    422
                );
            }

            // Ensure company has stripe_customer_id recorded
            if (! empty($session->customer) && empty($company->stripe_customer_id)) {
                $company->update(['stripe_customer_id' => (string) $session->customer]);
            }

            // Synchronize the verified subscription
            $stripeSub = $session->subscription;
            if (is_string($stripeSub) && ! empty($stripeSub)) {
                $stripeSub = $this->stripeService->getClient()->subscriptions->retrieve($stripeSub);
            }

            if ($stripeSub) {
                $subData = is_array($stripeSub) ? $stripeSub : $stripeSub->toArray();
                $subData['metadata']['company_id'] = (string) $company->id;
                if (! empty($session->metadata->plan_id)) {
                    $subData['metadata']['plan_id'] = (string) $session->metadata->plan_id;
                }

                $this->lifecycleService->handleSubscriptionCreatedOrUpdated(
                    $subData,
                    (int) ($stripeSub->created ?? time())
                );
            }

            $subscription = $company->fresh()->currentSubscription();
            if ($subscription) {
                $subscription->load('plan.planFeatures.feature');
            }

            return ApiResponse::success([
                'subscription' => $subscription ? new SubscriptionResource($subscription) : null,
                'has_stripe_customer' => ! empty($company->stripe_customer_id),
                'currency' => $company->currency ?? 'USD',
            ], 'Subscription verified and synchronized successfully.');
        } catch (Throwable $e) {
            return ApiResponse::error('Unable to verify checkout session: '.$e->getMessage(), [], 500);
        }
    }

    /**
     * Reconcile/sync company subscription directly from Stripe.
     */
    public function sync(Request $request, Company $company): JsonResponse
    {
        $this->authorizeBillingAccess($request, $company, 'billing.manage');

        if (empty($company->stripe_customer_id)) {
            $subscription = $company->currentSubscription();
            if ($subscription) {
                $subscription->load('plan.planFeatures.feature');
            }

            return ApiResponse::success([
                'subscription' => $subscription ? new SubscriptionResource($subscription) : null,
                'has_stripe_customer' => false,
                'currency' => $company->currency ?? 'USD',
            ], 'No Stripe customer associated with this workspace.');
        }

        try {
            $stripeSubs = $this->stripeService->getClient()->subscriptions->all([
                'customer' => $company->stripe_customer_id,
                'status' => 'all',
                'limit' => 5,
            ]);

            // Find active or latest subscription
            $activeSub = null;
            foreach ($stripeSubs->data as $sub) {
                if ($sub->status === 'active' || $sub->status === 'trialing') {
                    $activeSub = $sub;
                    break;
                }
            }

            if ($activeSub) {
                $subData = $activeSub->toArray();
                $subData['metadata']['company_id'] = (string) $company->id;
                $this->lifecycleService->handleSubscriptionCreatedOrUpdated($subData, (int) $activeSub->created);
            }

            $subscription = $company->fresh()->currentSubscription();
            if ($subscription) {
                $subscription->load('plan.planFeatures.feature');
            }

            return ApiResponse::success([
                'subscription' => $subscription ? new SubscriptionResource($subscription) : null,
                'has_stripe_customer' => true,
                'currency' => $company->currency ?? 'USD',
            ], 'Subscription synchronized from Stripe.');
        } catch (Throwable $e) {
            return ApiResponse::error('Unable to sync with Stripe: '.$e->getMessage(), [], 500);
        }
    }

    /**
     * Cancel the company's active Stripe subscription.
     */
    public function cancel(Request $request, Company $company): JsonResponse
    {
        $this->authorizeBillingAccess($request, $company, 'billing.manage');

        $subscription = $company->currentSubscription();

        if (! $subscription || empty($subscription->stripe_subscription_id)) {
            return ApiResponse::error('Company does not have an active Stripe subscription to cancel.', [], 400);
        }

        $immediately = (bool) $request->boolean('immediately');

        try {
            $this->stripeService->cancelSubscription($subscription, $immediately);

            if ($immediately) {
                $subscription->status = SubscriptionStatus::Cancelled;
                $subscription->cancel_at_period_end = false;
                $subscription->cancelled_at = now();
                $subscription->ends_at = now();
            } else {
                $subscription->cancel_at_period_end = true;
                $subscription->cancelled_at = now();
            }

            $subscription->save();
            $subscription->load('plan');

            return ApiResponse::success([
                'message' => $immediately ? 'Subscription cancelled immediately.' : 'Subscription scheduled to cancel at end of billing cycle.',
                'subscription' => new SubscriptionResource($subscription),
            ]);
        } catch (Throwable $e) {
            return ApiResponse::error('Unable to cancel subscription: '.$e->getMessage(), [], 500);
        }
    }

    /**
     * Resume a subscription currently marked to cancel at period end.
     */
    public function resume(Request $request, Company $company): JsonResponse
    {
        $this->authorizeBillingAccess($request, $company, 'billing.manage');

        $subscription = $company->currentSubscription();

        if (! $subscription || empty($subscription->stripe_subscription_id)) {
            return ApiResponse::error('Company does not have an active Stripe subscription.', [], 400);
        }

        if (! $subscription->cancel_at_period_end) {
            return ApiResponse::error('Subscription is not scheduled for cancellation.', [], 400);
        }

        try {
            $this->stripeService->resumeSubscription($subscription);

            $subscription->cancel_at_period_end = false;
            $subscription->cancelled_at = null;
            $subscription->save();
            $subscription->load('plan');

            return ApiResponse::success([
                'message' => 'Subscription successfully resumed.',
                'subscription' => new SubscriptionResource($subscription),
            ]);
        } catch (Throwable $e) {
            return ApiResponse::error('Unable to resume subscription: '.$e->getMessage(), [], 500);
        }
    }

    /**
     * Retrieve billing history events (payments, failures, refunds).
     */
    public function history(Request $request, Company $company): JsonResponse
    {
        $this->authorizeBillingAccess($request, $company, 'billing.view');

        $perPage = min(max((int) $request->query('per_page', 15), 5), 100);

        $history = $company->billingHistories()
            ->latest('event_at')
            ->paginate($perPage);

        return ApiResponse::success([
            'data' => BillingHistoryResource::collection($history->items()),
            'pagination' => [
                'current_page' => $history->currentPage(),
                'per_page' => $history->perPage(),
                'total' => $history->total(),
                'last_page' => $history->lastPage(),
            ],
        ]);
    }

    /**
     * Rigorous authorization check for company billing actions.
     */
    private function authorizeBillingAccess(Request $request, Company $company, string $permission): void
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        if (! $user->belongsToCompany($company)) {
            abort(403, 'You do not have access to this company.');
        }

        if (! $user->hasPermissionInCompany($company, $permission) && ! $company->isOwner($user)) {
            abort(403, 'You do not have permission to manage billing for this company.');
        }
    }
}
