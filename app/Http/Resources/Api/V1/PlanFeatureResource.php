<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\PlanFeature;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PlanFeature
 */
class PlanFeatureResource extends JsonResource
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
            'key' => $this->feature->key,
            'name' => $this->feature->name,
            'description' => $this->feature->description,
            'type' => $this->feature->type->value,
            'limit_period' => $this->feature->limit_period->value,
            'is_enabled' => (bool) $this->is_enabled,
            'limit_value' => $this->limit_value !== null ? (int) $this->limit_value : null,
            'is_unlimited' => $this->is_enabled && $this->limit_value === null,
        ];
    }
}
