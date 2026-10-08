<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Company;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyAuthorizationController extends Controller
{
    /**
     * Get the current authenticated user's role and permissions within the company.
     */
    public function __invoke(Request $request, Company $company): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $role = $user->roleInCompany($company);
        $permissions = $user->permissionsInCompany($company);
        $isOwner = $company->isOwner($user);

        return ApiResponse::success([
            'role' => $role ? [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
                'description' => $role->description,
            ] : null,
            'permissions' => $permissions->values()->all(),
            'is_owner' => $isOwner,
        ], 'Workspace authorization retrieved successfully.');
    }
}
