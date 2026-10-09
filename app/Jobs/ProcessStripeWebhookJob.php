<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\StripeWebhookEvent;
use App\Services\Payment\InvoicePaymentService;
use App\Services\Stripe\StripeConnectService;
use App\Services\Stripe\SubscriptionLifecycleService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessStripeWebhookJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $webhookEventId,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(
        SubscriptionLifecycleService $lifecycleService,
        StripeConnectService $connectService,
        ?InvoicePaymentService $paymentService = null,
    ): void {
        $paymentService ??= app(InvoicePaymentService::class);
        /** @var StripeWebhookEvent|null $eventRecord */
        $eventRecord = StripeWebhookEvent::find($this->webhookEventId);

        if (! $eventRecord) {
            Log::warning("StripeWebhookEvent record {$this->webhookEventId} not found for processing.");

            return;
        }

        if ($eventRecord->status === 'processed') {
            return;
        }

        $eventRecord->status = 'processing';
        $eventRecord->save();

        try {
            $payload = $eventRecord->payload ?? [];
            $type = $eventRecord->type;
            $dataObject = $payload['data']['object'] ?? [];
            $eventTimestamp = (int) ($payload['created'] ?? time());
            $eventId = $eventRecord->stripe_event_id;
            $connectedAccountId = $payload['account'] ?? null;

            match ($type) {
                'checkout.session.completed' => (($dataObject['mode'] ?? '') === 'subscription')
                    ? $lifecycleService->handleCheckoutSessionCompleted($dataObject, $eventTimestamp)
                    : $paymentService->handleCheckoutSessionCompleted($dataObject, $connectedAccountId),
                'checkout.session.expired' => $paymentService->handleCheckoutSessionExpired($dataObject, $connectedAccountId),
                'payment_intent.succeeded' => $paymentService->handlePaymentIntentSucceeded($dataObject, $connectedAccountId),
                'payment_intent.payment_failed' => $paymentService->handlePaymentIntentFailed($dataObject, $connectedAccountId),
                'customer.subscription.created',
                'customer.subscription.updated' => $lifecycleService->handleSubscriptionCreatedOrUpdated($dataObject, $eventTimestamp),
                'customer.subscription.deleted' => $lifecycleService->handleSubscriptionDeleted($dataObject, $eventTimestamp),
                'invoice.paid' => $lifecycleService->handleInvoicePaid($dataObject, $eventId, $eventTimestamp),
                'invoice.payment_failed' => $lifecycleService->handleInvoicePaymentFailed($dataObject, $eventId, $eventTimestamp),
                'charge.refunded' => $lifecycleService->handleChargeRefunded($dataObject, $eventId, $eventTimestamp),
                'account.updated' => $connectService->handleAccountUpdated($dataObject),
                'account.application.deauthorized' => $connectService->handleAccountDeauthorized($dataObject),
                default => Log::info("Unhandled Stripe webhook event type: {$type}"),
            };

            $eventRecord->status = 'processed';
            $eventRecord->processed_at = now();
            $eventRecord->error_message = null;
            $eventRecord->save();
        } catch (Throwable $e) {
            $eventRecord->status = 'failed';
            $eventRecord->error_message = $e->getMessage()."\n".$e->getTraceAsString();
            $eventRecord->save();

            Log::error("Failed to process Stripe webhook {$eventRecord->stripe_event_id}: {$e->getMessage()}", [
                'exception' => $e,
            ]);

            throw $e;
        }
    }
}
