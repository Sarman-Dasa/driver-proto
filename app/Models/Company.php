<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CompanyStatus;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
}
