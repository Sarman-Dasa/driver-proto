<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\CompanyContext;
use App\Services\Stripe\StripeConnectService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CompanyContext::class, function (): CompanyContext {
            return new CompanyContext;
        });

        $this->app->singleton(StripeConnectService::class, function (): StripeConnectService {
            return new StripeConnectService;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('auth.login', function (Request $request) {
            return Limit::perMinute(5)->by($request->input('email').'|'.$request->ip());
        });

        RateLimiter::for('auth.register', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('auth.password-reset', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('auth.verification', function (Request $request) {
            return Limit::perMinute(5)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }
}
