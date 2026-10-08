<?php

declare(strict_types=1);

namespace App\Actions\Member;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignMemberRoleAction
{
    /**
     * Assign a role to a company member with security and owner protections.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(User $actor, Company $company, User $targetMember, Role|int|string $roleOrId): Role
    {
        // 1. Authorization check: Actor must have roles.manage permission or be company owner
        if (! $company->isOwner($actor) && ! $actor->hasPermissionInCompany($company, 'roles.manage')) {
            throw new AuthorizationException('You do not have permission to manage roles in this workspace.');
        }

        // 2. Validate target member belongs to the company
        if (! $targetMember->belongsToCompany($company)) {
            throw ValidationException::withMessages([
                'member' => ['The specified user is not a member of this workspace.'],
            ]);
        }

        // 3. Resolve target role
        $role = $roleOrId instanceof Role
            ? $roleOrId
            : (is_numeric($roleOrId)
                ? Role::query()->find((int) $roleOrId)
                : Role::query()->where('slug', (string) $roleOrId)->first());

        if (! $role) {
            throw ValidationException::withMessages([
                'role' => ['The specified role does not exist.'],
            ]);
        }

        // 4. Owner Security: Cannot assign the 'owner' role via normal role assignment
        if ($role->slug === Role::OWNER && ! $company->isOwner($targetMember)) {
            throw new AuthorizationException('The owner role cannot be assigned through standard role management.');
        }

        // 5. Owner Security: Cannot downgrade or modify the company owner's role
        if ($company->isOwner($targetMember)) {
            throw new AuthorizationException('The workspace owner role cannot be modified or downgraded.');
        }

        // 6. Update role in company_users
        DB::table('company_users')
            ->where('company_id', $company->id)
            ->where('user_id', $targetMember->id)
            ->update([
                'role_id' => $role->id,
                'updated_at' => now(),
            ]);

        return $role;
    }
}
