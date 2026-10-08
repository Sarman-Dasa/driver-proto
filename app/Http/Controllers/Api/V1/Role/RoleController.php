<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Role;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\RoleResource;
use App\Models\Company;
use App\Models\Role;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class RoleController extends Controller
{
    /**
     * List all available roles in the system for member assignment.
     */
    public function index(Company $company): JsonResponse
    {
        $roles = Role::query()->with('permissions')->get();

        return ApiResponse::success(
            RoleResource::collection($roles),
            'Roles retrieved successfully.',
        );
    }
}
