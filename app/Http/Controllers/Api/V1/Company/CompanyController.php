<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Company;

use App\Actions\Company\CreateCompanyAction;
use App\Actions\Company\DeactivateCompanyAction;
use App\Actions\Company\ReactivateCompanyAction;
use App\Actions\Company\SwitchCompanyAction;
use App\Actions\Company\UpdateCompanyAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Company\CreateCompanyRequest;
use App\Http\Requests\Api\V1\Company\UpdateCompanyRequest;
use App\Http\Resources\Api\V1\CompanyResource;
use App\Models\Company;
use App\Models\User;
use App\Services\CompanyContext;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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
     * Update the specified company.
     */
    public function update(
        UpdateCompanyRequest $request,
        Company $company,
        UpdateCompanyAction $action,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        try {
            $updated = $action->execute($user, $company, $request->validated());

            return ApiResponse::success(
                new CompanyResource($updated),
                'Company updated successfully.',
            );
        } catch (AuthorizationException $e) {
            return ApiResponse::error($e->getMessage(), [], 403);
        }
    }

    /**
     * Switch active company context.
     */
    public function switch(
        Request $request,
        SwitchCompanyAction $action,
        ?Company $company = null,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        $target = $company ?? $request->input('company_id');

        if (! $target) {
            return ApiResponse::error('A company identifier is required to switch workspace.', [
                'company_id' => ['The company_id field is required.'],
            ], 422);
        }

        try {
            $switched = $action->execute($user, $target);

            return ApiResponse::success(
                new CompanyResource($switched),
                'Active company switched successfully.',
            );
        } catch (ValidationException $e) {
            return ApiResponse::error($e->getMessage(), $e->errors(), 422);
        }
    }

    /**
     * Deactivate the specified company workspace.
     */
    public function deactivate(
        Request $request,
        Company $company,
        DeactivateCompanyAction $action,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        try {
            $deactivated = $action->execute($user, $company);

            return ApiResponse::success(
                new CompanyResource($deactivated),
                'Company deactivated successfully.',
            );
        } catch (AuthorizationException $e) {
            return ApiResponse::error($e->getMessage(), [], 403);
        }
    }

    /**
     * Reactivate the specified company workspace.
     */
    public function reactivate(
        Request $request,
        Company $company,
        ReactivateCompanyAction $action,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        try {
            $reactivated = $action->execute($user, $company);

            return ApiResponse::success(
                new CompanyResource($reactivated),
                'Company reactivated successfully.',
            );
        } catch (AuthorizationException $e) {
            return ApiResponse::error($e->getMessage(), [], 403);
        }
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
