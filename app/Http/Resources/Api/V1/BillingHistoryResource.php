<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\BillingHistory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BillingHistory
 */
class BillingHistoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'subscription_id' => $this->subscription_id,
            'stripe_invoice_id' => $this->stripe_invoice_id,
            'stripe_payment_intent_id' => $this->stripe_payment_intent_id,
            'type' => $this->type,
            'amount' => $this->amount,
            'formatted_amount' => number_format($this->amount / 100, 2),
            'currency' => strtoupper($this->currency),
            'status' => $this->status,
            'billing_reason' => $this->billing_reason,
            'hosted_invoice_url' => $this->hosted_invoice_url,
            'invoice_pdf_url' => $this->invoice_pdf_url,
            'description' => $this->description,
            'event_at' => $this->event_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
