<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\StripeConnect;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\StripeConnectAccountResource;
use App\Models\Company;
use App\Models\Feature;
use App\Models\User;
use App\Services\FeatureAccessService;
use App\Services\Stripe\StripeConnectService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class StripeConnectController extends Controller
{
    public function __construct(
        private readonly StripeConnectService $connectService,
        private readonly FeatureAccessService $featureAccessService,
    ) {}

    /**
     * Get current Stripe Connect status and details for the company.
     */
    public function status(Request $request, Company $company): JsonResponse
    {
        $this->authorizeStripeAccess($request, $company, 'stripe.view');

        $account = $company->stripeConnectAccount;
        $summary = $this->connectService->getAccountSummary($company);
        $featureEnabled = $this->featureAccessService->canUseFeature($company, Feature::STRIPE_CONNECT);
        /** @var User $user */
        $user = $request->user();
        $canManage = $company->isOwner($user) || $user->hasPermissionInCompany($company, 'stripe.manage');

        return ApiResponse::success([
            'summary' => $summary,
            'account' => $account ? new StripeConnectAccountResource($account) : null,
            'is_ready_for_payments' => $summary['is_ready_for_payments'],
            'feature_enabled' => $featureEnabled,
            'can_manage' => $canManage,
        ]);
    }

    /**
     * Initiate Stripe Connect Express onboarding and return account link URL.
     */
    public function onboard(Request $request, Company $company): JsonResponse
    {
        $this->authorizeStripeAccess($request, $company, 'stripe.manage');

        // Feature gating
        if (! $this->featureAccessService->canUseFeature($company, Feature::STRIPE_CONNECT)) {
            return ApiResponse::error(
                'Stripe Connect is not available on your current plan. Please upgrade to Pro or Business to accept online payments.',
                ['upgrade_required' => true],
                403
            );
        }

        $validated = $request->validate([
            'return_url' => ['nullable', 'string', 'url'],
            'refresh_url' => ['nullable', 'string', 'url'],
        ]);

        $frontendUrl = (string) config('app.frontend_url', config('app.url'));
        $defaultReturnUrl = rtrim($frontendUrl, '/').'/settings/stripe-connect?return=true';
        $defaultRefreshUrl = rtrim($frontendUrl, '/').'/settings/stripe-connect?refresh=true';

        $returnUrl = $validated['return_url'] ?? $defaultReturnUrl;
        $refreshUrl = $validated['refresh_url'] ?? $defaultRefreshUrl;

        try {
            /** @var User $user */
            $user = $request->user();
            $onboardingUrl = $this->connectService->createOnboardingLink(
                company: $company,
                returnUrl: $returnUrl,
                refreshUrl: $refreshUrl,
                user: $user,
            );

            return ApiResponse::success([
                'url' => $onboardingUrl,
                'account_id' => $company->stripeConnectAccount?->stripe_account_id,
            ], 'Stripe onboarding link created successfully.');
        } catch (Throwable $e) {
            report($e);

            return ApiResponse::error(
                'Unable to create Stripe Connect onboarding link: '.$e->getMessage(),
                [],
                500
            );
        }
    }

    /**
     * Synchronize and refresh account capabilities and requirements from Stripe.
     */
    public function sync(Request $request, Company $company): JsonResponse
    {
        $this->authorizeStripeAccess($request, $company, 'stripe.view');

        $account = $company->stripeConnectAccount;
        if (! $account || empty($account->stripe_account_id)) {
            return ApiResponse::error('No Stripe Connect account has been initialized yet.', [], 404);
        }

        try {
            $syncedAccount = $this->connectService->syncAccount($company);
            $summary = $this->connectService->getAccountSummary($company);

            return ApiResponse::success([
                'summary' => $summary,
                'account' => new StripeConnectAccountResource($syncedAccount),
                'is_ready_for_payments' => $syncedAccount->isReadyForPayments(),
            ], 'Stripe account status refreshed successfully.');
        } catch (Throwable $e) {
            report($e);

            return ApiResponse::error(
                'Failed to synchronize Stripe account: '.$e->getMessage(),
                [],
                500
            );
        }
    }

    /**
     * Generate an Express dashboard login link for the connected account.
     */
    public function dashboardLink(Request $request, Company $company): JsonResponse
    {
        $this->authorizeStripeAccess($request, $company, 'stripe.manage');

        $account = $company->stripeConnectAccount;
        if (! $account || empty($account->stripe_account_id)) {
            return ApiResponse::error('No connected Stripe account found.', [], 404);
        }

        if (! $account->details_submitted) {
            return ApiResponse::error(
                'Onboarding must be completed before accessing the Stripe Express Dashboard.',
                ['onboarding_required' => true],
                422
            );
        }

        try {
            $url = $this->connectService->createDashboardLoginLink($company);

            return ApiResponse::success([
                'url' => $url,
            ], 'Stripe Dashboard link generated.');
        } catch (Throwable $e) {
            report($e);

            return ApiResponse::error(
                'Unable to generate dashboard link: '.$e->getMessage(),
                [],
                500
            );
        }
    }

    /**
     * Safely disconnect or disable the connected Stripe account.
     */
    public function disconnect(Request $request, Company $company): JsonResponse
    {
        $this->authorizeStripeAccess($request, $company, 'stripe.manage');

        $account = $company->stripeConnectAccount;
        if (! $account || empty($account->stripe_account_id)) {
            return ApiResponse::error('No active Stripe connection to disconnect.', [], 404);
        }

        try {
            $this->connectService->disconnectAccount($company);
            $summary = $this->connectService->getAccountSummary($company);

            return ApiResponse::success([
                'summary' => $summary,
            ], 'Stripe Connect account disconnected successfully.');
        } catch (Throwable $e) {
            report($e);

            return ApiResponse::error(
                'Failed to disconnect Stripe account: '.$e->getMessage(),
                [],
                500
            );
        }
    }

    /**
     * Authorize that the authenticated user belongs to the company and has the required permission.
     */
    private function authorizeStripeAccess(Request $request, Company $company, string $permission): void
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        if (! $user->belongsToCompany($company)) {
            abort(403, 'You do not have access to this company.');
        }

        if (! $company->isOwner($user) && ! $user->hasPermissionInCompany($company, $permission)) {
            abort(403, "You do not have permission [{$permission}] for this company.");
        }
    }
}
