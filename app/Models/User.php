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
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
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
     * Determine if the user belongs to the given company.
     */
    public function belongsToCompany(Company|int|string $company): bool
    {
        $companyId = $company instanceof Company ? $company->id : (int) $company;

        return $this->companies()->where('companies.id', $companyId)->exists();
    }
}
