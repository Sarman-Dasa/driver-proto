<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Company;

use App\Actions\Company\CreateCompanyAction;
use App\Actions\Company\SwitchCompanyAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Company\CreateCompanyRequest;
use App\Http\Requests\Api\V1\Company\SwitchCompanyRequest;
use App\Http\Resources\Api\V1\CompanyResource;
use App\Models\Company;
use App\Models\User;
use App\Services\CompanyContext;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    /**
     * List all companies the authenticated user belongs to.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $companies = $user->companies()->latest('company_users.created_at')->get();

        return ApiResponse::success(
            CompanyResource::collection($companies),
            'Companies retrieved successfully.',
        );
    }

    /**
     * Display the specified company.
     */
    public function show(Company $company): JsonResponse
    {
        return ApiResponse::success(
            new CompanyResource($company),
            'Company details retrieved successfully.',
        );
    }

    /**
     * Store a newly created company in storage.
     */
    public function store(CreateCompanyRequest $request, CreateCompanyAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $company = $action->execute($user, $request->validated());

        return ApiResponse::success(
            new CompanyResource($company),
            'Company created successfully.',
            201,
        );
    }

    /**
     * Switch active company context.
     */
    public function switch(SwitchCompanyRequest $request, SwitchCompanyAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $company = $action->execute($user, (int) $request->input('company_id'));

        return ApiResponse::success(
            new CompanyResource($company),
            'Active company switched successfully.',
        );
    }

    /**
     * Get the current resolved company context.
     */
    public function current(CompanyContext $companyContext): JsonResponse
    {
        $company = $companyContext->getCompany();

        if (! $company) {
            return ApiResponse::error('No active company selected.', [], 404);
        }

        return ApiResponse::success(
            new CompanyResource($company),
            'Current company retrieved successfully.',
        );
    }
}
