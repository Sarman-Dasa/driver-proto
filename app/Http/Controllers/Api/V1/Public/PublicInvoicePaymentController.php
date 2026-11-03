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
     * Create a Stripe PaymentIntent for inline custom Stripe Elements payment.
     */
    public function paymentIntent(Request $request, string $token, InvoicePaymentService $paymentService): JsonResponse
    {
        /** @var Invoice|null $invoice */
        $invoice = Invoice::where('payment_token', $token)->first();

        if (! $invoice) {
            throw ValidationException::withMessages([
                'token' => ['Invalid or non-existent invoice payment link.'],
            ]);
        }

        $result = $paymentService->createPaymentIntent($invoice);

        return response()->json([
            'data' => [
                'client_secret' => $result['client_secret'],
                'payment_intent_id' => $result['payment_intent_id'],
                'payment_reference' => $result['payment_reference'],
                'publishable_key' => $result['publishable_key'],
                'stripe_account_id' => $result['stripe_account_id'],
            ],
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
        $successUrl = "{$appUrl}/pay/{$token}/result?session_id={CHECKOUT_SESSION_ID}&status=success";
        $cancelUrl = "{$appUrl}/pay/{$token}/result?session_id={CHECKOUT_SESSION_ID}&status=cancelled";

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
        $paymentIntentId = (string) ($request->query('payment_intent_id') ?: $request->query('payment_intent') ?: '');

        $data = $paymentService->getPaymentStatus($token, $sessionId ?: null, $paymentIntentId ?: null);

        return response()->json([
            'data' => $data,
        ]);
    }
}
