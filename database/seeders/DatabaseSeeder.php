<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CompanyStatus;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database with development data.
     */
    public function run(): void
    {
        // Development user
        $user = User::query()->firstOrCreate(
            ['email' => 'developer@billflow.test'],
            [
                'name' => 'BillFlow Developer',
                'password' => Hash::make('Password123!'),
                'email_verified_at' => now(),
            ],
        );

        // Development primary company
        $company = Company::query()->firstOrCreate(
            ['slug' => 'acme-corp'],
            [
                'name' => 'Acme Corporation',
                'email' => 'contact@acmecorp.test',
                'phone' => '+1 555-0100',
                'status' => CompanyStatus::Active,
            ],
        );

        // Development secondary company to test company switching
        $secondaryCompany = Company::query()->firstOrCreate(
            ['slug' => 'stark-industries'],
            [
                'name' => 'Stark Industries',
                'email' => 'info@starkindustries.test',
                'phone' => '+1 555-0199',
                'status' => CompanyStatus::Active,
            ],
        );

        // Attach company memberships if not attached
        if (! $user->belongsToCompany($company)) {
            $user->companies()->attach($company->id, ['role_id' => null]);
        }

        if (! $user->belongsToCompany($secondaryCompany)) {
            $user->companies()->attach($secondaryCompany->id, ['role_id' => null]);
        }
    }
}
