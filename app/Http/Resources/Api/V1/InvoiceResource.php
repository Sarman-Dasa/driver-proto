<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Invoice
 */
class InvoiceResource extends JsonResource
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
            'customer_id' => $this->customer_id,
            'invoice_number' => $this->invoice_number,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'is_editable' => $this->isEditable(),
            'can_be_sent' => $this->canBeSent(),
            'can_be_cancelled' => $this->canBeCancelled(),
            'issue_date' => $this->issue_date?->format('Y-m-d'),
            'due_date' => $this->due_date?->format('Y-m-d'),
            'currency' => $this->currency,
            'subtotal' => (int) $this->subtotal,
            'subtotal_decimal' => round($this->subtotal / 100, 2),
            'formatted_subtotal' => '$'.number_format($this->subtotal / 100, 2),
            'discount_type' => $this->discount_type?->value ?? 'none',
            'discount_value' => (float) $this->discount_value,
            'discount_amount' => (int) $this->discount_amount,
            'discount_amount_decimal' => round($this->discount_amount / 100, 2),
            'formatted_discount_amount' => '$'.number_format($this->discount_amount / 100, 2),
            'tax_amount' => (int) $this->tax_amount,
            'tax_amount_decimal' => round($this->tax_amount / 100, 2),
            'formatted_tax_amount' => '$'.number_format($this->tax_amount / 100, 2),
            'total_amount' => (int) $this->total_amount,
            'total_amount_decimal' => round($this->total_amount / 100, 2),
            'formatted_total_amount' => '$'.number_format($this->total_amount / 100, 2),
            'amount_paid' => (int) $this->amount_paid,
            'amount_paid_decimal' => round($this->amount_paid / 100, 2),
            'balance_due' => (int) $this->balance_due,
            'balance_due_decimal' => round($this->balance_due / 100, 2),
            'formatted_balance_due' => '$'.number_format($this->balance_due / 100, 2),
            'customer_snapshot' => $this->customer_snapshot,
            'notes' => $this->notes,
            'terms' => $this->terms,
            'sent_at' => $this->sent_at?->toISOString(),
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'payment_token' => $this->payment_token,
            'payment_url' => $this->payment_token ? rtrim((string) config('app.url', $request->getSchemeAndHttpHost()), '/').'/pay/'.$this->payment_token : null,
            'is_payable' => $this->isPayable(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
            'customer' => $this->whenLoaded('customer', fn () => CustomerResource::make($this->customer)),
            'items' => InvoiceItemResource::collection($this->whenLoaded('items')),
            'deliveries' => InvoiceDeliveryResource::collection($this->whenLoaded('deliveries')),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
        ];
    }
}
