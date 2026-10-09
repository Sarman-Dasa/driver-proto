<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Webhook;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessStripeWebhookJob;
use App\Models\StripeWebhookEvent;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Event as StripeEvent;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeWebhookController extends Controller
{
    /**
     * Handle incoming Stripe webhook requests asynchronously and idempotently.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $secret = (string) config('stripe.webhook_secret', config('services.stripe.webhook_secret'));

        if (empty($sigHeader) || empty($secret)) {
            Log::warning('Stripe webhook received without signature header or webhook secret configured.');

            return response()->json(['error' => 'Webhook secret or signature missing.'], 400);
        }

        try {
            /** @var StripeEvent $event */
            $event = Webhook::constructEvent($payload, $sigHeader, $secret);
        } catch (UnexpectedValueException $e) {
            Log::error('Invalid Stripe webhook payload: '.$e->getMessage());

            return response()->json(['error' => 'Invalid payload.'], 400);
        } catch (SignatureVerificationException $e) {
            Log::error('Invalid Stripe webhook signature: '.$e->getMessage());

            return response()->json(['error' => 'Invalid signature.'], 400);
        }

        // Idempotency: Ignore already recorded events
        $existingEvent = StripeWebhookEvent::where('stripe_event_id', $event->id)->first();
        if ($existingEvent) {
            return response()->json([
                'received' => true,
                'duplicate' => true,
                'status' => $existingEvent->status,
            ]);
        }

        // Persist verified event
        $webhookRecord = StripeWebhookEvent::create([
            'stripe_event_id' => $event->id,
            'type' => $event->type,
            'payload' => $event->toArray(),
            'status' => 'pending',
            'stripe_event_created_at' => Carbon::createFromTimestamp((int) $event->created),
        ]);

        // Dispatch async processing
        ProcessStripeWebhookJob::dispatch($webhookRecord->id);

        return response()->json([
            'received' => true,
            'event_id' => $event->id,
        ]);
    }
}
