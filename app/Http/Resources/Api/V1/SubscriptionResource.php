<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Subscription
 */
class SubscriptionResource extends JsonResource
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
            'plan_id' => $this->plan_id,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'is_active' => $this->isActive(),
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'trial_ends_at' => $this->trial_ends_at?->toIso8601String(),
            'current_period_start' => $this->current_period_start?->toIso8601String(),
            'current_period_end' => $this->current_period_end?->toIso8601String(),
            'cancel_at_period_end' => (bool) $this->cancel_at_period_end,
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'stripe_subscription_id' => $this->stripe_subscription_id,
            'stripe_price_id' => $this->stripe_price_id,
            'billing_interval' => $this->billing_interval,
            'plan' => new PlanResource($this->whenLoaded('plan')),
        ];
    }
}
