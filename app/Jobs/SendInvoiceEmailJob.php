<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\InvoiceDeliveryStatus;
use App\Enums\InvoiceStatus;
use App\Mail\InvoiceMail;
use App\Models\InvoiceDelivery;
use App\Services\InvoicePdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendInvoiceEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly int $deliveryId,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(InvoicePdfService $pdfService): void
    {
        /** @var InvoiceDelivery|null $delivery */
        $delivery = InvoiceDelivery::with(['invoice.company', 'invoice.customer', 'invoice.items'])
            ->find($this->deliveryId);

        if ($delivery === null) {
            Log::warning("SendInvoiceEmailJob aborted: Delivery ID {$this->deliveryId} not found.");

            return;
        }

        $delivery->increment('attempts');

        try {
            $invoice = $delivery->invoice;

            // Generate authoritative PDF binary from invoice snapshots
            $pdfBinary = $pdfService->generateBinary($invoice);

            // Send email with attached PDF
            Mail::to($delivery->recipient_email)->send(
                new InvoiceMail(
                    invoice: $invoice,
                    subjectText: $delivery->subject,
                    customMessage: $delivery->message,
                    pdfBinary: $pdfBinary,
                )
            );

            // Mark delivery as sent
            $delivery->update([
                'status' => InvoiceDeliveryStatus::Sent,
                'sent_at' => now(),
                'error_message' => null,
            ]);

            // If the invoice is currently in draft status, transition to sent
            if ($invoice->status === InvoiceStatus::Draft) {
                $invoice->update([
                    'status' => InvoiceStatus::Sent,
                    'sent_at' => now(),
                ]);
            }
        } catch (Throwable $e) {
            $delivery->update([
                'status' => InvoiceDeliveryStatus::Failed,
                'failed_at' => now(),
                'error_message' => substr($e->getMessage(), 0, 500),
            ]);

            Log::error("Failed to send invoice email for delivery {$delivery->id}: {$e->getMessage()}");

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        $delivery = InvoiceDelivery::find($this->deliveryId);
        if ($delivery !== null) {
            $delivery->update([
                'status' => InvoiceDeliveryStatus::Failed,
                'failed_at' => now(),
                'error_message' => $exception ? substr($exception->getMessage(), 0, 500) : 'Job failed',
            ]);
        }
    }
}
