<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class InvoicePdfService
{
    /**
     * Generate raw PDF binary bytes for the given invoice.
     */
    public function generateBinary(Invoice $invoice): string
    {
        $invoice->loadMissing(['company', 'customer', 'items']);

        $pdf = Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'company' => $invoice->company,
        ]);

        $pdf->setPaper('a4', 'portrait');
        $pdf->setOption('isPhpEnabled', true);
        $pdf->setOption('isRemoteEnabled', true);

        return $pdf->output();
    }

    /**
     * Generate a streamable or downloadable HTTP response for the invoice PDF.
     */
    public function downloadResponse(Invoice $invoice, bool $attachment = true): Response
    {
        $binary = $this->generateBinary($invoice);
        $filename = "Invoice-{$invoice->invoice_number}.pdf";
        $disposition = $attachment ? 'attachment' : 'inline';

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "{$disposition}; filename=\"{$filename}\"",
            'Cache-Control' => 'no-cache, private',
        ]);
    }
}
