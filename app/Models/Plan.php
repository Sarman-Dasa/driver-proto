<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'slug',
    'description',
    'price_monthly',
    'price_yearly',
    'currency',
    'is_active',
    'is_default',
    'sort_order',
])]
class Plan extends Model
{
    use HasFactory;

    public const FREE = 'free';

    public const PRO = 'pro';

    public const BUSINESS = 'business';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_monthly' => 'integer',
            'price_yearly' => 'integer',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Plan features attached to this plan.
     *
     * @return HasMany<PlanFeature, $this>
     */
    public function planFeatures(): HasMany
    {
        return $this->hasMany(PlanFeature::class);
    }

    /**
     * Features included in this plan.
     *
     * @return BelongsToMany<Feature, $this>
     */
    public function features(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class, 'plan_features')
            ->withPivot(['is_enabled', 'limit_value'])
            ->withTimestamps();
    }

    /**
     * Subscriptions subscribed to this plan.
     *
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Determine if this is the free plan.
     */
    public function isFree(): bool
    {
        return $this->slug === self::FREE;
    }

    /**
     * Determine if this is the pro plan.
     */
    public function isPro(): bool
    {
        return $this->slug === self::PRO;
    }

    /**
     * Determine if this is the business plan.
     */
    public function isBusiness(): bool
    {
        return $this->slug === self::BUSINESS;
    }
}
