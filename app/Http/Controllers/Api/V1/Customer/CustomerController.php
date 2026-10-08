<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer;

use App\Actions\Customer\ArchiveCustomerAction;
use App\Actions\Customer\CreateCustomerAction;
use App\Actions\Customer\RestoreCustomerAction;
use App\Actions\Customer\UpdateCustomerAction;
use App\Enums\CustomerStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\CreateCustomerRequest;
use App\Http\Requests\Api\V1\Customer\UpdateCustomerRequest;
use App\Http\Resources\Api\V1\CustomerResource;
use App\Models\Company;
use App\Models\Customer;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    use AuthorizesRequests;

    /**
     * List all customers for the company with search, filtering, sorting, and pagination.
     */
    public function index(Request $request, Company $company): JsonResponse
    {
        $this->authorize('viewAny', [Customer::class, $company]);

        $query = $company->customers();

        // Status Filter
        $status = $request->query('status', 'active');
        if ($status === 'archived') {
            $query->onlyTrashed();
        } elseif ($status === 'all') {
            $query->withTrashed();
        } else {
            $query->whereNull('deleted_at')->where('status', CustomerStatus::Active->value);
        }

        // Search Filter
        if ($request->filled('search')) {
            $query->search((string) $request->query('search'));
        }

        // Safe Sorting Whitelist
        $allowedSorts = ['name', 'company_name', 'created_at', 'updated_at'];
        $sort = (string) $request->query('sort', '-created_at');
        $direction = 'asc';

        if (str_starts_with($sort, '-')) {
            $direction = 'desc';
            $sortField = substr($sort, 1);
        } else {
            $sortField = $sort;
        }

        if (in_array($sortField, $allowedSorts, true)) {
            $query->orderBy($sortField, $direction);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // Bounded Pagination
        $perPage = min(max((int) $request->query('per_page', 20), 5), 100);
        $paginator = $query->paginate($perPage);

        return ApiResponse::success(
            $paginator->through(fn (Customer $customer): CustomerResource => new CustomerResource($customer)),
            'Customers retrieved successfully.',
        );
    }

    /**
     * Create a new customer within the company.
     */
    public function store(
        CreateCustomerRequest $request,
        Company $company,
        CreateCustomerAction $action,
    ): JsonResponse {
        $this->authorize('create', [Customer::class, $company]);

        try {
            $customer = $action->execute($company, $request->validated());

            return ApiResponse::success(
                new CustomerResource($customer),
                'Customer created successfully.',
                201,
            );
        } catch (AuthorizationException $e) {
            return ApiResponse::error($e->getMessage(), [], 403);
        }
    }

    /**
     * Display the specified customer.
     */
    public function show(Request $request, Company $company, int|string $customerId): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $company->customers()->withTrashed()->where('id', $customerId)->firstOrFail();

        $this->authorize('view', [$customer, $company]);

        return ApiResponse::success(
            new CustomerResource($customer),
            'Customer retrieved successfully.',
        );
    }

    /**
     * Update the specified customer.
     */
    public function update(
        UpdateCustomerRequest $request,
        Company $company,
        int|string $customerId,
        UpdateCustomerAction $action,
    ): JsonResponse {
        /** @var Customer $customer */
        $customer = $company->customers()->withTrashed()->where('id', $customerId)->firstOrFail();

        $this->authorize('update', [$customer, $company]);

        $updatedCustomer = $action->execute($customer, $request->validated());

        return ApiResponse::success(
            new CustomerResource($updatedCustomer),
            'Customer updated successfully.',
        );
    }

    /**
     * Archive the specified customer.
     */
    public function archive(
        Request $request,
        Company $company,
        int|string $customerId,
        ArchiveCustomerAction $action,
    ): JsonResponse {
        /** @var Customer $customer */
        $customer = $company->customers()->where('id', $customerId)->firstOrFail();

        $this->authorize('delete', [$customer, $company]);

        $archivedCustomer = $action->execute($customer);

        return ApiResponse::success(
            new CustomerResource($archivedCustomer),
            'Customer archived successfully.',
        );
    }

    /**
     * Restore an archived customer.
     */
    public function restore(
        Request $request,
        Company $company,
        int|string $customerId,
        RestoreCustomerAction $action,
    ): JsonResponse {
        /** @var Customer $customer */
        $customer = $company->customers()->onlyTrashed()->where('id', $customerId)->firstOrFail();

        $this->authorize('restore', [$customer, $company]);

        $restoredCustomer = $action->execute($customer);

        return ApiResponse::success(
            new CustomerResource($restoredCustomer),
            'Customer restored successfully.',
        );
    }
}
