<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Enums\ProductStatus;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class ProductResource extends JsonResource
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
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'name' => $this->name,
            'description' => $this->description,
            'sku' => $this->sku,
            'price' => (int) $this->price,
            'price_decimal' => round($this->price / 100, 2),
            'formatted_price' => '$'.number_format($this->price / 100, 2),
            'currency' => $this->currency,
            'tax_rate' => (float) $this->tax_rate,
            'unit' => $this->unit,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'is_active' => $this->status === ProductStatus::Active && ! $this->trashed(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }
}
