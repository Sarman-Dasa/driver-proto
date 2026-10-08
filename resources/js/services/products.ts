import apiClient from '@/services/api';
import type {
    ApiResponse,
    Pagination,
    Product,
    CreateProductPayload,
    UpdateProductPayload,
    ProductFilterParams,
} from '@/types';

export const productService = {
    /**
     * Get paginated products and services list for a company.
     */
    async getProducts(
        companyId: number | string,
        params?: ProductFilterParams
    ): Promise<ApiResponse<Pagination<Product>>> {
        const response = await apiClient.get<ApiResponse<Pagination<Product>>>(
            `/companies/${companyId}/products`,
            { params }
        );
        return response.data;
    },

    /**
     * Get product details by ID.
     */
    async getProduct(
        companyId: number | string,
        productId: number | string
    ): Promise<ApiResponse<Product>> {
        const response = await apiClient.get<ApiResponse<Product>>(
            `/companies/${companyId}/products/${productId}`
        );
        return response.data;
    },

    /**
     * Create a new product or service.
     */
    async createProduct(
        companyId: number | string,
        payload: CreateProductPayload
    ): Promise<ApiResponse<Product>> {
        const response = await apiClient.post<ApiResponse<Product>>(
            `/companies/${companyId}/products`,
            payload
        );
        return response.data;
    },

    /**
     * Update an existing product or service.
     */
    async updateProduct(
        companyId: number | string,
        productId: number | string,
        payload: UpdateProductPayload
    ): Promise<ApiResponse<Product>> {
        const response = await apiClient.put<ApiResponse<Product>>(
            `/companies/${companyId}/products/${productId}`,
            payload
        );
        return response.data;
    },

    /**
     * Update product status (active/inactive).
     */
    async updateStatus(
        companyId: number | string,
        productId: number | string,
        status: 'active' | 'inactive'
    ): Promise<ApiResponse<Product>> {
        const response = await apiClient.patch<ApiResponse<Product>>(
            `/companies/${companyId}/products/${productId}/status`,
            { status }
        );
        return response.data;
    },

    /**
     * Soft delete a product.
     */
    async deleteProduct(
        companyId: number | string,
        productId: number | string
    ): Promise<ApiResponse<Product>> {
        const response = await apiClient.delete<ApiResponse<Product>>(
            `/companies/${companyId}/products/${productId}`
        );
        return response.data;
    },

    /**
     * Restore a soft-deleted product.
     */
    async restoreProduct(
        companyId: number | string,
        productId: number | string
    ): Promise<ApiResponse<Product>> {
        const response = await apiClient.post<ApiResponse<Product>>(
            `/companies/${companyId}/products/${productId}/restore`
        );
        return response.data;
    },
};
