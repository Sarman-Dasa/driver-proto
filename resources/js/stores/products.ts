import { defineStore } from 'pinia';
import { ref } from 'vue';
import { productService } from '@/services/products';
import type {
    Product,
    CreateProductPayload,
    UpdateProductPayload,
    ProductFilterParams,
    Pagination,
} from '@/types';

export const useProductStore = defineStore('products', () => {
    const products = ref<Product[]>([]);
    const currentProduct = ref<Product | null>(null);
    const pagination = ref<Pagination<Product> | null>(null);
    const loading = ref(false);
    const error = ref<string | null>(null);

    const filters = ref<ProductFilterParams>({
        status: 'all',
        type: 'all',
        search: '',
        sort: '-created_at',
        page: 1,
        per_page: 20,
    });

    /**
     * Clear product state (e.g. on workspace switch).
     */
    const clear = () => {
        products.value = [];
        currentProduct.value = null;
        pagination.value = null;
        error.value = null;
        filters.value = {
            status: 'all',
            type: 'all',
            search: '',
            sort: '-created_at',
            page: 1,
            per_page: 20,
        };
    };

    /**
     * Fetch paginated products and services for a company.
     */
    const fetchProducts = async (companyId?: number | string): Promise<Pagination<Product> | null> => {
        const targetCompanyId = companyId || localStorage.getItem('billflow_current_company_id');
        if (!targetCompanyId) {
            clear();
            return null;
        }

        loading.value = true;
        error.value = null;
        try {
            const response = await productService.getProducts(targetCompanyId, filters.value);
            const paginatedData = response.data;
            pagination.value = paginatedData;
            products.value = paginatedData.data;
            return paginatedData;
        } catch (err: unknown) {
            const apiErr = err as { response?: { data?: { message?: string } } };
            error.value = apiErr.response?.data?.message || 'Failed to fetch products.';
            return null;
        } finally {
            loading.value = false;
        }
    };

    /**
     * Fetch single product details.
     */
    const fetchProduct = async (
        productId: number | string,
        companyId?: number | string
    ): Promise<Product | null> => {
        const targetCompanyId = companyId || localStorage.getItem('billflow_current_company_id');
        if (!targetCompanyId) {
            return null;
        }

        loading.value = true;
        error.value = null;
        try {
            const response = await productService.getProduct(targetCompanyId, productId);
            currentProduct.value = response.data;
            return response.data;
        } catch (err: unknown) {
            const apiErr = err as { response?: { data?: { message?: string } } };
            error.value = apiErr.response?.data?.message || 'Failed to fetch product.';
            return null;
        } finally {
            loading.value = false;
        }
    };

    /**
     * Create a new product or service.
     */
    const createProduct = async (
        payload: CreateProductPayload,
        companyId?: number | string
    ): Promise<Product> => {
        const targetCompanyId = companyId || localStorage.getItem('billflow_current_company_id');
        if (!targetCompanyId) {
            throw new Error('No active company workspace.');
        }

        loading.value = true;
        error.value = null;
        try {
            const response = await productService.createProduct(targetCompanyId, payload);
            const created = response.data;
            products.value.unshift(created);
            return created;
        } catch (err: unknown) {
            const apiErr = err as { response?: { data?: { message?: string } } };
            error.value = apiErr.response?.data?.message || 'Failed to create product.';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    /**
     * Update an existing product or service.
     */
    const updateProduct = async (
        productId: number | string,
        payload: UpdateProductPayload,
        companyId?: number | string
    ): Promise<Product> => {
        const targetCompanyId = companyId || localStorage.getItem('billflow_current_company_id');
        if (!targetCompanyId) {
            throw new Error('No active company workspace.');
        }

        loading.value = true;
        error.value = null;
        try {
            const response = await productService.updateProduct(
                targetCompanyId,
                productId,
                payload
            );
            const updated = response.data;
            const index = products.value.findIndex((p) => p.id === updated.id);
            if (index !== -1) {
                products.value[index] = updated;
            }
            if (currentProduct.value?.id === updated.id) {
                currentProduct.value = updated;
            }
            return updated;
        } catch (err: unknown) {
            const apiErr = err as { response?: { data?: { message?: string } } };
            error.value = apiErr.response?.data?.message || 'Failed to update product.';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    /**
     * Update product status (active/inactive).
     */
    const updateStatus = async (
        productId: number | string,
        status: 'active' | 'inactive',
        companyId?: number | string
    ): Promise<Product> => {
        const targetCompanyId = companyId || localStorage.getItem('billflow_current_company_id');
        if (!targetCompanyId) {
            throw new Error('No active company workspace.');
        }

        loading.value = true;
        error.value = null;
        try {
            const response = await productService.updateStatus(
                targetCompanyId,
                productId,
                status
            );
            const updated = response.data;
            const index = products.value.findIndex((p) => p.id === updated.id);
            if (index !== -1) {
                products.value[index] = updated;
            }
            if (currentProduct.value?.id === updated.id) {
                currentProduct.value = updated;
            }
            return updated;
        } catch (err: unknown) {
            const apiErr = err as { response?: { data?: { message?: string } } };
            error.value = apiErr.response?.data?.message || 'Failed to update product status.';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    /**
     * Soft delete a product.
     */
    const deleteProduct = async (
        productId: number | string,
        companyId?: number | string
    ): Promise<Product> => {
        const targetCompanyId = companyId || localStorage.getItem('billflow_current_company_id');
        if (!targetCompanyId) {
            throw new Error('No active company workspace.');
        }

        loading.value = true;
        error.value = null;
        try {
            const response = await productService.deleteProduct(targetCompanyId, productId);
            const deleted = response.data;
            products.value = products.value.filter((p) => p.id !== deleted.id);
            if (currentProduct.value?.id === deleted.id) {
                currentProduct.value = null;
            }
            return deleted;
        } catch (err: unknown) {
            const apiErr = err as { response?: { data?: { message?: string } } };
            error.value = apiErr.response?.data?.message || 'Failed to delete product.';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    /**
     * Restore a soft-deleted product.
     */
    const restoreProduct = async (
        productId: number | string,
        companyId?: number | string
    ): Promise<Product> => {
        const targetCompanyId = companyId || localStorage.getItem('billflow_current_company_id');
        if (!targetCompanyId) {
            throw new Error('No active company workspace.');
        }

        loading.value = true;
        error.value = null;
        try {
            const response = await productService.restoreProduct(targetCompanyId, productId);
            const restored = response.data;
            const index = products.value.findIndex((p) => p.id === restored.id);
            if (index !== -1) {
                products.value[index] = restored;
            } else {
                products.value.unshift(restored);
            }
            if (currentProduct.value?.id === restored.id) {
                currentProduct.value = restored;
            }
            return restored;
        } catch (err: unknown) {
            const apiErr = err as { response?: { data?: { message?: string } } };
            error.value = apiErr.response?.data?.message || 'Failed to restore product.';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    return {
        products,
        currentProduct,
        pagination,
        loading,
        error,
        filters,
        clear,
        fetchProducts,
        fetchProduct,
        createProduct,
        updateProduct,
        updateStatus,
        deleteProduct,
        restoreProduct,
    };
});
