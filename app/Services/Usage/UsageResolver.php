<?php

declare(strict_types=1);

namespace App\Services\Usage;

use App\Contracts\UsageProviderInterface;
use App\Models\Company;

class UsageResolver
{
    /**
     * @var array<int, UsageProviderInterface>
     */
    private array $providers;

    /**
     * @param  array<int, UsageProviderInterface>|null  $providers
     */
    public function __construct(?array $providers = null)
    {
        $this->providers = $providers ?? [
            new TeamMembersUsageProvider,
        ];
    }

    /**
     * Register an additional usage provider.
     */
    public function registerProvider(UsageProviderInterface $provider): self
    {
        $this->providers[] = $provider;

        return $this;
    }

    /**
     * Resolve the current usage for a feature in the given company.
     */
    public function resolveUsage(Company $company, string $featureKey): int
    {
        foreach ($this->providers as $provider) {
            if ($provider->supports($featureKey)) {
                return $provider->getCurrentUsage($company);
            }
        }

        return 0;
    }
}
