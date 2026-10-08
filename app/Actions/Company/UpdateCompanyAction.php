<?php

declare(strict_types=1);

namespace App\Actions\Company;

use App\Models\Company;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;

class UpdateCompanyAction
{
    /**
     * Update company details. Only the company owner is authorized.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     */
    public function execute(User $user, Company $company, array $data): Company
    {
        if (! $company->isOwner($user)) {
            throw new AuthorizationException('Only the company owner can update workspace settings.');
        }

        $safeData = Arr::only($data, [
            'name',
            'email',
            'phone',
            'website',
            'address',
            'city',
            'state',
            'postal_code',
            'country',
            'tax_number',
            'currency',
            'timezone',
        ]);

        $company->update($safeData);

        return $company->fresh();
    }
}
