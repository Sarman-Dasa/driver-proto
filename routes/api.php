<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\EmailVerificationController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\Company\CompanyController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    // Guest authentication routes with rate limiting
    Route::prefix('auth')->middleware('throttle:6,1')->group(function (): void {
        Route::post('register', [AuthController::class, 'register'])->name('api.v1.auth.register');
        Route::post('login', [AuthController::class, 'login'])->name('api.v1.auth.login');
        Route::post('forgot-password', [PasswordResetController::class, 'forgotPassword'])->name('api.v1.auth.forgot-password');
        Route::post('reset-password', [PasswordResetController::class, 'resetPassword'])->name('api.v1.auth.reset-password');
        Route::post('verify-email', [EmailVerificationController::class, 'verify'])->name('api.v1.auth.verify-email');
    });

    // Authenticated routes
    Route::middleware('auth:sanctum')->group(function (): void {
        // Authenticated auth routes
        Route::prefix('auth')->group(function (): void {
            Route::get('me', [AuthController::class, 'me'])->name('api.v1.auth.me');
            Route::post('logout', [AuthController::class, 'logout'])->name('api.v1.auth.logout');
            Route::post('email/resend', [EmailVerificationController::class, 'resend'])->name('api.v1.auth.email.resend');
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
