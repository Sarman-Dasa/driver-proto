<?php

declare(strict_types=1);

namespace App\Services\Usage;

use App\Contracts\UsageProviderInterface;
use App\Enums\CustomerStatus;
use App\Models\Company;
use App\Models\Feature;

class CustomersUsageProvider implements UsageProviderInterface
{
    /**
     * Determine if this provider supports the given feature key.
     */
    public function supports(string $featureKey): bool
    {
        return $featureKey === Feature::CUSTOMERS;
    }

    /**
     * Resolve the current active customers count for the company.
     * Soft-deleted or archived customers do not count against the active plan limit.
     */
    public function getCurrentUsage(Company $company): int
    {
        return $company->customers()
            ->whereNull('deleted_at')
            ->where('status', CustomerStatus::Active->value)
            ->count();
    }
}
