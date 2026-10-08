<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CompanyStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Role;
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
                'owner_id' => $user->id,
                'currency' => 'USD',
                'timezone' => 'UTC',
            ],
        );
        if ($company->owner_id === null) {
            $company->update(['owner_id' => $user->id]);
        }

        // Development secondary company to test company switching
        $secondaryCompany = Company::query()->firstOrCreate(
            ['slug' => 'stark-industries'],
            [
                'name' => 'Stark Industries',
                'email' => 'info@starkindustries.test',
                'phone' => '+1 555-0199',
                'status' => CompanyStatus::Active,
                'owner_id' => $user->id,
                'currency' => 'USD',
                'timezone' => 'UTC',
            ],
        );
        if ($secondaryCompany->owner_id === null) {
            $secondaryCompany->update(['owner_id' => $user->id]);
        }

        // Seed roles and permissions catalog
        $this->call(RoleAndPermissionSeeder::class);

        // Seed plans and features catalog
        $this->call(PlanAndFeatureSeeder::class);

        /** @var Role|null $ownerRole */
        $ownerRole = Role::query()->where('slug', Role::OWNER)->first();

        // Attach company memberships with owner role
        if (! $user->belongsToCompany($company)) {
            $user->companies()->attach($company->id, ['role_id' => $ownerRole?->id]);
        } else {
            $user->companies()->updateExistingPivot($company->id, ['role_id' => $ownerRole?->id]);
        }

        if (! $user->belongsToCompany($secondaryCompany)) {
            $user->companies()->attach($secondaryCompany->id, ['role_id' => $ownerRole?->id]);
        } else {
            $user->companies()->updateExistingPivot($secondaryCompany->id, ['role_id' => $ownerRole?->id]);
        }

        /** @var Plan|null $freePlan */
        $freePlan = Plan::query()->where('slug', Plan::FREE)->first();
        /** @var Plan|null $proPlan */
        $proPlan = Plan::query()->where('slug', Plan::PRO)->first();

        // Primary company on Free plan
        if ($freePlan !== null && $company->subscriptions()->count() === 0) {
            $company->subscriptions()->create([
                'plan_id' => $freePlan->id,
                'status' => SubscriptionStatus::Active,
                'starts_at' => now(),
                'current_period_start' => now(),
                'current_period_end' => now()->addMonth(),
                'cancel_at_period_end' => false,
            ]);
        }

        // Secondary company on Pro plan for multi-workspace plan testing
        if ($proPlan !== null && $secondaryCompany->subscriptions()->count() === 0) {
            $secondaryCompany->subscriptions()->create([
                'plan_id' => $proPlan->id,
                'status' => SubscriptionStatus::Active,
                'starts_at' => now(),
                'current_period_start' => now(),
                'current_period_end' => now()->addMonth(),
                'cancel_at_period_end' => false,
            ]);
        }
    }
}
