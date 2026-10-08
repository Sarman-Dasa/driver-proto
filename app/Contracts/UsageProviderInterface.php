<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Company;

interface UsageProviderInterface
{
    /**
     * Determine if this provider supports the given feature key.
     */
    public function supports(string $featureKey): bool;

    /**
     * Resolve the current usage for the given company and feature.
     */
    public function getCurrentUsage(Company $company): int;
}
