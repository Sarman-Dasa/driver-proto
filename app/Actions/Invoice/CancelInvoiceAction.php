<?php

declare(strict_types=1);

namespace App\Actions\Invoice;

use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CancelInvoiceAction
{
    /**
     * Transition invoice status to cancelled.
     */
    public function execute(Company $company, Invoice $invoice, ?User $user = null): Invoice
    {
        if ($invoice->company_id !== $company->id) {
            abort(403, 'Unauthorized company invoice access.');
        }

        if (! $invoice->canBeCancelled()) {
            throw ValidationException::withMessages([
                'status' => ['Only draft or sent invoices can be cancelled.'],
            ]);
        }

        $invoice->update([
            'status' => InvoiceStatus::Cancelled,
            'cancelled_at' => now(),
            'updated_by' => $user?->id,
        ]);

        return $invoice->fresh(['customer', 'items.product']);
    }
}
