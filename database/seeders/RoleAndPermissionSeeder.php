<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Define and seed all standard permissions
        $permissions = [
            // Company
            ['slug' => 'company.view', 'name' => 'View Workspace', 'group' => 'company', 'description' => 'View workspace details and overview.'],
            ['slug' => 'company.update', 'name' => 'Update Workspace', 'group' => 'company', 'description' => 'Update workspace profile, address, and settings.'],
            ['slug' => 'company.deactivate', 'name' => 'Deactivate Workspace', 'group' => 'company', 'description' => 'Deactivate the workspace lifecycle.'],
            ['slug' => 'company.reactivate', 'name' => 'Reactivate Workspace', 'group' => 'company', 'description' => 'Reactivate an inactive workspace.'],

            // Roles & Team
            ['slug' => 'roles.view', 'name' => 'View Roles', 'group' => 'roles', 'description' => 'View company roles and permissions.'],
            ['slug' => 'roles.manage', 'name' => 'Manage Roles', 'group' => 'roles', 'description' => 'Assign and modify roles for workspace members.'],
            ['slug' => 'team.view', 'name' => 'View Team Members', 'group' => 'team', 'description' => 'View company members and memberships.'],
            ['slug' => 'team.manage', 'name' => 'Manage Team', 'group' => 'team', 'description' => 'Manage and invite team members.'],

            // Operational Settings
            ['slug' => 'settings.view', 'name' => 'View Settings', 'group' => 'settings', 'description' => 'View operational workspace preferences.'],
            ['slug' => 'settings.update', 'name' => 'Update Settings', 'group' => 'settings', 'description' => 'Modify operational workspace preferences.'],

            // Billing & Subscriptions (Future Catalog)
            ['slug' => 'billing.view', 'name' => 'View Billing', 'group' => 'billing', 'description' => 'View subscription status and payment history.'],
            ['slug' => 'billing.manage', 'name' => 'Manage Billing', 'group' => 'billing', 'description' => 'Manage subscription plans and payment methods.'],
            ['slug' => 'stripe.view', 'name' => 'View Stripe Connect', 'group' => 'stripe', 'description' => 'View Stripe Connect account status.'],
            ['slug' => 'stripe.manage', 'name' => 'Manage Stripe Connect', 'group' => 'stripe', 'description' => 'Connect and configure Stripe account.'],

            // Customers (Future Catalog)
            ['slug' => 'customers.view', 'name' => 'View Customers', 'group' => 'customers', 'description' => 'View company customers and clients.'],
            ['slug' => 'customers.create', 'name' => 'Create Customers', 'group' => 'customers', 'description' => 'Create new customers.'],
            ['slug' => 'customers.update', 'name' => 'Update Customers', 'group' => 'customers', 'description' => 'Modify customer records.'],
            ['slug' => 'customers.delete', 'name' => 'Delete Customers', 'group' => 'customers', 'description' => 'Delete customer records.'],

            // Products & Services (Future Catalog)
            ['slug' => 'products.view', 'name' => 'View Products', 'group' => 'products', 'description' => 'View catalog products and services.'],
            ['slug' => 'products.create', 'name' => 'Create Products', 'group' => 'products', 'description' => 'Create catalog products and services.'],
            ['slug' => 'products.update', 'name' => 'Update Products', 'group' => 'products', 'description' => 'Modify catalog products and services.'],
            ['slug' => 'products.delete', 'name' => 'Delete Products', 'group' => 'products', 'description' => 'Remove catalog products and services.'],

            // Invoices (Future Catalog)
            ['slug' => 'invoices.view', 'name' => 'View Invoices', 'group' => 'invoices', 'description' => 'View issued invoices and drafts.'],
            ['slug' => 'invoices.create', 'name' => 'Create Invoices', 'group' => 'invoices', 'description' => 'Create and draft invoices.'],
            ['slug' => 'invoices.update', 'name' => 'Update Invoices', 'group' => 'invoices', 'description' => 'Edit drafted invoices.'],
            ['slug' => 'invoices.delete', 'name' => 'Delete Invoices', 'group' => 'invoices', 'description' => 'Delete drafted invoices.'],
            ['slug' => 'invoices.send', 'name' => 'Send Invoices', 'group' => 'invoices', 'description' => 'Send invoices directly to clients.'],

            // Payments (Future Catalog)
            ['slug' => 'payments.view', 'name' => 'View Payments', 'group' => 'payments', 'description' => 'View transaction payments.'],
            ['slug' => 'payments.create', 'name' => 'Record Payments', 'group' => 'payments', 'description' => 'Manually record invoice payments.'],

            // Expenses (Future Catalog)
            ['slug' => 'expenses.view', 'name' => 'View Expenses', 'group' => 'expenses', 'description' => 'View tracked business expenses.'],
            ['slug' => 'expenses.create', 'name' => 'Record Expenses', 'group' => 'expenses', 'description' => 'Record business expenses.'],
            ['slug' => 'expenses.update', 'name' => 'Update Expenses', 'group' => 'expenses', 'description' => 'Edit recorded business expenses.'],
            ['slug' => 'expenses.delete', 'name' => 'Delete Expenses', 'group' => 'expenses', 'description' => 'Remove recorded business expenses.'],

            // Reports (Future Catalog)
            ['slug' => 'reports.view', 'name' => 'View Reports', 'group' => 'reports', 'description' => 'View financial reports and analytical summaries.'],
        ];

        $permissionModels = [];
        foreach ($permissions as $perm) {
            $permissionModels[$perm['slug']] = Permission::query()->firstOrCreate(
                ['slug' => $perm['slug']],
                [
                    'name' => $perm['name'],
                    'group' => $perm['group'],
                    'description' => $perm['description'],
                ],
            );
        }

        // 2. Define standard system roles
        $ownerRole = Role::query()->firstOrCreate(
            ['slug' => Role::OWNER],
            [
                'name' => 'Owner',
                'description' => 'Complete control over workspace configuration, members, and all business resources.',
                'is_system' => true,
            ],
        );

        $adminRole = Role::query()->firstOrCreate(
            ['slug' => Role::ADMIN],
            [
                'name' => 'Admin',
                'description' => 'Operational workspace management and full business resources access.',
                'is_system' => true,
            ],
        );

        $accountantRole = Role::query()->firstOrCreate(
            ['slug' => Role::ACCOUNTANT],
            [
                'name' => 'Accountant',
                'description' => 'Dedicated financial access to manage invoices, payments, expenses, and view reports.',
                'is_system' => true,
            ],
        );

        $memberRole = Role::query()->firstOrCreate(
            ['slug' => Role::MEMBER],
            [
                'name' => 'Member',
                'description' => 'Standard collaborative access for daily business operations.',
                'is_system' => true,
            ],
        );

        // 3. Assign role-permission relationships
        // Owner gets ALL permissions
        $ownerRole->permissions()->sync(array_values(array_map(fn ($p) => $p->id, $permissionModels)));

        // Admin permissions: Operational resources and team, but NOT owner-only lifecycle or ownership management
        $adminSlugs = [
            'company.view',
            'roles.view',
            'team.view',
            'team.manage',
            'settings.view',
            'settings.update',
            'billing.view',
            'stripe.view',
            'customers.view',
            'customers.create',
            'customers.update',
            'customers.delete',
            'products.view',
            'products.create',
            'products.update',
            'products.delete',
            'invoices.view',
            'invoices.create',
            'invoices.update',
            'invoices.delete',
            'invoices.send',
            'payments.view',
            'payments.create',
            'expenses.view',
            'expenses.create',
            'expenses.update',
            'expenses.delete',
            'reports.view',
        ];
        $adminPermissionIds = array_map(fn ($slug) => $permissionModels[$slug]->id, array_filter($adminSlugs, fn ($slug) => isset($permissionModels[$slug])));
        $adminRole->permissions()->sync($adminPermissionIds);

        // Accountant permissions: Financial focused
        $accountantSlugs = [
            'company.view',
            'customers.view',
            'products.view',
            'invoices.view',
            'invoices.create',
            'invoices.update',
            'invoices.send',
            'payments.view',
            'payments.create',
            'expenses.view',
            'expenses.create',
            'expenses.update',
            'expenses.delete',
            'reports.view',
        ];
        $accountantPermissionIds = array_map(fn ($slug) => $permissionModels[$slug]->id, array_filter($accountantSlugs, fn ($slug) => isset($permissionModels[$slug])));
        $accountantRole->permissions()->sync($accountantPermissionIds);

        // Member permissions: Basic day-to-day operations
        $memberSlugs = [
            'company.view',
            'customers.view',
            'products.view',
            'invoices.view',
            'invoices.create',
            'invoices.update',
            'payments.view',
            'expenses.view',
            'reports.view',
        ];
        $memberPermissionIds = array_map(fn ($slug) => $permissionModels[$slug]->id, array_filter($memberSlugs, fn ($slug) => isset($permissionModels[$slug])));
        $memberRole->permissions()->sync($memberPermissionIds);
    }
}
