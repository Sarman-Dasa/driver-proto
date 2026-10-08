<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CompanyStatus;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'pending_email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * The companies that belong to the user.
     *
     * @return BelongsToMany<Company, $this>
     */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_users')
            ->withPivot(['id', 'role_id'])
            ->withTimestamps();
    }

    /**
     * Get only the active companies that belong to the user.
     *
     * @return BelongsToMany<Company, $this>
     */
    public function activeCompanies(): BelongsToMany
    {
        return $this->companies()->where('companies.status', CompanyStatus::Active->value);
    }

    /**
     * The companies owned by the user.
     *
     * @return HasMany<Company, $this>
     */
    public function ownedCompanies(): HasMany
    {
        return $this->hasMany(Company::class, 'owner_id');
    }

    /**
     * Determine if the user belongs to the given company.
     */
    public function belongsToCompany(Company|int|string $company): bool
    {
        $companyId = $company instanceof Company ? $company->id : (int) $company;

        return $this->companies()->where('companies.id', $companyId)->exists();
    }

    /**
     * Determine if the user owns the given company.
     */
    public function isOwnerOf(Company|int|string $company): bool
    {
        $companyId = $company instanceof Company ? $company->id : (int) $company;

        return $this->ownedCompanies()->where('id', $companyId)->exists();
    }

    /**
     * Get the user's role in the given company.
     */
    public function roleInCompany(Company|int|string $company): ?Role
    {
        $companyId = $company instanceof Company ? $company->id : (int) $company;

        $membership = DB::table('company_users')
            ->where('user_id', $this->id)
            ->where('company_id', $companyId)
            ->first();

        if (! $membership) {
            return null;
        }

        if ($membership->role_id) {
            return Role::with('permissions')->find($membership->role_id);
        }

        // Fallback: if owner, return owner role
        if ($this->isOwnerOf($companyId)) {
            return Role::with('permissions')->where('slug', Role::OWNER)->first();
        }

        return null;
    }

    /**
     * Determine if the user has a specific role (or one of multiple roles) in the given company.
     *
     * @param  string|array<string>  $roles
     */
    public function hasRoleInCompany(Company|int|string $company, string|array $roles): bool
    {
        $role = $this->roleInCompany($company);

        if (! $role) {
            return false;
        }

        $expected = is_array($roles) ? $roles : [$roles];

        return in_array($role->slug, $expected, true);
    }

    /**
     * Get all permission slugs for the user in the given company.
     *
     * @return Collection<int, string>
     */
    public function permissionsInCompany(Company|int|string $company): Collection
    {
        $role = $this->roleInCompany($company);

        if (! $role) {
            return collect();
        }

        return $role->permissions->pluck('slug');
    }

    /**
     * Determine if the user has a specific permission in the given company.
     */
    public function hasPermissionInCompany(Company|int|string $company, string $permission): bool
    {
        $role = $this->roleInCompany($company);

        if (! $role) {
            return false;
        }

        if ($role->slug === Role::OWNER) {
            return true;
        }

        return $role->permissions->contains('slug', $permission);
    }

    /**
     * Alias for hasPermissionInCompany.
     */
    public function canInCompany(string $permission, Company|int|string $company): bool
    {
        return $this->hasPermissionInCompany($company, $permission);
    }
}
