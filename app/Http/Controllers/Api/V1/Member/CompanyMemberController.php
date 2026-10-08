<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Member;

use App\Actions\Member\AssignMemberRoleAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Member\AssignRoleRequest;
use App\Http\Resources\Api\V1\MemberResource;
use App\Models\Company;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CompanyMemberController extends Controller
{
    /**
     * List all members belonging to the specified company.
     */
    public function index(Request $request, Company $company): JsonResponse
    {
        $members = $company->users()->latest('company_users.created_at')->get();

        return ApiResponse::success(
            MemberResource::collection($members),
            'Company members retrieved successfully.',
        );
    }

    /**
     * Assign a role to a member in the company.
     */
    public function assignRole(
        AssignRoleRequest $request,
        Company $company,
        User $member,
        AssignMemberRoleAction $action,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        $targetRole = $request->input('role_id') ?? $request->input('role');

        try {
            $action->execute($actor, $company, $member, $targetRole);

            return ApiResponse::success(
                new MemberResource($member),
                'Member role updated successfully.',
            );
        } catch (AuthorizationException $e) {
            return ApiResponse::error($e->getMessage(), [], 403);
        } catch (ValidationException $e) {
            return ApiResponse::error($e->getMessage(), $e->errors(), 422);
        }
    }
}
