<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Product;

use App\Actions\Product\CreateProductAction;
use App\Actions\Product\DeleteProductAction;
use App\Actions\Product\RestoreProductAction;
use App\Actions\Product\UpdateProductAction;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Product\CreateProductRequest;
use App\Http\Requests\Api\V1\Product\UpdateProductRequest;
use App\Http\Resources\Api\V1\ProductResource;
use App\Models\Company;
use App\Models\Product;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * List all products and services for a given company.
     */
    public function index(Request $request, Company $company): JsonResponse
    {
        $this->authorize('viewAny', [Product::class, $company]);

        $query = $company->products();

        // Status Filter
        $status = (string) $request->query('status', 'all_active');
        if ($status === 'active') {
            $query->whereNull('deleted_at')->where('status', ProductStatus::Active->value);
        } elseif ($status === 'inactive') {
            $query->whereNull('deleted_at')->where('status', ProductStatus::Inactive->value);
        } elseif ($status === 'archived' || $status === 'trashed') {
            $query->onlyTrashed();
        } elseif ($status === 'all_with_trashed') {
            $query->withTrashed();
        } else {
            // Default: show both active and inactive non-deleted catalog items
            $query->whereNull('deleted_at');
        }

        // Type Filter (product, service, all)
        if ($request->filled('type') && $request->query('type') !== 'all') {
            $type = (string) $request->query('type');
            if (in_array($type, [ProductType::Product->value, ProductType::Service->value], true)) {
                $query->where('type', $type);
            }
        }

        // Search Filter
        if ($request->filled('search')) {
            $query->search((string) $request->query('search'));
        }

        // Safe Sorting Whitelist
        $allowedSorts = ['name', 'price', 'type', 'status', 'created_at', 'updated_at'];
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
            $paginator->through(fn (Product $product): ProductResource => new ProductResource($product)),
            'Products retrieved successfully.',
        );
    }

    /**
     * Create a new product or service in the company catalog.
     */
    public function store(
        CreateProductRequest $request,
        Company $company,
        CreateProductAction $action,
    ): JsonResponse {
        $this->authorize('create', [Product::class, $company]);

        try {
            $product = $action->execute($company, $request->validated());

            return ApiResponse::success(
                new ProductResource($product),
                'Product created successfully.',
                201,
            );
        } catch (AuthorizationException $e) {
            return ApiResponse::error($e->getMessage(), [], 403);
        }
    }

    /**
     * View a specific product or service.
     */
    public function show(Company $company, Product $product): JsonResponse
    {
        // Enforce company boundary
        if ((int) $product->company_id !== (int) $company->id) {
            return ApiResponse::error('Product not found.', [], 404);
        }

        $this->authorize('view', [$product, $company]);

        return ApiResponse::success(
            new ProductResource($product),
            'Product retrieved successfully.',
        );
    }

    /**
     * Update an existing product or service.
     */
    public function update(
        UpdateProductRequest $request,
        Company $company,
        Product $product,
        UpdateProductAction $action,
    ): JsonResponse {
        // Enforce company boundary
        if ((int) $product->company_id !== (int) $company->id) {
            return ApiResponse::error('Product not found.', [], 404);
        }

        $this->authorize('update', [$product, $company]);

        $updated = $action->execute($product, $request->validated());

        return ApiResponse::success(
            new ProductResource($updated),
            'Product updated successfully.',
        );
    }

    /**
     * Soft delete a product or service from the catalog.
     */
    public function destroy(
        Company $company,
        Product $product,
        DeleteProductAction $action,
    ): JsonResponse {
        // Enforce company boundary
        if ((int) $product->company_id !== (int) $company->id) {
            return ApiResponse::error('Product not found.', [], 404);
        }

        $this->authorize('delete', [$product, $company]);

        $action->execute($product);

        return ApiResponse::success(
            new ProductResource($product),
            'Product deleted successfully.',
        );
    }

    /**
     * Restore a soft-deleted product or service.
     */
    public function restore(
        Company $company,
        int|string $productId,
        RestoreProductAction $action,
    ): JsonResponse {
        $product = Product::withTrashed()
            ->where('company_id', $company->id)
            ->where('id', $productId)
            ->first();

        if (! $product) {
            return ApiResponse::error('Product not found.', [], 404);
        }

        $this->authorize('restore', [$product, $company]);

        $action->execute($product);

        return ApiResponse::success(
            new ProductResource($product),
            'Product restored successfully.',
        );
    }

    /**
     * Toggle or update the active/inactive status of a product or service.
     */
    public function updateStatus(
        Request $request,
        Company $company,
        Product $product,
    ): JsonResponse {
        if ((int) $product->company_id !== (int) $company->id) {
            return ApiResponse::error('Product not found.', [], 404);
        }

        $this->authorize('update', [$product, $company]);

        $request->validate([
            'status' => ['required', 'string', 'in:active,inactive'],
        ]);

        $product->update([
            'status' => $request->input('status'),
        ]);

        return ApiResponse::success(
            new ProductResource($product->fresh()),
            'Product status updated successfully.',
        );
    }
}
