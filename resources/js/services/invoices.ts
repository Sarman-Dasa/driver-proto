import apiClient from '@/services/api';
import type {
    ApiResponse,
    Pagination,
    Invoice,
    CreateInvoicePayload,
    UpdateInvoicePayload,
    InvoiceFilterParams,
} from '@/types';

export const invoiceService = {
    /**
     * Get paginated invoices list for a company.
     */
    async getInvoices(
        companyId: number | string,
        params?: InvoiceFilterParams
    ): Promise<ApiResponse<Pagination<Invoice>>> {
        const response = await apiClient.get<ApiResponse<Pagination<Invoice>>>(
            `/companies/${companyId}/invoices`,
            { params }
        );
        return response.data;
    },

    /**
     * Get invoice details by ID.
     */
    async getInvoice(
        companyId: number | string,
        invoiceId: number | string
    ): Promise<ApiResponse<Invoice>> {
        const response = await apiClient.get<ApiResponse<Invoice>>(
            `/companies/${companyId}/invoices/${invoiceId}`
        );
        return response.data;
    },

    /**
     * Create a new draft invoice.
     */
    async createInvoice(
        companyId: number | string,
        payload: CreateInvoicePayload
    ): Promise<ApiResponse<Invoice>> {
        const response = await apiClient.post<ApiResponse<Invoice>>(
            `/companies/${companyId}/invoices`,
            payload
        );
        return response.data;
    },

    /**
     * Update an existing draft invoice.
     */
    async updateInvoice(
        companyId: number | string,
        invoiceId: number | string,
        payload: UpdateInvoicePayload
    ): Promise<ApiResponse<Invoice>> {
        const response = await apiClient.put<ApiResponse<Invoice>>(
            `/companies/${companyId}/invoices/${invoiceId}`,
            payload
        );
        return response.data;
    },

    /**
     * Delete (soft-delete) a draft invoice.
     */
    async deleteInvoice(
        companyId: number | string,
        invoiceId: number | string
    ): Promise<ApiResponse<null>> {
        const response = await apiClient.delete<ApiResponse<null>>(
            `/companies/${companyId}/invoices/${invoiceId}`
        );
        return response.data;
    },

    /**
     * Restore a soft-deleted invoice.
     */
    async restoreInvoice(
        companyId: number | string,
        invoiceId: number | string
    ): Promise<ApiResponse<Invoice>> {
        const response = await apiClient.post<ApiResponse<Invoice>>(
            `/companies/${companyId}/invoices/${invoiceId}/restore`
        );
        return response.data;
    },

    /**
     * Send an invoice to transition from draft to sent.
     */
    async sendInvoice(
        companyId: number | string,
        invoiceId: number | string
    ): Promise<ApiResponse<Invoice>> {
        const response = await apiClient.post<ApiResponse<Invoice>>(
            `/companies/${companyId}/invoices/${invoiceId}/send`
        );
        return response.data;
    },

    /**
     * Cancel an invoice.
     */
    async cancelInvoice(
        companyId: number | string,
        invoiceId: number | string
    ): Promise<ApiResponse<Invoice>> {
        const response = await apiClient.post<ApiResponse<Invoice>>(
            `/companies/${companyId}/invoices/${invoiceId}/cancel`
        );
        return response.data;
    },
};
