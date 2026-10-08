<?php

declare(strict_types=1);

namespace App\Actions\Company;

use App\Enums\CompanyStatus;
use App\Models\Company;
use App\Models\User;
use App\Services\CompanyContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateCompanyAction
{
    public function __construct(
        private readonly CompanyContext $companyContext,
    ) {}

    /**
     * Create a new company and attach the user as owner and member.
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(User $user, array $data): Company
    {
        return DB::transaction(function () use ($user, $data): Company {
            $baseSlug = Str::slug((string) ($data['name'] ?? ''));
            $baseSlug = $baseSlug !== '' ? $baseSlug : 'company';
            $slug = $baseSlug;
            $counter = 2;

            while (Company::query()->where('slug', $slug)->exists()) {
                $slug = "{$baseSlug}-{$counter}";
                $counter++;
            }

            $company = Company::create([
                'name' => $data['name'],
                'slug' => $slug,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'website' => $data['website'] ?? null,
                'address' => $data['address'] ?? null,
                'city' => $data['city'] ?? null,
                'state' => $data['state'] ?? null,
                'postal_code' => $data['postal_code'] ?? null,
                'country' => $data['country'] ?? null,
                'tax_number' => $data['tax_number'] ?? null,
                'currency' => $data['currency'] ?? 'USD',
                'timezone' => $data['timezone'] ?? 'UTC',
                'status' => CompanyStatus::Active,
                'owner_id' => $user->id,
            ]);

            $user->companies()->attach($company->id, ['role_id' => null]);

            $this->companyContext->setCompany($company);

            return $company;
        });
    }
}
