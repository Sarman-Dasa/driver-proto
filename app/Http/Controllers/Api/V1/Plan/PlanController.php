<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Plan;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PlanResource;
use App\Models\Plan;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class PlanController extends Controller
{
    /**
     * List all active plans along with their configured features.
     */
    public function index(): JsonResponse
    {
        $plans = Plan::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->with(['planFeatures.feature'])
            ->get();

        return ApiResponse::success(
            PlanResource::collection($plans),
            'Plans retrieved successfully.',
        );
    }
}
