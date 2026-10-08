<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Company;
use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    /**
     * Determine whether the user can view any customers in the company.
     */
    public function viewAny(User $user, Company $company): bool
    {
        return $user->belongsToCompany($company)
            && $user->hasPermissionInCompany($company, 'customers.view');
    }

    /**
     * Determine whether the user can view the specific customer.
     */
    public function view(User $user, Customer $customer, Company $company): bool
    {
        return (int) $customer->company_id === (int) $company->id
            && $user->belongsToCompany($company)
            && $user->hasPermissionInCompany($company, 'customers.view');
    }

    /**
     * Determine whether the user can create customers in the company.
     */
    public function create(User $user, Company $company): bool
    {
        return $user->belongsToCompany($company)
            && $user->hasPermissionInCompany($company, 'customers.create');
    }

    /**
     * Determine whether the user can update the customer.
     */
    public function update(User $user, Customer $customer, Company $company): bool
    {
        return (int) $customer->company_id === (int) $company->id
            && $user->belongsToCompany($company)
            && $user->hasPermissionInCompany($company, 'customers.update');
    }

    /**
     * Determine whether the user can archive/delete the customer.
     */
    public function delete(User $user, Customer $customer, Company $company): bool
    {
        return (int) $customer->company_id === (int) $company->id
            && $user->belongsToCompany($company)
            && $user->hasPermissionInCompany($company, 'customers.delete');
    }

    /**
     * Determine whether the user can restore the customer.
     */
    public function restore(User $user, Customer $customer, Company $company): bool
    {
        return (int) $customer->company_id === (int) $company->id
            && $user->belongsToCompany($company)
            && $user->hasPermissionInCompany($company, 'customers.delete');
    }
}
