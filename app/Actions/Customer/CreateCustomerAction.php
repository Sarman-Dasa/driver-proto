<?php

declare(strict_types=1);

namespace App\Actions\Customer;

use App\Enums\CustomerStatus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Feature;
use App\Services\FeatureAccessService;
use App\Services\UsageLimitService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class CreateCustomerAction
{
    public function __construct(
        private readonly FeatureAccessService $featureAccessService,
        private readonly UsageLimitService $usageLimitService,
    ) {}

    /**
     * Create a new customer within the company, respecting plan features and usage limits.
     * Concurrency safe via transactional lock.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     */
    public function execute(Company $company, array $data): Customer
    {
        return DB::transaction(function () use ($company, $data): Customer {
            // Lock the company row to serialize concurrent creation and prevent limit race conditions
            Company::query()->where('id', $company->id)->lockForUpdate()->first();

            if (! $this->featureAccessService->canUseFeature($company, Feature::CUSTOMERS)) {
                throw new AuthorizationException('The customers feature is not available on your current plan.');
            }

            if (! $this->usageLimitService->canCreate($company, Feature::CUSTOMERS)) {
                throw new AuthorizationException('You have reached the customer limit for your current plan.');
            }

            $sameAsBilling = (bool) ($data['shipping_same_as_billing'] ?? true);
            if ($sameAsBilling) {
                $data['shipping_address'] = $data['billing_address'] ?? null;
                $data['shipping_city'] = $data['billing_city'] ?? null;
                $data['shipping_state'] = $data['billing_state'] ?? null;
                $data['shipping_postal_code'] = $data['billing_postal_code'] ?? null;
                $data['shipping_country'] = $data['billing_country'] ?? null;
            }

            // Enforce company association server-side
            $data['company_id'] = $company->id;
            $data['status'] = CustomerStatus::Active;

            return Customer::create($data);
        });
    }
}
