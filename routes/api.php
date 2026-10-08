<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\EmailVerificationController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\Company\CompanyController;
use App\Http\Controllers\Api\V1\Profile\ProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
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
        });
    });
});
