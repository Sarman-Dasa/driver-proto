<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\FeatureType;
use App\Enums\LimitPeriod;
use App\Models\Feature;
use App\Models\Plan;
use App\Models\PlanFeature;
use Illuminate\Database\Seeder;

class PlanAndFeatureSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Seed Feature Catalog
        $features = [
            [
                'key' => Feature::CUSTOMERS,
                'name' => 'Customers',
                'description' => 'Maximum number of client contacts managed.',
                'type' => FeatureType::Limit,
                'limit_period' => LimitPeriod::Total,
            ],
            [
                'key' => Feature::INVOICES,
                'name' => 'Invoices',
                'description' => 'Number of invoices created per month.',
                'type' => FeatureType::Limit,
                'limit_period' => LimitPeriod::Monthly,
            ],
            [
                'key' => Feature::EXPENSES,
                'name' => 'Expenses Tracking',
                'description' => 'Track business expenses and manage receipts.',
                'type' => FeatureType::Boolean,
                'limit_period' => LimitPeriod::None,
            ],
            [
                'key' => Feature::ONLINE_PAYMENTS,
                'name' => 'Online Payments',
                'description' => 'Accept card and online invoice payments.',
                'type' => FeatureType::Boolean,
                'limit_period' => LimitPeriod::None,
            ],
            [
                'key' => Feature::STRIPE_CONNECT,
                'name' => 'Stripe Connect',
                'description' => 'Connect custom merchant Stripe account.',
                'type' => FeatureType::Boolean,
                'limit_period' => LimitPeriod::None,
            ],
            [
                'key' => Feature::REPORTS_BASIC,
                'name' => 'Basic Reports',
                'description' => 'Overview of workspace revenue and cashflow.',
                'type' => FeatureType::Boolean,
                'limit_period' => LimitPeriod::None,
            ],
            [
                'key' => Feature::REPORTS_ADVANCED,
                'name' => 'Advanced Reports',
                'description' => 'Detailed analytics and exportable financial summaries.',
                'type' => FeatureType::Boolean,
                'limit_period' => LimitPeriod::None,
            ],
            [
                'key' => Feature::TEAM_MEMBERS,
                'name' => 'Team Members',
                'description' => 'Number of team members within the workspace.',
                'type' => FeatureType::Limit,
                'limit_period' => LimitPeriod::Total,
            ],
            [
                'key' => Feature::API_ACCESS,
                'name' => 'API Access',
                'description' => 'Developer API keys and automation access.',
                'type' => FeatureType::Boolean,
                'limit_period' => LimitPeriod::None,
            ],
            [
                'key' => Feature::MULTIPLE_COMPANIES,
                'name' => 'Multiple Workspaces',
                'description' => 'Create and manage multiple workspace accounts.',
                'type' => FeatureType::Boolean,
                'limit_period' => LimitPeriod::None,
            ],
            [
                'key' => Feature::PAYMENT_REMINDERS,
                'name' => 'Payment Reminders',
                'description' => 'Automated invoice overdue reminders.',
                'type' => FeatureType::Boolean,
                'limit_period' => LimitPeriod::None,
            ],
        ];

        $featureModels = [];
        foreach ($features as $f) {
            $featureModels[$f['key']] = Feature::query()->firstOrCreate(
                ['key' => $f['key']],
                [
                    'name' => $f['name'],
                    'description' => $f['description'],
                    'type' => $f['type'],
                    'limit_period' => $f['limit_period'],
                ],
            );
        }

        // 2. Seed Plans
        $freePlan = Plan::query()->firstOrCreate(
            ['slug' => Plan::FREE],
            [
                'name' => 'Free',
                'description' => 'Perfect for freelancers and solo entrepreneurs getting started.',
                'price_monthly' => 0,
                'price_yearly' => 0,
                'currency' => 'USD',
                'is_active' => true,
                'is_default' => true,
                'sort_order' => 1,
            ],
        );

        $proPlan = Plan::query()->firstOrCreate(
            ['slug' => Plan::PRO],
            [
                'name' => 'Pro',
                'description' => 'Ideal for growing businesses needing expense tracking and automation.',
                'price_monthly' => 1900,
                'price_yearly' => 19000,
                'currency' => 'USD',
                'is_active' => true,
                'is_default' => false,
                'sort_order' => 2,
            ],
        );

        $businessPlan = Plan::query()->firstOrCreate(
            ['slug' => Plan::BUSINESS],
            [
                'name' => 'Business',
                'description' => 'Full-scale financial operations with team collaboration and API access.',
                'price_monthly' => 4900,
                'price_yearly' => 49000,
                'currency' => 'USD',
                'is_active' => true,
                'is_default' => false,
                'sort_order' => 3,
            ],
        );

        // 3. Map Plan Features
        $freeFeatures = [
            Feature::CUSTOMERS => ['enabled' => true, 'limit' => 5],
            Feature::INVOICES => ['enabled' => true, 'limit' => 10],
            Feature::EXPENSES => ['enabled' => false, 'limit' => null],
            Feature::ONLINE_PAYMENTS => ['enabled' => false, 'limit' => null],
            Feature::STRIPE_CONNECT => ['enabled' => false, 'limit' => null],
            Feature::REPORTS_BASIC => ['enabled' => true, 'limit' => null],
            Feature::REPORTS_ADVANCED => ['enabled' => false, 'limit' => null],
            Feature::TEAM_MEMBERS => ['enabled' => true, 'limit' => 1],
            Feature::API_ACCESS => ['enabled' => false, 'limit' => null],
            Feature::MULTIPLE_COMPANIES => ['enabled' => false, 'limit' => null],
            Feature::PAYMENT_REMINDERS => ['enabled' => false, 'limit' => null],
        ];

        $proFeatures = [
            Feature::CUSTOMERS => ['enabled' => true, 'limit' => null],
            Feature::INVOICES => ['enabled' => true, 'limit' => null],
            Feature::EXPENSES => ['enabled' => true, 'limit' => null],
            Feature::ONLINE_PAYMENTS => ['enabled' => true, 'limit' => null],
            Feature::STRIPE_CONNECT => ['enabled' => true, 'limit' => null],
            Feature::REPORTS_BASIC => ['enabled' => true, 'limit' => null],
            Feature::REPORTS_ADVANCED => ['enabled' => true, 'limit' => null],
            Feature::TEAM_MEMBERS => ['enabled' => true, 'limit' => 5],
            Feature::API_ACCESS => ['enabled' => false, 'limit' => null],
            Feature::MULTIPLE_COMPANIES => ['enabled' => false, 'limit' => null],
            Feature::PAYMENT_REMINDERS => ['enabled' => true, 'limit' => null],
        ];

        $businessFeatures = [
            Feature::CUSTOMERS => ['enabled' => true, 'limit' => null],
            Feature::INVOICES => ['enabled' => true, 'limit' => null],
            Feature::EXPENSES => ['enabled' => true, 'limit' => null],
            Feature::ONLINE_PAYMENTS => ['enabled' => true, 'limit' => null],
            Feature::STRIPE_CONNECT => ['enabled' => true, 'limit' => null],
            Feature::REPORTS_BASIC => ['enabled' => true, 'limit' => null],
            Feature::REPORTS_ADVANCED => ['enabled' => true, 'limit' => null],
            Feature::TEAM_MEMBERS => ['enabled' => true, 'limit' => null],
            Feature::API_ACCESS => ['enabled' => true, 'limit' => null],
            Feature::MULTIPLE_COMPANIES => ['enabled' => true, 'limit' => null],
            Feature::PAYMENT_REMINDERS => ['enabled' => true, 'limit' => null],
        ];

        $this->syncPlanFeatures($freePlan, $freeFeatures, $featureModels);
        $this->syncPlanFeatures($proPlan, $proFeatures, $featureModels);
        $this->syncPlanFeatures($businessPlan, $businessFeatures, $featureModels);
    }

    /**
     * @param  array<string, array{enabled: bool, limit: int|null}>  $matrix
     * @param  array<string, Feature>  $featureModels
     */
    private function syncPlanFeatures(Plan $plan, array $matrix, array $featureModels): void
    {
        foreach ($matrix as $key => $config) {
            $feature = $featureModels[$key] ?? null;
            if ($feature === null) {
                continue;
            }

            PlanFeature::query()->updateOrCreate(
                [
                    'plan_id' => $plan->id,
                    'feature_id' => $feature->id,
                ],
                [
                    'is_enabled' => $config['enabled'],
                    'limit_value' => $config['limit'],
                ],
            );
        }
    }
}
