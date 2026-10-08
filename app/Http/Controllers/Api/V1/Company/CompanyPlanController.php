<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Company;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PlanResource;
use App\Http\Resources\Api\V1\SubscriptionResource;
use App\Models\Company;
use App\Models\PlanFeature;
use App\Services\FeatureAccessService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyPlanController extends Controller
{
    public function __construct(
        private readonly FeatureAccessService $featureAccessService,
    ) {}

    /**
     * Get the plan and subscription details for the given company.
     */
    public function show(Request $request, Company $company): JsonResponse
    {
        $subscription = $company->currentSubscription();
        if ($subscription !== null) {
            $subscription->load('plan.planFeatures.feature');
        }

        $effectivePlan = $this->featureAccessService->getEffectivePlan($company);
        if ($effectivePlan !== null) {
            $effectivePlan->load('planFeatures.feature');
        }

        $featuresMap = [];
        if ($effectivePlan !== null) {
            /** @var PlanFeature $planFeature */
            foreach ($effectivePlan->planFeatures as $planFeature) {
                $featureKey = $planFeature->feature->key;
                $featuresMap[$featureKey] = [
                    'key' => $featureKey,
                    'name' => $planFeature->feature->name,
                    'type' => $planFeature->feature->type->value,
                    'limit_period' => $planFeature->feature->limit_period->value,
                    'enabled' => $this->featureAccessService->canUseFeature($company, $featureKey),
                    'limit' => $planFeature->limit_value !== null ? (int) $planFeature->limit_value : null,
                    'is_unlimited' => $planFeature->is_enabled && $planFeature->limit_value === null,
                ];
            }
        }

        return ApiResponse::success([
            'subscription' => $subscription !== null ? new SubscriptionResource($subscription) : null,
            'plan' => $effectivePlan !== null ? new PlanResource($effectivePlan) : null,
            'features' => $featuresMap,
        ], 'Company plan retrieved successfully.');
    }
}
