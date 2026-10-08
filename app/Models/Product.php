<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'type',
        'name',
        'description',
        'sku',
        'price',
        'currency',
        'tax_rate',
        'unit',
        'status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ProductType::class,
            'status' => ProductStatus::class,
            'price' => 'integer',
            'tax_rate' => 'float',
        ];
    }

    /**
     * Company that owns the product or service.
     *
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Scope a query to only include active products/services.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ProductStatus::Active->value);
    }

    /**
     * Scope a query to only include inactive products/services.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('status', ProductStatus::Inactive->value);
    }

    /**
     * Scope a query to filter by product type (product or service).
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeOfType(Builder $query, ProductType|string $type): Builder
    {
        $val = $type instanceof ProductType ? $type->value : $type;

        return $query->where('type', $val);
    }

    /**
     * Scope a query to search products and services within the company.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if ($term === null || trim($term) === '') {
            return $query;
        }

        $term = '%'.trim($term).'%';

        return $query->where(function (Builder $q) use ($term): void {
            $q->where('name', 'like', $term)
                ->orWhere('description', 'like', $term)
                ->orWhere('sku', 'like', $term);
        });
    }

    /**
     * Determine if the item is active.
     */
    public function isActive(): bool
    {
        return $this->status === ProductStatus::Active && ! $this->trashed();
    }

    /**
     * Determine if the item is a tangible product.
     */
    public function isProduct(): bool
    {
        return $this->type === ProductType::Product;
    }

    /**
     * Determine if the item is a service.
     */
    public function isService(): bool
    {
        return $this->type === ProductType::Service;
    }

    /**
     * Get price formatted as currency string (e.g. $2,000.00).
     *
     * @return Attribute<string, never>
     */
    protected function formattedPrice(): Attribute
    {
        return Attribute::make(
            get: fn (): string => '$'.number_format($this->price / 100, 2),
        );
    }

    /**
     * Get price in decimal units (e.g. 2000.00).
     *
     * @return Attribute<float, never>
     */
    protected function priceDecimal(): Attribute
    {
        return Attribute::make(
            get: fn (): float => round($this->price / 100, 2),
        );
    }
}
