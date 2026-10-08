<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Company;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Feature;
use App\Services\FeatureAccessService;
use App\Services\UsageLimitService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyFeatureController extends Controller
{
    public function __construct(
        private readonly FeatureAccessService $featureAccessService,
        private readonly UsageLimitService $usageLimitService,
    ) {}

    /**
     * List all features and the company's current access, limit, and usage for each.
     */
    public function index(Request $request, Company $company): JsonResponse
    {
        $features = Feature::query()->get();
        $results = [];

        foreach ($features as $feature) {
            $enabled = $this->featureAccessService->canUseFeature($company, $feature->key);
            $limit = $this->usageLimitService->getLimit($company, $feature->key);
            $usage = $this->usageLimitService->getCurrentUsage($company, $feature->key);
            $remaining = $this->usageLimitService->remaining($company, $feature->key, $usage);

            $results[$feature->key] = [
                'key' => $feature->key,
                'name' => $feature->name,
                'description' => $feature->description,
                'type' => $feature->type->value,
                'limit_period' => $feature->limit_period->value,
                'enabled' => $enabled,
                'limit' => $limit,
                'is_unlimited' => $enabled && $limit === null,
                'current_usage' => $usage,
                'remaining' => $remaining,
                'has_reached_limit' => $this->featureAccessService->hasReachedLimit($company, $feature->key, $usage),
            ];
        }

        return ApiResponse::success($results, 'Company features retrieved successfully.');
    }
}
