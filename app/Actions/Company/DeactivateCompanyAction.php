<?php

declare(strict_types=1);

namespace App\Actions\Company;

use App\Enums\CompanyStatus;
use App\Models\Company;
use App\Models\User;
use App\Services\CompanyContext;
use Illuminate\Auth\Access\AuthorizationException;

class DeactivateCompanyAction
{
    public function __construct(
        private readonly CompanyContext $companyContext,
    ) {}

    /**
     * Deactivate a company workspace. Only the company owner is authorized.
     *
     * @throws AuthorizationException
     */
    public function execute(User $user, Company $company): Company
    {
        if (! $company->isOwner($user)) {
            throw new AuthorizationException('Only the company owner can deactivate this workspace.');
        }

        $company->status = CompanyStatus::Inactive;
        $company->save();

        if ($this->companyContext->id() === $company->id) {
            $this->companyContext->clear();
        }

        return $company;
    }
}
