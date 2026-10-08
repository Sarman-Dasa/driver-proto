<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Company;
use App\Models\Plan;
use App\Services\Usage\UsageResolver;

class UsageLimitService
{
    public function __construct(
        private readonly FeatureAccessService $featureAccessService,
        private readonly UsageResolver $usageResolver,
    ) {}

    /**
     * Get the current usage count for the given company and feature.
     */
    public function getCurrentUsage(Company $company, string $featureKey): int
    {
        return $this->usageResolver->resolveUsage($company, $featureKey);
    }

    /**
     * Get the configured limit for the given company and feature.
     * Returns null if unlimited.
     */
    public function getLimit(Company $company, string $featureKey): ?int
    {
        return $this->featureAccessService->getFeatureLimit($company, $featureKey);
    }

    /**
     * Determine if a new record can be created under the company's current limit.
     */
    public function canCreate(Company $company, string $featureKey, ?int $currentUsage = null): bool
    {
        if (! $this->featureAccessService->canUseFeature($company, $featureKey)) {
            return false;
        }

        $limit = $this->getLimit($company, $featureKey);
        if ($limit === null) {
            return true; // Unlimited
        }

        $usage = $currentUsage ?? $this->getCurrentUsage($company, $featureKey);

        return $usage < $limit;
    }

    /**
     * Get remaining capacity for the given feature.
     * Returns null for unlimited features, or integer remaining.
     */
    public function remaining(Company $company, string $featureKey, ?int $currentUsage = null): ?int
    {
        if (! $this->featureAccessService->canUseFeature($company, $featureKey)) {
            return 0;
        }

        $limit = $this->getLimit($company, $featureKey);
        if ($limit === null) {
            return null; // Unlimited
        }

        $usage = $currentUsage ?? $this->getCurrentUsage($company, $featureKey);

        return max(0, $limit - $usage);
    }

    /**
     * Check if a company can downgrade to a target plan based on current usage.
     * Note: Data retention rule - does NOT delete any existing data!
     *
     * @return array{allowed: bool, violations: array<string, array{feature: string, current: int, limit: ?int}>}
     */
    public function canDowngrade(Company $company, Plan $targetPlan): array
    {
        $violations = [];
        $planFeatures = $targetPlan->planFeatures()->with('feature')->get();

        foreach ($planFeatures as $planFeature) {
            $featureKey = $planFeature->feature->key;
            $currentUsage = $this->getCurrentUsage($company, $featureKey);

            // If feature is disabled on target plan but the company currently has records in use
            if (! $planFeature->is_enabled && $currentUsage > 0) {
                $violations[$featureKey] = [
                    'feature' => $featureKey,
                    'current' => $currentUsage,
                    'limit' => 0,
                ];

                continue;
            }

            // If feature has a numeric limit and current usage exceeds target plan's limit
            if ($planFeature->limit_value !== null && $currentUsage > $planFeature->limit_value) {
                $violations[$featureKey] = [
                    'feature' => $featureKey,
                    'current' => $currentUsage,
                    'limit' => $planFeature->limit_value,
                ];
            }
        }

        return [
            'allowed' => empty($violations),
            'violations' => $violations,
        ];
    }
}
