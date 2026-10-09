<?php

declare(strict_types=1);

namespace App\Services\Usage;

use App\Contracts\UsageProviderInterface;
use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\Feature;

class InvoicesUsageProvider implements UsageProviderInterface
{
    /**
     * Determine if this provider supports the given feature key.
     */
    public function supports(string $featureKey): bool
    {
        return $featureKey === Feature::INVOICES;
    }

    /**
     * Resolve the current monthly active invoices count for the company.
     * Soft-deleted invoices and cancelled invoices do not count against the limit.
     * Evaluated against the active subscription billing period, or current calendar month.
     */
    public function getCurrentUsage(Company $company): int
    {
        $subscription = $company->currentSubscription();

        if ($subscription && $subscription->current_period_start && $subscription->current_period_end) {
            $start = $subscription->current_period_start;
            $end = $subscription->current_period_end;
        } else {
            $start = now()->startOfMonth();
            $end = now()->endOfMonth();
        }

        return $company->invoices()
            ->whereNull('deleted_at')
            ->where('status', '!=', InvoiceStatus::Cancelled->value)
            ->whereBetween('created_at', [$start, $end])
            ->count();
    }
}
