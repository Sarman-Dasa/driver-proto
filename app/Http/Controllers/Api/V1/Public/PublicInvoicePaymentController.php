<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\Payment\InvoicePaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PublicInvoicePaymentController extends Controller
{
    /**
     * Retrieve public customer-safe details of an invoice by its payment link token.
     */
    public function show(string $token, InvoicePaymentService $paymentService): JsonResponse
    {
        $data = $paymentService->getPublicInvoiceData($token);

        return response()->json([
            'data' => $data,
        ]);
    }

    /**
     * Create a Stripe Checkout Session for the invoice routed to the company's connected account.
     */
    public function checkout(Request $request, string $token, InvoicePaymentService $paymentService): JsonResponse
    {
        /** @var Invoice|null $invoice */
        $invoice = Invoice::where('payment_token', $token)->first();

        if (! $invoice) {
            throw ValidationException::withMessages([
                'token' => ['Invalid or non-existent invoice payment link.'],
            ]);
        }

        $appUrl = rtrim((string) config('app.url', $request->getSchemeAndHttpHost()), '/');
        $successUrl = "{$appUrl}/pay/{$token}?session_id={CHECKOUT_SESSION_ID}&success=1";
        $cancelUrl = "{$appUrl}/pay/{$token}?session_id={CHECKOUT_SESSION_ID}&cancelled=1";

        $result = $paymentService->createCheckoutSession($invoice, $successUrl, $cancelUrl);

        return response()->json([
            'data' => [
                'checkout_url' => $result['checkout_url'],
                'session_id' => $result['session_id'],
                'payment_reference' => $result['payment_reference'],
            ],
        ]);
    }

    /**
     * Query verified payment status for the public invoice view.
     */
    public function status(Request $request, string $token, InvoicePaymentService $paymentService): JsonResponse
    {
        $sessionId = (string) $request->query('session_id', '');

        $data = $paymentService->getPaymentStatus($token, $sessionId ?: null);

        return response()->json([
            'data' => $data,
        ]);
    }
}
