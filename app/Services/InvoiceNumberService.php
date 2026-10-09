<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Company;
use App\Models\Invoice;

class InvoiceNumberService
{
    /**
     * Generate the next unique invoice number for the given company.
     * Must be executed within a database transaction with company lock.
     */
    public function generateNextNumber(Company $company, string $prefix = 'INV-'): string
    {
        // Find existing invoices for company to determine highest sequence number
        $invoices = Invoice::withTrashed()
            ->where('company_id', $company->id)
            ->where('invoice_number', 'like', "{$prefix}%")
            ->lockForUpdate()
            ->pluck('invoice_number');

        $maxSequence = 0;

        foreach ($invoices as $invNum) {
            $numPart = substr($invNum, strlen($prefix));
            if (is_numeric($numPart)) {
                $seq = (int) $numPart;
                if ($seq > $maxSequence) {
                    $maxSequence = $seq;
                }
            }
        }

        $nextSeq = $maxSequence + 1;

        // Ensure candidate is unique, advancing if collisions exist
        do {
            $candidate = $prefix.str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);
            $exists = Invoice::withTrashed()
                ->where('company_id', $company->id)
                ->where('invoice_number', $candidate)
                ->exists();

            if ($exists) {
                $nextSeq++;
            }
        } while ($exists);

        return $candidate;
    }
}
