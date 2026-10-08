<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CompanyStatus;
use App\Services\FeatureAccessService;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'slug',
    'email',
    'phone',
    'logo',
    'status',
    'owner_id',
    'website',
    'address',
    'city',
    'state',
    'postal_code',
    'country',
    'tax_number',
    'currency',
    'timezone',
])]
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CompanyStatus::class,
        ];
    }

    /**
     * The owner of the company.
     *
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * The users that belong to the company.
     *
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'company_users')
            ->withPivot(['id', 'role_id'])
            ->withTimestamps();
    }

    /**
     * Determine if the given user is the company owner.
     */
    public function isOwner(User|int|null $user): bool
    {
        if ($user === null || $this->owner_id === null) {
            return false;
        }

        $userId = $user instanceof User ? $user->id : (int) $user;

        return (int) $this->owner_id === $userId;
    }

    /**
     * Determine if the company is active.
     */
    public function isActive(): bool
    {
        return $this->status === CompanyStatus::Active;
    }

    /**
     * Scope a query to only include active companies.
     *
     * @param  Builder<Company>  $query
     * @return Builder<Company>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', CompanyStatus::Active->value);
    }

    /**
     * Get the role of a given user within this company.
     */
    public function roleOf(User|int|string $user): ?Role
    {
        $userModel = $user instanceof User ? $user : User::find($user);

        return $userModel?->roleInCompany($this);
    }

    /**
     * Determine if a company member has a given permission.
     */
    public function memberHasPermission(User|int|string $user, string $permission): bool
    {
        $userModel = $user instanceof User ? $user : User::find($user);

        return $userModel?->hasPermissionInCompany($this, $permission) ?? false;
    }

    /**
     * Subscriptions for this company.
     *
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Customers belonging to this company.
     *
     * @return HasMany<Customer, $this>
     */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    /**
     * Products and services belonging to this company.
     *
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Get the current active subscription for this company.
     */
    public function currentSubscription(): ?Subscription
    {
        return $this->subscriptions()->latest('id')->first();
    }

    /**
     * Get the current plan for this company.
     */
    public function currentPlan(): ?Plan
    {
        return $this->currentSubscription()?->plan;
    }

    /**
     * Determine if this company can use a given feature.
     */
    public function canUseFeature(string $featureKey): bool
    {
        return app(FeatureAccessService::class)->canUseFeature($this, $featureKey);
    }

    /**
     * Get the configured limit for a given feature.
     */
    public function getFeatureLimit(string $featureKey): ?int
    {
        return app(FeatureAccessService::class)->getFeatureLimit($this, $featureKey);
    }
}
