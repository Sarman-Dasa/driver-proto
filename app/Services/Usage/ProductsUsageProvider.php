<?php

declare(strict_types=1);

namespace App\Services\Usage;

use App\Contracts\UsageProviderInterface;
use App\Enums\ProductStatus;
use App\Models\Company;
use App\Models\Feature;

class ProductsUsageProvider implements UsageProviderInterface
{
    /**
     * Determine if this provider supports the given feature key.
     */
    public function supports(string $featureKey): bool
    {
        return $featureKey === Feature::PRODUCTS;
    }

    /**
     * Resolve the current active products and services count for the company.
     * Soft-deleted or inactive products do not count against the active plan limit.
     */
    public function getCurrentUsage(Company $company): int
    {
        return $company->products()
            ->whereNull('deleted_at')
            ->where('status', ProductStatus::Active->value)
            ->count();
    }
}
