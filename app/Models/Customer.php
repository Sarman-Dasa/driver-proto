<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CustomerStatus;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'company_id',
    'name',
    'email',
    'phone',
    'company_name',
    'tax_number',
    'billing_address',
    'billing_city',
    'billing_state',
    'billing_postal_code',
    'billing_country',
    'shipping_same_as_billing',
    'shipping_address',
    'shipping_city',
    'shipping_state',
    'shipping_postal_code',
    'shipping_country',
    'notes',
    'status',
])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CustomerStatus::class,
            'shipping_same_as_billing' => 'boolean',
        ];
    }

    /**
     * The company that owns this customer.
     *
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Scope a query to only include active customers.
     *
     * @param  Builder<Customer>  $query
     * @return Builder<Customer>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', CustomerStatus::Active->value);
    }

    /**
     * Scope a query to only include archived customers.
     *
     * @param  Builder<Customer>  $query
     * @return Builder<Customer>
     */
    public function scopeArchived(Builder $query): Builder
    {
        return $query->where('status', CustomerStatus::Archived->value);
    }

    /**
     * Scope a query to search customers safely within the company.
     *
     * @param  Builder<Customer>  $query
     * @return Builder<Customer>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if ($term === null || trim($term) === '') {
            return $query;
        }

        $term = '%'.trim($term).'%';

        return $query->where(function (Builder $q) use ($term): void {
            $q->where('name', 'like', $term)
                ->orWhere('email', 'like', $term)
                ->orWhere('phone', 'like', $term)
                ->orWhere('company_name', 'like', $term)
                ->orWhere('tax_number', 'like', $term);
        });
    }

    /**
     * Determine if customer is active.
     */
    public function isActive(): bool
    {
        return $this->status === CustomerStatus::Active && ! $this->trashed();
    }

    /**
     * Determine if customer is archived.
     */
    public function isArchived(): bool
    {
        return $this->status === CustomerStatus::Archived || $this->trashed();
    }
}
