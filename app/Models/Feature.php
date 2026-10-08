<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FeatureType;
use App\Enums\LimitPeriod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'key',
    'name',
    'description',
    'type',
    'limit_period',
])]
class Feature extends Model
{
    use HasFactory;

    // Feature key constants
    public const CUSTOMERS = 'customers';

    public const INVOICES = 'invoices';

    public const EXPENSES = 'expenses';

    public const ONLINE_PAYMENTS = 'online_payments';

    public const STRIPE_CONNECT = 'stripe_connect';

    public const REPORTS_BASIC = 'reports_basic';

    public const REPORTS_ADVANCED = 'reports_advanced';

    public const TEAM_MEMBERS = 'team_members';

    public const API_ACCESS = 'api_access';

    public const MULTIPLE_COMPANIES = 'multiple_companies';

    public const PAYMENT_REMINDERS = 'payment_reminders';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => FeatureType::class,
            'limit_period' => LimitPeriod::class,
        ];
    }

    /**
     * Plan features attached to this feature.
     *
     * @return HasMany<PlanFeature, $this>
     */
    public function planFeatures(): HasMany
    {
        return $this->hasMany(PlanFeature::class);
    }

    /**
     * Plans that include this feature.
     *
     * @return BelongsToMany<Plan, $this>
     */
    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class, 'plan_features')
            ->withPivot(['is_enabled', 'limit_value'])
            ->withTimestamps();
    }

    /**
     * Determine if this feature is a limit-based feature.
     */
    public function isLimitType(): bool
    {
        return $this->type === FeatureType::Limit;
    }

    /**
     * Determine if this feature is a boolean flag feature.
     */
    public function isBooleanType(): bool
    {
        return $this->type === FeatureType::Boolean;
    }
}
