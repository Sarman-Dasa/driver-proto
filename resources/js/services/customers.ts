import apiClient from '@/services/api';
import type {
    ApiResponse,
    Pagination,
    Customer,
    CreateCustomerPayload,
    UpdateCustomerPayload,
    CustomerFilterParams,
} from '@/types';

export const customerService = {
    /**
     * Get paginated customer list for a company.
     */
    async getCustomers(
        companyId: number | string,
        params?: CustomerFilterParams
    ): Promise<ApiResponse<Pagination<Customer>>> {
        const response = await apiClient.get<ApiResponse<Pagination<Customer>>>(
            `/companies/${companyId}/customers`,
            { params }
        );
        return response.data;
    },

    /**
     * Get customer details by ID.
     */
    async getCustomer(
        companyId: number | string,
        customerId: number | string
    ): Promise<ApiResponse<Customer>> {
        const response = await apiClient.get<ApiResponse<Customer>>(
            `/companies/${companyId}/customers/${customerId}`
        );
        return response.data;
    },

    /**
     * Create a new customer.
     */
    async createCustomer(
        companyId: number | string,
        payload: CreateCustomerPayload
    ): Promise<ApiResponse<Customer>> {
        const response = await apiClient.post<ApiResponse<Customer>>(
            `/companies/${companyId}/customers`,
            payload
        );
        return response.data;
    },

    /**
     * Update an existing customer.
     */
    async updateCustomer(
        companyId: number | string,
        customerId: number | string,
        payload: UpdateCustomerPayload
    ): Promise<ApiResponse<Customer>> {
        const response = await apiClient.put<ApiResponse<Customer>>(
            `/companies/${companyId}/customers/${customerId}`,
            payload
        );
        return response.data;
    },

    /**
     * Archive a customer.
     */
    async archiveCustomer(
        companyId: number | string,
        customerId: number | string
    ): Promise<ApiResponse<Customer>> {
        const response = await apiClient.post<ApiResponse<Customer>>(
            `/companies/${companyId}/customers/${customerId}/archive`
        );
        return response.data;
    },

    /**
     * Restore an archived customer.
     */
    async restoreCustomer(
        companyId: number | string,
        customerId: number | string
    ): Promise<ApiResponse<Customer>> {
        const response = await apiClient.post<ApiResponse<Customer>>(
            `/companies/${companyId}/customers/${customerId}/restore`
        );
        return response.data;
    },
};
