<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\EmailVerificationController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\Company\CompanyAuthorizationController;
use App\Http\Controllers\Api\V1\Company\CompanyController;
use App\Http\Controllers\Api\V1\Company\CompanyFeatureController;
use App\Http\Controllers\Api\V1\Company\CompanyPlanController;
use App\Http\Controllers\Api\V1\Customer\CustomerController;
use App\Http\Controllers\Api\V1\Invoice\InvoiceController;
use App\Http\Controllers\Api\V1\Member\CompanyMemberController;
use App\Http\Controllers\Api\V1\Plan\PlanController;
use App\Http\Controllers\Api\V1\Product\ProductController;
use App\Http\Controllers\Api\V1\Profile\ProfileController;
use App\Http\Controllers\Api\V1\Role\RoleController;
use App\Http\Controllers\Api\V1\Subscription\BillingController;
use App\Http\Controllers\Api\V1\Webhook\StripeWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    // Phase 9: Stripe Webhooks (unauthenticated, signature-verified)
    Route::post('webhooks/stripe', StripeWebhookController::class)->name('api.v1.webhooks.stripe');
    // Guest authentication routes with endpoint-specific rate limiters
    Route::prefix('auth')->group(function (): void {
        Route::post('register', [AuthController::class, 'register'])
            ->middleware('throttle:auth.register')
            ->name('api.v1.auth.register');

        Route::post('login', [AuthController::class, 'login'])
            ->middleware('throttle:auth.login')
            ->name('api.v1.auth.login');

        Route::post('forgot-password', [PasswordResetController::class, 'forgotPassword'])
            ->middleware('throttle:auth.password-reset')
            ->name('api.v1.auth.forgot-password');

        Route::post('reset-password', [PasswordResetController::class, 'resetPassword'])
            ->middleware('throttle:auth.password-reset')
            ->name('api.v1.auth.reset-password');

        Route::post('verify-email', [EmailVerificationController::class, 'verify'])
            ->middleware('throttle:auth.verification')
            ->name('api.v1.auth.verify-email');
    });

    // Authenticated routes with global API rate limiter
    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {
        // Authenticated auth routes
        Route::prefix('auth')->group(function (): void {
            Route::get('me', [AuthController::class, 'me'])->name('api.v1.auth.me');
            Route::post('logout', [AuthController::class, 'logout'])->name('api.v1.auth.logout');
            Route::post('change-password', [AuthController::class, 'changePassword'])->name('api.v1.auth.change-password');
            Route::post('email/resend', [EmailVerificationController::class, 'resend'])
                ->middleware('throttle:auth.verification')
                ->name('api.v1.auth.email.resend');
        });

        // User profile management
        Route::prefix('profile')->group(function (): void {
            Route::get('/', [ProfileController::class, 'show'])->name('api.v1.profile.show');
            Route::put('/', [ProfileController::class, 'update'])->name('api.v1.profile.update');
            Route::post('verify-email-change', [ProfileController::class, 'verifyEmailChange'])->name('api.v1.profile.verify-email-change');
            Route::delete('pending-email', [ProfileController::class, 'cancelPendingEmail'])->name('api.v1.profile.cancel-pending-email');
        });

        // Company management and context routes
        Route::prefix('companies')->group(function (): void {
            Route::get('/', [CompanyController::class, 'index'])->name('api.v1.companies.index');
            Route::post('/', [CompanyController::class, 'store'])->name('api.v1.companies.store');
            Route::post('switch', [CompanyController::class, 'switch'])->name('api.v1.companies.switch');
            Route::get('current', [CompanyController::class, 'current'])->middleware('company.access')->name('api.v1.companies.current');

            Route::get('{company}', [CompanyController::class, 'show'])->middleware('company.access')->name('api.v1.companies.show');
            Route::put('{company}', [CompanyController::class, 'update'])->middleware('company.access')->name('api.v1.companies.update');
            Route::post('{company}/switch', [CompanyController::class, 'switch'])->name('api.v1.companies.switch-by-id');
            Route::post('{company}/deactivate', [CompanyController::class, 'deactivate'])->middleware('company.access')->name('api.v1.companies.deactivate');
            Route::post('{company}/reactivate', [CompanyController::class, 'reactivate'])->middleware('company.access:allow_inactive')->name('api.v1.companies.reactivate');

            // Phase 3: Authorization, Roles, and Members
            Route::get('{company}/authorization', CompanyAuthorizationController::class)
                ->middleware('company.access')
                ->name('api.v1.companies.authorization');
            Route::get('{company}/roles', [RoleController::class, 'index'])
                ->middleware('company.access')
                ->name('api.v1.companies.roles');
            Route::get('{company}/members', [CompanyMemberController::class, 'index'])
                ->middleware('company.access')
                ->name('api.v1.companies.members.index');
            Route::post('{company}/members/{member}/role', [CompanyMemberController::class, 'assignRole'])
                ->middleware('company.access')
                ->name('api.v1.companies.members.role');

            // Phase 4: Plan and Feature Access
            Route::get('{company}/plan', [CompanyPlanController::class, 'show'])
                ->middleware('company.access')
                ->name('api.v1.companies.plan');
            Route::get('{company}/features', [CompanyFeatureController::class, 'index'])
                ->middleware('company.access')
                ->name('api.v1.companies.features');

            // Phase 5: Customers
            Route::get('{company}/customers', [CustomerController::class, 'index'])
                ->middleware('company.access')
                ->name('api.v1.companies.customers.index');
            Route::post('{company}/customers', [CustomerController::class, 'store'])
                ->middleware('company.access')
                ->name('api.v1.companies.customers.store');
            Route::get('{company}/customers/{customer}', [CustomerController::class, 'show'])
                ->middleware('company.access')
                ->name('api.v1.companies.customers.show');
            Route::put('{company}/customers/{customer}', [CustomerController::class, 'update'])
                ->middleware('company.access')
                ->name('api.v1.companies.customers.update');
            Route::post('{company}/customers/{customer}/archive', [CustomerController::class, 'archive'])
                ->middleware('company.access')
                ->name('api.v1.companies.customers.archive');
            Route::post('{company}/customers/{customer}/restore', [CustomerController::class, 'restore'])
                ->middleware('company.access')
                ->name('api.v1.companies.customers.restore');

            // Phase 6: Products & Services
            Route::get('{company}/products', [ProductController::class, 'index'])
                ->middleware('company.access')
                ->name('api.v1.companies.products.index');
            Route::post('{company}/products', [ProductController::class, 'store'])
                ->middleware('company.access')
                ->name('api.v1.companies.products.store');
            Route::get('{company}/products/{product}', [ProductController::class, 'show'])
                ->middleware('company.access')
                ->name('api.v1.companies.products.show');
            Route::put('{company}/products/{product}', [ProductController::class, 'update'])
                ->middleware('company.access')
                ->name('api.v1.companies.products.update');
            Route::patch('{company}/products/{product}/status', [ProductController::class, 'updateStatus'])
                ->middleware('company.access')
                ->name('api.v1.companies.products.status');
            Route::delete('{company}/products/{product}', [ProductController::class, 'destroy'])
                ->middleware('company.access')
                ->name('api.v1.companies.products.destroy');
            Route::post('{company}/products/{product}/restore', [ProductController::class, 'restore'])
                ->middleware('company.access')
                ->name('api.v1.companies.products.restore');

            // Phase 7: Invoices
            Route::get('{company}/invoices', [InvoiceController::class, 'index'])
                ->middleware('company.access')
                ->name('api.v1.companies.invoices.index');
            Route::post('{company}/invoices', [InvoiceController::class, 'store'])
                ->middleware('company.access')
                ->name('api.v1.companies.invoices.store');
            Route::get('{company}/invoices/{invoice}', [InvoiceController::class, 'show'])
                ->middleware('company.access')
                ->name('api.v1.companies.invoices.show');
            Route::put('{company}/invoices/{invoice}', [InvoiceController::class, 'update'])
                ->middleware('company.access')
                ->name('api.v1.companies.invoices.update');
            Route::delete('{company}/invoices/{invoice}', [InvoiceController::class, 'destroy'])
                ->middleware('company.access')
                ->name('api.v1.companies.invoices.destroy');
            Route::post('{company}/invoices/{invoice}/restore', [InvoiceController::class, 'restore'])
                ->middleware('company.access')
                ->name('api.v1.companies.invoices.restore');
            Route::post('{company}/invoices/{invoice}/send', [InvoiceController::class, 'send'])
                ->middleware('company.access')
                ->name('api.v1.companies.invoices.send');
            Route::post('{company}/invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])
                ->middleware('company.access')
                ->name('api.v1.companies.invoices.cancel');
            Route::get('{company}/invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])
                ->middleware('company.access')
                ->name('api.v1.companies.invoices.pdf');
            Route::post('{company}/invoices/{invoice}/email', [InvoiceController::class, 'email'])
                ->middleware('company.access')
                ->name('api.v1.companies.invoices.email');
            Route::get('{company}/invoices/{invoice}/deliveries', [InvoiceController::class, 'deliveries'])
                ->middleware('company.access')
                ->name('api.v1.companies.invoices.deliveries');

            // Phase 9: Subscription & Stripe Billing
            Route::prefix('{company}/subscription')->middleware('company.access')->group(function (): void {
                Route::get('/', [BillingController::class, 'show'])
                    ->middleware('company.permission:billing.view')
                    ->name('api.v1.companies.subscription.show');
                Route::post('checkout', [BillingController::class, 'checkout'])
                    ->middleware('company.permission:billing.manage')
                    ->name('api.v1.companies.subscription.checkout');
                Route::post('portal', [BillingController::class, 'portal'])
                    ->middleware('company.permission:billing.manage')
                    ->name('api.v1.companies.subscription.portal');
                Route::post('cancel', [BillingController::class, 'cancel'])
                    ->middleware('company.permission:billing.manage')
                    ->name('api.v1.companies.subscription.cancel');
                Route::post('resume', [BillingController::class, 'resume'])
                    ->middleware('company.permission:billing.manage')
                    ->name('api.v1.companies.subscription.resume');
                Route::post('verify-session', [BillingController::class, 'verifySession'])
                    ->middleware('company.permission:billing.manage')
                    ->name('api.v1.companies.subscription.verify-session');
                Route::post('sync', [BillingController::class, 'sync'])
                    ->middleware('company.permission:billing.manage')
                    ->name('api.v1.companies.subscription.sync');
                Route::get('history', [BillingController::class, 'history'])
                    ->middleware('company.permission:billing.view')
                    ->name('api.v1.companies.subscription.history');
            });
        });
    });

    // Phase 4: Public / Authenticated Plan Catalog
    Route::get('plans', [PlanController::class, 'index'])->name('api.v1.plans.index');
});
