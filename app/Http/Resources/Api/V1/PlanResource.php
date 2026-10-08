<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Plan
 */
class PlanResource extends JsonResource
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
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'price_monthly' => (int) $this->price_monthly,
            'price_yearly' => (int) $this->price_yearly,
            'formatted_price_monthly' => $this->price_monthly === 0 ? '$0' : '$'.number_format($this->price_monthly / 100, 2),
            'formatted_price_yearly' => $this->price_yearly === 0 ? '$0' : '$'.number_format($this->price_yearly / 100, 2),
            'currency' => $this->currency,
            'is_default' => (bool) $this->is_default,
            'is_active' => (bool) $this->is_active,
            'sort_order' => (int) $this->sort_order,
            'features' => PlanFeatureResource::collection($this->whenLoaded('planFeatures')),
        ];
    }
}
