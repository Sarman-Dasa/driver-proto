<?php

declare(strict_types=1);

namespace App\Actions\Invoice;

use App\Models\Company;
use App\Models\Invoice;

class RestoreInvoiceAction
{
    /**
     * Restore a soft-deleted invoice.
     */
    public function execute(Company $company, Invoice $invoice): bool
    {
        if ($invoice->company_id !== $company->id) {
            abort(403, 'Unauthorized company invoice access.');
        }

        return (bool) $invoice->restore();
    }
}
