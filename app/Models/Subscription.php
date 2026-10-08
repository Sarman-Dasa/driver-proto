<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id',
    'plan_id',
    'status',
    'starts_at',
    'ends_at',
    'trial_ends_at',
    'current_period_start',
    'current_period_end',
    'cancel_at_period_end',
])]
class Subscription extends Model
{
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'cancel_at_period_end' => 'boolean',
        ];
    }

    /**
     * The company that owns this subscription.
     *
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * The plan of this subscription.
     *
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Determine if the subscription is currently active or in valid period.
     */
    public function isActive(): bool
    {
        if ($this->status === SubscriptionStatus::Active || $this->status === SubscriptionStatus::Trialing || $this->status === SubscriptionStatus::PastDue) {
            return true;
        }

        if ($this->status === SubscriptionStatus::Cancelled && $this->cancel_at_period_end) {
            return $this->current_period_end === null || $this->current_period_end->isFuture();
        }

        return false;
    }

    /**
     * Determine if the subscription has expired.
     */
    public function isExpired(): bool
    {
        return $this->status === SubscriptionStatus::Expired;
    }

    /**
     * Determine if the subscription is on trial.
     */
    public function onTrial(): bool
    {
        return $this->status === SubscriptionStatus::Trialing
            && ($this->trial_ends_at === null || $this->trial_ends_at->isFuture());
    }

    /**
     * Determine if the subscription is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === SubscriptionStatus::Cancelled;
    }
}
