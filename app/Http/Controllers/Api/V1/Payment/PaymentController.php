<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Payment;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PaymentResource;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Payment\InvoicePaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PaymentController extends Controller
{
    /**
     * Display a paginated listing of payments for the given company.
     */
    public function index(Request $request, Company $company): AnonymousResourceCollection
    {
        $query = $company->payments()
            ->with(['invoice', 'customer'])
            ->latest('id');

        if ($request->filled('status')) {
            $query->ofStatus((string) $request->query('status'));
        }

        if ($request->filled('invoice_id')) {
            $query->where('invoice_id', (int) $request->query('invoice_id'));
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', (int) $request->query('customer_id'));
        }

        if ($request->filled('search')) {
            $query->search((string) $request->query('search'));
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', (string) $request->query('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', (string) $request->query('to'));
        }

        $perPage = min(max($request->integer('per_page', 15), 5), 100);
        $payments = $query->paginate($perPage);

        return PaymentResource::collection($payments);
    }

    /**
     * Display the specified payment.
     */
    public function show(Request $request, Company $company, Payment $payment): PaymentResource
    {
        if ((int) $payment->company_id !== (int) $company->id) {
            abort(404, 'Payment record not found.');
        }

        $payment->load(['invoice', 'customer']);

        return new PaymentResource($payment);
    }

    /**
     * Display all payments for a specific invoice.
     */
    public function invoicePayments(Request $request, Company $company, Invoice $invoice): AnonymousResourceCollection
    {
        if ((int) $invoice->company_id !== (int) $company->id) {
            abort(404, 'Invoice not found.');
        }

        $perPage = min(max($request->integer('per_page', 15), 5), 100);
        $payments = $invoice->payments()
            ->with(['customer'])
            ->latest('id')
            ->paginate($perPage);

        return PaymentResource::collection($payments);
    }

    /**
     * Generate or retrieve a secure customer payment link for an invoice.
     */
    public function generatePaymentLink(
        Request $request,
        Company $company,
        Invoice $invoice,
        InvoicePaymentService $paymentService
    ): JsonResponse {
        if ((int) $invoice->company_id !== (int) $company->id) {
            abort(404, 'Invoice not found.');
        }

        $force = $request->boolean('regenerate', false);
        $token = $paymentService->ensurePaymentToken($invoice, $force);
        $invoice->refresh();

        $appUrl = rtrim((string) config('app.url', $request->getSchemeAndHttpHost()), '/');
        $paymentUrl = "{$appUrl}/pay/{$token}";

        return response()->json([
            'data' => [
                'token' => $token,
                'payment_url' => $paymentUrl,
                'expires_at' => $invoice->payment_token_expires_at?->toIso8601String(),
                'is_payable' => $invoice->isPayable(),
                'balance_due' => $invoice->balance_due,
                'balance_due_decimal' => $invoice->balance_due_decimal,
                'currency' => $invoice->currency,
            ],
        ]);
    }
}
