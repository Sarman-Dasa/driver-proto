<?php

declare(strict_types=1);

namespace App\Services\Usage;

use App\Contracts\UsageProviderInterface;
use App\Models\Company;
use App\Models\Feature;

class TeamMembersUsageProvider implements UsageProviderInterface
{
    /**
     * Determine if this provider supports the given feature key.
     */
    public function supports(string $featureKey): bool
    {
        return $featureKey === Feature::TEAM_MEMBERS;
    }

    /**
     * Resolve the current team members usage for the company.
     * Counts actual company memberships, not global users.
     */
    public function getCurrentUsage(Company $company): int
    {
        return $company->users()->count();
    }
}
