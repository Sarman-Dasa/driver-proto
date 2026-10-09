<?php

declare(strict_types=1);

namespace App\Actions\Invoice;

use App\Enums\InvoiceDeliveryStatus;
use App\Enums\InvoiceStatus;
use App\Jobs\SendInvoiceEmailJob;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceDelivery;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class SendInvoiceEmailAction
{
    /**
     * Queue an invoice email delivery.
     *
     * @param  array{
     *     recipient_email: string,
     *     subject: string,
     *     message?: ?string,
     * }  $data
     */
    public function execute(Company $company, Invoice $invoice, array $data, ?User $user = null): InvoiceDelivery
    {
        if ((int) $invoice->company_id !== (int) $company->id) {
            abort(403, 'Unauthorized company invoice access.');
        }

        if ($invoice->status === InvoiceStatus::Cancelled) {
            throw ValidationException::withMessages([
                'invoice' => ['Cancelled invoices cannot be sent to customers.'],
            ]);
        }

        // Prevent rapid duplicate clicks from creating redundant queue jobs
        $hasActiveQueued = InvoiceDelivery::query()
            ->where('invoice_id', $invoice->id)
            ->where('status', InvoiceDeliveryStatus::Queued)
            ->where('created_at', '>=', now()->subMinutes(2))
            ->exists();

        if ($hasActiveQueued) {
            throw ValidationException::withMessages([
                'recipient_email' => ['An invoice email is already queued for delivery. Please wait a moment.'],
            ]);
        }

        $invoice->ensurePaymentToken();

        $delivery = InvoiceDelivery::create([
            'company_id' => $company->id,
            'invoice_id' => $invoice->id,
            'recipient_email' => $data['recipient_email'],
            'subject' => $data['subject'],
            'message' => $data['message'] ?? null,
            'status' => InvoiceDeliveryStatus::Queued,
            'attempts' => 0,
            'created_by' => $user?->id,
        ]);

        SendInvoiceEmailJob::dispatch($delivery->id);

        return $delivery;
    }
}
