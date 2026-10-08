<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Company;
use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    /**
     * Determine whether the user can view any products/services in the company.
     */
    public function viewAny(User $user, Company $company): bool
    {
        return $user->belongsToCompany($company)
            && $user->hasPermissionInCompany($company, 'products.view');
    }

    /**
     * Determine whether the user can view the specific product/service.
     */
    public function view(User $user, Product $product, Company $company): bool
    {
        return (int) $product->company_id === (int) $company->id
            && $user->belongsToCompany($company)
            && $user->hasPermissionInCompany($company, 'products.view');
    }

    /**
     * Determine whether the user can create products/services in the company.
     */
    public function create(User $user, Company $company): bool
    {
        return $user->belongsToCompany($company)
            && $user->hasPermissionInCompany($company, 'products.create');
    }

    /**
     * Determine whether the user can update the product/service.
     */
    public function update(User $user, Product $product, Company $company): bool
    {
        return (int) $product->company_id === (int) $company->id
            && $user->belongsToCompany($company)
            && $user->hasPermissionInCompany($company, 'products.update');
    }

    /**
     * Determine whether the user can delete (soft delete) the product/service.
     */
    public function delete(User $user, Product $product, Company $company): bool
    {
        return (int) $product->company_id === (int) $company->id
            && $user->belongsToCompany($company)
            && $user->hasPermissionInCompany($company, 'products.delete');
    }

    /**
     * Determine whether the user can restore the product/service.
     */
    public function restore(User $user, Product $product, Company $company): bool
    {
        return (int) $product->company_id === (int) $company->id
            && $user->belongsToCompany($company)
            && ($user->hasPermissionInCompany($company, 'products.delete') || $user->hasPermissionInCompany($company, 'products.update'));
    }
}
