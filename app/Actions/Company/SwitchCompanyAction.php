<?php

declare(strict_types=1);

namespace App\Actions\Company;

use App\Models\Company;
use App\Models\User;
use App\Services\CompanyContext;
use Illuminate\Validation\ValidationException;

class SwitchCompanyAction
{
    public function __construct(
        private readonly CompanyContext $companyContext,
    ) {}

    /**
     * Switch the active company context for the authenticated user.
     *
     * @throws ValidationException
     */
    public function execute(User $user, Company|int $companyOrId): Company
    {
        $company = $companyOrId instanceof Company
            ? $companyOrId
            : Company::query()->find($companyOrId);

        if (! $company) {
            throw ValidationException::withMessages([
                'company_id' => ['The selected company does not exist.'],
            ]);
        }

        if (! $user->belongsToCompany($company)) {
            throw ValidationException::withMessages([
                'company_id' => ['You do not have access to this company.'],
            ]);
        }

        if (! $company->isActive()) {
            throw ValidationException::withMessages([
                'company_id' => ['This company is inactive.'],
            ]);
        }

        $this->companyContext->setCompany($company);

        return $company;
    }
}
