<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Company;
use App\Services\CompanyContext;
use App\Services\FeatureAccessService;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyFeature
{
    public function __construct(
        private readonly CompanyContext $companyContext,
        private readonly FeatureAccessService $featureAccessService,
    ) {}

    /**
     * Handle an incoming request and ensure current company has the required plan feature(s).
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$features): Response
    {
        $company = $this->companyContext->getCompany() ?? $request->attributes->get('current_company');

        if (! $company instanceof Company) {
            $routeParam = $request->route('company');
            if ($routeParam instanceof Company) {
                $company = $routeParam;
            } elseif ($routeParam !== null && $routeParam !== '') {
                $company = is_numeric($routeParam)
                    ? Company::query()->find((int) $routeParam)
                    : Company::query()->where('slug', (string) $routeParam)->first();
            }
        }

        if (! $company instanceof Company) {
            return ApiResponse::error('Company context is required.', [], 400);
        }

        foreach ($features as $feature) {
            if (! $this->featureAccessService->canUseFeature($company, $feature)) {
                return ApiResponse::error('This feature is not available on your current plan.', [], 403);
            }
        }

        return $next($request);
    }
}
