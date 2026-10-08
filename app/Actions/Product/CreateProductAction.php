<?php

declare(strict_types=1);

namespace App\Actions\Product;

use App\Enums\ProductStatus;
use App\Models\Company;
use App\Models\Feature;
use App\Models\Product;
use App\Services\FeatureAccessService;
use App\Services\UsageLimitService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class CreateProductAction
{
    public function __construct(
        private readonly FeatureAccessService $featureAccessService,
        private readonly UsageLimitService $usageLimitService,
    ) {}

    /**
     * Create a new product or service within the company, respecting plan features and usage limits.
     * Concurrency safe via transactional lock.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     */
    public function execute(Company $company, array $data): Product
    {
        return DB::transaction(function () use ($company, $data): Product {
            // Lock the company row to serialize concurrent creation and prevent limit race conditions
            Company::query()->where('id', $company->id)->lockForUpdate()->first();

            if (! $this->featureAccessService->canUseFeature($company, Feature::PRODUCTS)) {
                throw new AuthorizationException('The products and services feature is not available on your current plan.');
            }

            if (! $this->usageLimitService->canCreate($company, Feature::PRODUCTS)) {
                throw new AuthorizationException('You have reached the product and service limit for your current plan.');
            }

            // Enforce company association server-side
            $data['company_id'] = $company->id;
            $data['currency'] = $data['currency'] ?? $company->currency ?? 'USD';
            $data['status'] = $data['status'] ?? ProductStatus::Active;

            // Normalize price to minor units (cents)
            if (isset($data['price'])) {
                $rawPrice = $data['price'];
                if (is_float($rawPrice) || (is_string($rawPrice) && str_contains($rawPrice, '.'))) {
                    $data['price'] = (int) round(((float) $rawPrice) * 100);
                } else {
                    $data['price'] = (int) $rawPrice;
                }
            }

            return Product::create($data);
        });
    }
}
