<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DiscountType;
use App\Enums\InvoiceStatus;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'customer_id',
        'invoice_number',
        'status',
        'issue_date',
        'due_date',
        'currency',
        'subtotal',
        'discount_type',
        'discount_value',
        'discount_amount',
        'tax_amount',
        'total_amount',
        'amount_paid',
        'balance_due',
        'customer_snapshot',
        'notes',
        'terms',
        'created_by',
        'updated_by',
        'sent_at',
        'cancelled_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'discount_type' => DiscountType::class,
            'discount_value' => 'float',
            'subtotal' => 'integer',
            'discount_amount' => 'integer',
            'tax_amount' => 'integer',
            'total_amount' => 'integer',
            'amount_paid' => 'integer',
            'balance_due' => 'integer',
            'customer_snapshot' => 'array',
            'issue_date' => 'date:Y-m-d',
            'due_date' => 'date:Y-m-d',
            'sent_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * Company that owns this invoice.
     *
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Customer associated with this invoice.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Invoice line items.
     *
     * @return HasMany<InvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Invoice delivery attempts and history.
     *
     * @return HasMany<InvoiceDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(InvoiceDelivery::class)->latest();
    }

    /**
     * User who created the invoice.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * User who last updated the invoice.
     *
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope query to a specific status.
     *
     * @param  Builder<Invoice>  $query
     */
    public function scopeOfStatus(Builder $query, InvoiceStatus|string $status): void
    {
        $value = $status instanceof InvoiceStatus ? $status->value : $status;
        $query->where('status', $value);
    }

    /**
     * Scope query to a specific customer.
     *
     * @param  Builder<Invoice>  $query
     */
    public function scopeForCustomer(Builder $query, int $customerId): void
    {
        $query->where('customer_id', $customerId);
    }

    /**
     * Scope query to search by invoice number or customer information.
     *
     * @param  Builder<Invoice>  $query
     */
    public function scopeSearch(Builder $query, ?string $search): void
    {
        if (! $search) {
            return;
        }

        $term = trim($search);

        $query->where(function (Builder $q) use ($term) {
            $q->where('invoice_number', 'like', "%{$term}%")
                ->orWhere('customer_snapshot->name', 'like', "%{$term}%")
                ->orWhere('customer_snapshot->email', 'like', "%{$term}%")
                ->orWhere('customer_snapshot->company_name', 'like', "%{$term}%")
                ->orWhereHas('customer', function (Builder $customerQuery) use ($term) {
                    $customerQuery->where('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%")
                        ->orWhere('company_name', 'like', "%{$term}%");
                });
        });
    }

    /**
     * Scope query by issue date range.
     *
     * @param  Builder<Invoice>  $query
     */
    public function scopeDateRange(Builder $query, ?string $from, ?string $to): void
    {
        if ($from) {
            $query->whereDate('issue_date', '>=', $from);
        }

        if ($to) {
            $query->whereDate('issue_date', '<=', $to);
        }
    }

    /**
     * Subtotal in major currency units (e.g. 250000 -> 2500.00).
     */
    protected function subtotalDecimal(): Attribute
    {
        return Attribute::make(
            get: fn (): float => round($this->subtotal / 100, 2),
        );
    }

    /**
     * Total amount in major currency units.
     */
    protected function totalAmountDecimal(): Attribute
    {
        return Attribute::make(
            get: fn (): float => round($this->total_amount / 100, 2),
        );
    }

    /**
     * Tax amount in major currency units.
     */
    protected function taxAmountDecimal(): Attribute
    {
        return Attribute::make(
            get: fn (): float => round($this->tax_amount / 100, 2),
        );
    }

    /**
     * Discount amount in major currency units.
     */
    protected function discountAmountDecimal(): Attribute
    {
        return Attribute::make(
            get: fn (): float => round($this->discount_amount / 100, 2),
        );
    }

    /**
     * Balance due in major currency units.
     */
    protected function balanceDueDecimal(): Attribute
    {
        return Attribute::make(
            get: fn (): float => round($this->balance_due / 100, 2),
        );
    }

    /**
     * Formatted string for total amount.
     */
    protected function formattedTotal(): Attribute
    {
        return Attribute::make(
            get: fn (): string => '$'.number_format($this->total_amount / 100, 2),
        );
    }

    /**
     * Formatted string for balance due.
     */
    protected function formattedBalanceDue(): Attribute
    {
        return Attribute::make(
            get: fn (): string => '$'.number_format($this->balance_due / 100, 2),
        );
    }

    public function isDraft(): bool
    {
        return $this->status === InvoiceStatus::Draft;
    }

    public function isSent(): bool
    {
        return $this->status === InvoiceStatus::Sent;
    }

    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }

    public function canBeSent(): bool
    {
        return $this->status->canBeSent();
    }

    public function canBeCancelled(): bool
    {
        return $this->status->canBeCancelled();
    }
}
