<?php

declare(strict_types=1);

namespace App\Actions\Company;

use App\Enums\CompanyStatus;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateCompanyAction
{
    /**
     * Create a new company and attach the user as a member.
     *
     * @param  array{name: string, email?: ?string, phone?: ?string}  $data
     */
    public function execute(User $user, array $data): Company
    {
        return DB::transaction(function () use ($user, $data): Company {
            $baseSlug = Str::slug($data['name']);
            $slug = $baseSlug ?: 'company';
            $uniqueSlug = $slug.'-'.Str::lower(Str::random(6));

            $company = Company::create([
                'name' => $data['name'],
                'slug' => $uniqueSlug,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'status' => CompanyStatus::Active,
            ]);

            $user->companies()->attach($company->id, ['role_id' => null]);

            return $company;
        });
    }
}
