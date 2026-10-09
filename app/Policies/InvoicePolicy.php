<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    /**
     * Determine whether the user can view any invoices in the company.
     */
    public function viewAny(User $user, Company $company): bool
    {
        return $user->belongsToCompany($company)
            && $user->hasPermissionInCompany($company, 'invoices.view');
    }

    /**
     * Determine whether the user can view the specific invoice.
     */
    public function view(User $user, Invoice $invoice, Company $company): bool
    {
        return (int) $invoice->company_id === (int) $company->id
            && $user->belongsToCompany($company)
            && $user->hasPermissionInCompany($company, 'invoices.view');
    }

    /**
     * Determine whether the user can create invoices in the company.
     */
    public function create(User $user, Company $company): bool
    {
        return $user->belongsToCompany($company)
            && $user->hasPermissionInCompany($company, 'invoices.create');
    }

    /**
     * Determine whether the user can update the invoice.
     */
    public function update(User $user, Invoice $invoice, Company $company): bool
    {
        return (int) $invoice->company_id === (int) $company->id
            && $user->belongsToCompany($company)
            && $user->hasPermissionInCompany($company, 'invoices.update');
    }

    /**
     * Determine whether the user can delete (soft delete) the invoice.
     */
    public function delete(User $user, Invoice $invoice, Company $company): bool
    {
        return (int) $invoice->company_id === (int) $company->id
            && $user->belongsToCompany($company)
            && $user->hasPermissionInCompany($company, 'invoices.delete');
    }

    /**
     * Determine whether the user can restore a soft-deleted invoice.
     */
    public function restore(User $user, Invoice $invoice, Company $company): bool
    {
        return (int) $invoice->company_id === (int) $company->id
            && $user->belongsToCompany($company)
            && $user->hasPermissionInCompany($company, 'invoices.delete');
    }

    /**
     * Determine whether the user can send the invoice.
     */
    public function send(User $user, Invoice $invoice, Company $company): bool
    {
        return (int) $invoice->company_id === (int) $company->id
            && $user->belongsToCompany($company)
            && $user->hasPermissionInCompany($company, 'invoices.send');
    }

    /**
     * Determine whether the user can cancel the invoice.
     */
    public function cancel(User $user, Invoice $invoice, Company $company): bool
    {
        return (int) $invoice->company_id === (int) $company->id
            && $user->belongsToCompany($company)
            && ($user->hasPermissionInCompany($company, 'invoices.cancel') || $user->hasPermissionInCompany($company, 'invoices.update'));
    }
}
