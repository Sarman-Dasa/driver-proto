<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'invoice_id',
        'customer_id',
        'payment_reference',
        'stripe_checkout_session_id',
        'stripe_payment_intent_id',
        'stripe_account_id',
        'amount',
        'currency',
        'status',
        'payment_method_type',
        'receipt_url',
        'paid_at',
        'failure_reason',
        'metadata',
        'created_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount' => 'integer',
            'paid_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * Company that owns this payment.
     *
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Invoice associated with this payment.
     *
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Customer who made this payment.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * User who created or recorded this payment (if manual/internal).
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope query to a specific payment status.
     *
     * @param  Builder<Payment>  $query
     */
    public function scopeOfStatus(Builder $query, PaymentStatus|string $status): void
    {
        $value = $status instanceof PaymentStatus ? $status->value : $status;
        $query->where('status', $value);
    }

    /**
     * Scope query to search by reference, stripe IDs, invoice number, or customer.
     *
     * @param  Builder<Payment>  $query
     */
    public function scopeSearch(Builder $query, ?string $search): void
    {
        if (! $search) {
            return;
        }

        $term = trim($search);

        $query->where(function (Builder $q) use ($term) {
            $q->where('payment_reference', 'like', "%{$term}%")
                ->orWhere('stripe_checkout_session_id', 'like', "%{$term}%")
                ->orWhere('stripe_payment_intent_id', 'like', "%{$term}%")
                ->orWhereHas('invoice', function (Builder $invoiceQuery) use ($term) {
                    $invoiceQuery->where('invoice_number', 'like', "%{$term}%");
                })
                ->orWhereHas('customer', function (Builder $customerQuery) use ($term) {
                    $customerQuery->where('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%");
                });
        });
    }

    /**
     * Amount in major currency units.
     */
    protected function amountDecimal(): Attribute
    {
        return Attribute::make(
            get: fn (): float => round($this->amount / 100, 2),
        );
    }

    /**
     * Formatted string for amount.
     */
    protected function formattedAmount(): Attribute
    {
        return Attribute::make(
            get: fn (): string => '$'.number_format($this->amount / 100, 2).' '.$this->currency,
        );
    }
}
