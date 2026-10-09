<?php

declare(strict_types=1);

namespace App\Actions\Invoice;

use App\Models\Company;
use App\Models\Invoice;
use Illuminate\Validation\ValidationException;

class DeleteInvoiceAction
{
    /**
     * Soft delete a draft invoice.
     */
    public function execute(Company $company, Invoice $invoice): bool
    {
        if ($invoice->company_id !== $company->id) {
            abort(403, 'Unauthorized company invoice access.');
        }

        if (! $invoice->isDraft()) {
            throw ValidationException::withMessages([
                'status' => ['Only draft invoices can be deleted. Sent or active invoices must be cancelled.'],
            ]);
        }

        return (bool) $invoice->delete();
    }
}
