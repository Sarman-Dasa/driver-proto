<?php

declare(strict_types=1);

namespace App\Actions\Company;

use App\Enums\CompanyStatus;
use App\Models\Company;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class ReactivateCompanyAction
{
    /**
     * Reactivate a company workspace. Only the company owner is authorized.
     *
     * @throws AuthorizationException
     */
    public function execute(User $user, Company $company): Company
    {
        if (! $company->isOwner($user)) {
            throw new AuthorizationException('Only the company owner can reactivate this workspace.');
        }

        $company->status = CompanyStatus::Active;
        $company->save();

        return $company;
    }
}
