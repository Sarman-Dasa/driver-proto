<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\StripeConnectAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StripeConnectAccount
 */
class StripeConnectAccountResource extends JsonResource
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
            'stripe_account_id' => $this->stripe_account_id,
            'account_type' => $this->account_type,
            'country' => $this->country,
            'currency' => $this->currency,
            'charges_enabled' => (bool) $this->charges_enabled,
            'payouts_enabled' => (bool) $this->payouts_enabled,
            'details_submitted' => (bool) $this->details_submitted,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'is_ready_for_payments' => $this->isReadyForPayments(),
            'has_pending_requirements' => $this->hasPendingRequirements(),
            'capabilities' => $this->capabilities ?? [],
            'requirements' => [
                'currently_due' => $this->requirements['currently_due'] ?? [],
                'past_due' => $this->requirements['past_due'] ?? [],
                'eventually_due' => $this->requirements['eventually_due'] ?? [],
                'disabled_reason' => $this->requirements['disabled_reason'] ?? null,
            ],
            'connected_at' => $this->connected_at?->toIso8601String(),
            'last_synced_at' => $this->last_synced_at?->toIso8601String(),
        ];
    }
}
