<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Company;
use App\Models\Feature;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Services\Usage\UsageResolver;

class FeatureAccessService
{
    public function __construct(
        private readonly UsageResolver $usageResolver,
    ) {}

    /**
     * Determine if a company can use a given feature.
     * Order of evaluation:
     * 1. Company must exist and be active
     * 2. Subscription must exist
     * 3. Subscription status checked (with deterministic Free-plan fallback if expired)
     * 4. Plan and Feature exist
     * 5. Plan feature is enabled
     */
    public function canUseFeature(Company $company, string $featureKey): bool
    {
        if (! $company->isActive()) {
            return false;
        }

        $subscription = $company->currentSubscription();
        if ($subscription === null) {
            return false;
        }

        // If subscription is expired, fall back strictly to the default Free plan features
        if ($subscription->isExpired()) {
            return $this->canUseOnFreePlanFallback($featureKey);
        }

        if (! $subscription->isActive()) {
            return false;
        }

        $plan = $subscription->plan;
        if ($plan === null) {
            return false;
        }

        $planFeature = $this->getPlanFeatureForPlan($plan, $featureKey);

        return $planFeature !== null && $planFeature->is_enabled;
    }

    /**
     * Get the configured limit value for a feature in the given company.
     * Returns null if the feature is unlimited, integer limit if limited, or 0 if disabled.
     */
    public function getFeatureLimit(Company $company, string $featureKey): ?int
    {
        if (! $this->canUseFeature($company, $featureKey)) {
            return 0;
        }

        $plan = $this->getEffectivePlan($company);
        if ($plan === null) {
            return 0;
        }

        $planFeature = $this->getPlanFeatureForPlan($plan, $featureKey);
        if ($planFeature === null || ! $planFeature->is_enabled) {
            return 0;
        }

        return $planFeature->limit_value !== null ? (int) $planFeature->limit_value : null;
    }

    /**
     * Determine if the company has reached its allowed usage limit for a feature.
     */
    public function hasReachedLimit(Company $company, string $featureKey, ?int $currentUsage = null): bool
    {
        if (! $this->canUseFeature($company, $featureKey)) {
            return true;
        }

        $limit = $this->getFeatureLimit($company, $featureKey);
        if ($limit === null) {
            return false; // Unlimited
        }

        $usage = $currentUsage ?? $this->usageResolver->resolveUsage($company, $featureKey);

        return $usage >= $limit;
    }

    /**
     * Get the PlanFeature model for a company's effective plan.
     */
    public function getPlanFeature(Company $company, string $featureKey): ?PlanFeature
    {
        $plan = $this->getEffectivePlan($company);
        if ($plan === null) {
            return null;
        }

        return $this->getPlanFeatureForPlan($plan, $featureKey);
    }

    /**
     * Resolve the effective plan for a company (handling expired fallback to Free plan).
     */
    public function getEffectivePlan(Company $company): ?Plan
    {
        $subscription = $company->currentSubscription();
        if ($subscription === null) {
            return null;
        }

        if ($subscription->isExpired()) {
            return $this->getDefaultFreePlan();
        }

        if (! $subscription->isActive()) {
            return null;
        }

        return $subscription->plan;
    }

    /**
     * Find the PlanFeature relationship for a given plan and feature key.
     */
    public function getPlanFeatureForPlan(Plan $plan, string $featureKey): ?PlanFeature
    {
        /** @var Feature|null $feature */
        $feature = Feature::query()->where('key', $featureKey)->first();
        if ($feature === null) {
            return null;
        }

        /** @var PlanFeature|null $planFeature */
        $planFeature = PlanFeature::query()
            ->where('plan_id', $plan->id)
            ->where('feature_id', $feature->id)
            ->first();

        return $planFeature;
    }

    /**
     * Determine if a feature is enabled on the Free plan as fallback for expired subscriptions.
     */
    private function canUseOnFreePlanFallback(string $featureKey): bool
    {
        $freePlan = $this->getDefaultFreePlan();
        if ($freePlan === null) {
            return false;
        }

        $planFeature = $this->getPlanFeatureForPlan($freePlan, $featureKey);

        return $planFeature !== null && $planFeature->is_enabled;
    }

    /**
     * Retrieve the system default Free plan.
     */
    private function getDefaultFreePlan(): ?Plan
    {
        return Plan::query()->where('is_default', true)->first()
            ?? Plan::query()->where('slug', Plan::FREE)->first();
    }
}
