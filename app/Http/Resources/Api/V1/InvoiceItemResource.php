<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\InvoiceItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin InvoiceItem
 */
class InvoiceItemResource extends JsonResource
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
            'invoice_id' => $this->invoice_id,
            'product_id' => $this->product_id,
            'product_name' => $this->product_name,
            'description' => $this->description,
            'quantity' => (float) $this->quantity,
            'unit' => $this->unit,
            'unit_price' => (int) $this->unit_price,
            'unit_price_decimal' => round($this->unit_price / 100, 2),
            'formatted_unit_price' => '$'.number_format($this->unit_price / 100, 2),
            'tax_rate' => (float) $this->tax_rate,
            'tax_amount' => (int) $this->tax_amount,
            'tax_amount_decimal' => round($this->tax_amount / 100, 2),
            'formatted_tax_amount' => '$'.number_format($this->tax_amount / 100, 2),
            'subtotal' => (int) $this->subtotal,
            'subtotal_decimal' => round($this->subtotal / 100, 2),
            'formatted_subtotal' => '$'.number_format($this->subtotal / 100, 2),
            'total' => (int) $this->total,
            'total_decimal' => round($this->total / 100, 2),
            'formatted_total' => '$'.number_format($this->total / 100, 2),
            'sort_order' => (int) $this->sort_order,
        ];
    }
}
