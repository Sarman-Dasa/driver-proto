<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id',
    'subscription_id',
    'stripe_invoice_id',
    'stripe_payment_intent_id',
    'stripe_event_id',
    'type',
    'amount',
    'currency',
    'status',
    'billing_reason',
    'hosted_invoice_url',
    'invoice_pdf_url',
    'description',
    'event_at',
])]
class BillingHistory extends Model
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
            'amount' => 'integer',
            'event_at' => 'datetime',
        ];
    }

    /**
     * Company belonging to this billing history record.
     *
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Subscription related to this billing record.
     *
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
