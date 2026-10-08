<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Company;
use App\Models\User;
use App\Services\CompanyContext;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyAccess
{
    /**
     * Create a new middleware instance.
     */
    public function __construct(
        private readonly CompanyContext $companyContext,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, ?string $mode = null): Response
    {
        // Reset any leftover context to ensure complete request isolation
        $this->companyContext->clear();

        /** @var User|null $user */
        $user = $request->user();

        if (! $user) {
            return ApiResponse::error('Unauthenticated.', [], 401);
        }

        // 1. Resolve company identifier from route, headers, or query
        $routeParam = $request->route('company');
        $identifier = $routeParam ?? $request->header('X-Company-Id') ?? $request->header('X-Company') ?? $request->query('company_id');

        $company = null;

        if ($identifier instanceof Company) {
            $company = $identifier;
        } elseif ($identifier !== null && $identifier !== '') {
            $company = is_numeric($identifier)
                ? Company::query()->find((int) $identifier)
                : Company::query()->where('slug', (string) $identifier)->first();

            if (! $company) {
                return ApiResponse::error('Company not found.', [], 404);
            }
        } else {
            // No company specified: fallback to user's first active company if available
            $company = $user->activeCompanies()->first();

            if (! $company && $mode === 'required') {
                return ApiResponse::error('Company context is required.', [], 400);
            }

            if (! $company) {
                return ApiResponse::error('You do not have access to any active company.', [], 403);
            }
        }

        // 2. Validate that authenticated user belongs to the company
        if (! $user->belongsToCompany($company)) {
            return ApiResponse::error('You do not have access to this company.', [], 403);
        }

        // 3. Validate that the company is active unless explicitly allowed
        if ($mode !== 'allow_inactive' && ! $company->isActive()) {
            return ApiResponse::error('This company is inactive.', [], 403);
        }

        // 4. Set company in context and request attributes
        $this->companyContext->setCompany($company);
        $request->attributes->set('current_company', $company);

        return $next($request);
    }

    /**
     * Clean up company context after response has been sent to prevent cross-request leakage.
     */
    public function terminate(Request $request, Response $response): void
    {
        $this->companyContext->clear();
    }
}
