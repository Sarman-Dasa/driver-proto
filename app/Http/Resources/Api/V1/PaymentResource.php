<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payment
 */
class PaymentResource extends JsonResource
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
            'invoice_id' => $this->invoice_id,
            'invoice_number' => $this->invoice?->invoice_number,
            'customer_id' => $this->customer_id,
            'customer_name' => $this->customer?->name ?? ($this->invoice?->customer_snapshot['name'] ?? null),
            'customer_email' => $this->customer?->email ?? ($this->invoice?->customer_snapshot['email'] ?? null),
            'payment_reference' => $this->payment_reference,
            'stripe_checkout_session_id' => $this->stripe_checkout_session_id,
            'stripe_payment_intent_id' => $this->stripe_payment_intent_id,
            'stripe_account_id' => $this->stripe_account_id,
            'amount' => $this->amount,
            'amount_decimal' => $this->amount_decimal,
            'formatted_amount' => $this->formatted_amount,
            'currency' => $this->currency,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'payment_method_type' => $this->payment_method_type,
            'receipt_url' => $this->receipt_url,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'failure_reason' => $this->failure_reason,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
