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

class EnsureCompanyPermission
{
    public function __construct(
        private readonly CompanyContext $companyContext,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user) {
            return ApiResponse::error('Unauthenticated.', [], 401);
        }

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

        foreach ($permissions as $permission) {
            if (! $user->hasPermissionInCompany($company, $permission)) {
                return ApiResponse::error('You do not have permission to perform this action in this workspace.', [], 403);
            }
        }

        return $next($request);
    }
}
