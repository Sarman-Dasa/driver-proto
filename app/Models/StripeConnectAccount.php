<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StripeConnectAccount extends Model
{
    use HasFactory;

    /**
     * Account statuses.
     */
    public const STATUS_NOT_CONNECTED = 'not_connected';

    public const STATUS_PENDING = 'pending';

    public const STATUS_RESTRICTED = 'restricted';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_DISABLED = 'disabled';

    protected $fillable = [
        'company_id',
        'stripe_account_id',
        'account_type',
        'country',
        'currency',
        'charges_enabled',
        'payouts_enabled',
        'details_submitted',
        'status',
        'capabilities',
        'requirements',
        'dashboard_url',
        'connected_at',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'charges_enabled' => 'boolean',
            'payouts_enabled' => 'boolean',
            'details_submitted' => 'boolean',
            'capabilities' => 'array',
            'requirements' => 'array',
            'connected_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    /**
     * The company that owns this Stripe Connect account.
     *
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Determine if this connected account is fully ready to accept customer invoice payments.
     */
    public function isReadyForPayments(): bool
    {
        if (! $this->charges_enabled || ! $this->details_submitted) {
            return false;
        }

        $cardPayments = $this->capabilities['card_payments'] ?? null;
        if ($cardPayments !== 'active') {
            return false;
        }

        if (! empty($this->requirements['disabled_reason'])) {
            return false;
        }

        return true;
    }

    /**
     * Determine if additional verification information is currently required by Stripe.
     */
    public function hasPendingRequirements(): bool
    {
        return ! empty($this->requirements['currently_due'])
            || ! empty($this->requirements['past_due']);
    }

    /**
     * Determine a human-readable display status label.
     */
    public function getStatusLabelAttribute(): string
    {
        if ($this->isReadyForPayments()) {
            return 'Ready for Payments';
        }

        if (! empty($this->requirements['disabled_reason'])) {
            return 'Restricted';
        }

        if ($this->hasPendingRequirements()) {
            return 'Information Required';
        }

        if ($this->details_submitted) {
            return 'Pending Verification';
        }

        if ($this->stripe_account_id) {
            return 'Onboarding Incomplete';
        }

        return 'Not Connected';
    }
}
