<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CompanyStatus;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'slug', 'email', 'phone', 'logo', 'status'])]
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
}
