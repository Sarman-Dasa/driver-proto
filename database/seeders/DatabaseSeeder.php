<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Invoice\CreateInvoiceAction;
use App\Actions\Invoice\SendInvoiceAction;
use App\Enums\CompanyStatus;
use App\Enums\CustomerStatus;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Enums\SubscriptionStatus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Product;
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
        // Seed sample products and services
        Product::query()->firstOrCreate(
            ['company_id' => $company->id, 'sku' => 'WEB-DEV-001'],
            [
                'name' => 'Website Development',
                'type' => ProductType::Service,
                'description' => 'Professional full-stack website development and design.',
                'price' => 200000,
                'currency' => 'USD',
                'tax_rate' => 18.00,
                'unit' => 'project',
                'status' => ProductStatus::Active,
            ]
        );

        Product::query()->firstOrCreate(
            ['company_id' => $company->id, 'sku' => 'HOST-001'],
            [
                'name' => 'Cloud Hosting & Maintenance',
                'type' => ProductType::Service,
                'description' => 'Managed cloud infrastructure, backup and server maintenance.',
                'price' => 20000,
                'currency' => 'USD',
                'tax_rate' => 10.00,
                'unit' => 'month',
                'status' => ProductStatus::Active,
            ]
        );

        Product::query()->firstOrCreate(
            ['company_id' => $company->id, 'sku' => 'LAP-001'],
            [
                'name' => 'Developer Workstation Laptop',
                'type' => ProductType::Product,
                'description' => 'High-performance laptop for software engineering.',
                'price' => 150000,
                'currency' => 'USD',
                'tax_rate' => 5.00,
                'unit' => 'piece',
                'status' => ProductStatus::Active,
            ]
        );

        Product::query()->firstOrCreate(
            ['company_id' => $secondaryCompany->id, 'sku' => 'ARC-MNT-001'],
            [
                'name' => 'Arc Reactor Maintenance',
                'type' => ProductType::Service,
                'description' => 'Clean energy generator calibration and plasma containment inspection.',
                'price' => 5000000,
                'currency' => 'USD',
                'tax_rate' => 18.00,
                'unit' => 'service',
                'status' => ProductStatus::Active,
            ]
        );

        Product::query()->firstOrCreate(
            ['company_id' => $secondaryCompany->id, 'sku' => 'REP-001'],
            [
                'name' => 'Mark VII Repulsor Stabilizer',
                'type' => ProductType::Product,
                'description' => 'Flight stabilization and energy dampening unit.',
                'price' => 1250000,
                'currency' => 'USD',
                'tax_rate' => 12.00,
                'unit' => 'piece',
                'status' => ProductStatus::Active,
            ]
        );

        // Seed sample customers
        $acmeCustomer = Customer::query()->firstOrCreate(
            ['company_id' => $company->id, 'email' => 'client@globex.test'],
            [
                'name' => 'Hank Scorpio',
                'company_name' => 'Globex Corporation',
                'phone' => '+1 555-4321',
                'billing_address' => '100 Cypress Creek Way',
                'billing_city' => 'Cypress Creek',
                'billing_state' => 'OR',
                'billing_postal_code' => '97401',
                'billing_country' => 'USA',
                'status' => CustomerStatus::Active,
            ]
        );

        $starkCustomer = Customer::query()->firstOrCreate(
            ['company_id' => $secondaryCompany->id, 'email' => 'rhodey@defense.gov'],
            [
                'name' => 'James Rhodes',
                'company_name' => 'Department of Defense',
                'phone' => '+1 555-8899',
                'billing_address' => 'The Pentagon',
                'billing_city' => 'Arlington',
                'billing_state' => 'VA',
                'billing_postal_code' => '22202',
                'billing_country' => 'USA',
                'status' => CustomerStatus::Active,
            ]
        );

        // Seed sample invoices if none exist
        if ($company->invoices()->count() === 0) {
            /** @var CreateInvoiceAction $createInvoiceAction */
            $createInvoiceAction = app(CreateInvoiceAction::class);

            $inv1 = $createInvoiceAction->execute($company, [
                'customer_id' => $acmeCustomer->id,
                'issue_date' => now()->format('Y-m-d'),
                'due_date' => now()->addDays(30)->format('Y-m-d'),
                'currency' => 'USD',
                'discount_type' => 'percentage',
                'discount_value' => 10.0,
                'notes' => 'Thank you for partnering with Acme Corporation.',
                'terms' => 'Payment due within 30 days of issue.',
                'items' => [
                    [
                        'product_name' => 'Website Development',
                        'description' => 'Custom full-stack web application development',
                        'quantity' => 1,
                        'unit' => 'project',
                        'unit_price' => 200000,
                        'tax_rate' => 18.0,
                    ],
                    [
                        'product_name' => 'Cloud Hosting & Maintenance',
                        'description' => 'Monthly dedicated high-availability hosting',
                        'quantity' => 12,
                        'unit' => 'month',
                        'unit_price' => 20000,
                        'tax_rate' => 10.0,
                    ],
                ],
            ], $user);

            // Mark invoice 1 as sent
            app(SendInvoiceAction::class)->execute($company, $inv1, $user);

            // Create draft invoice 2
            $createInvoiceAction->execute($company, [
                'customer_id' => $acmeCustomer->id,
                'issue_date' => now()->format('Y-m-d'),
                'due_date' => now()->addDays(14)->format('Y-m-d'),
                'currency' => 'USD',
                'notes' => 'Draft invoice for upcoming consultation services.',
                'items' => [
                    [
                        'product_name' => 'Developer Workstation Laptop',
                        'description' => 'Configured hardware setup',
                        'quantity' => 2,
                        'unit' => 'piece',
                        'unit_price' => 150000,
                        'tax_rate' => 5.0,
                    ],
                ],
            ], $user);
        }
    }
}
