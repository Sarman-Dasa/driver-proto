import { defineStore } from 'pinia';
import { ref } from 'vue';
import { invoiceService } from '@/services/invoices';
import type {
    Invoice,
    CreateInvoicePayload,
    UpdateInvoicePayload,
    InvoiceFilterParams,
    Pagination,
} from '@/types';

export const useInvoiceStore = defineStore('invoices', () => {
    const invoices = ref<Invoice[]>([]);
    const currentInvoice = ref<Invoice | null>(null);
    const pagination = ref<Pagination<Invoice> | null>(null);
    const loading = ref(false);
    const error = ref<string | null>(null);

    const filters = ref<InvoiceFilterParams>({
        status: 'all',
        customer_id: null,
        search: '',
        from_date: null,
        to_date: null,
        sort: '-created_at',
        page: 1,
        per_page: 20,
    });

    /**
     * Clear invoice state (e.g. on workspace switch).
     */
    const clear = () => {
        invoices.value = [];
        currentInvoice.value = null;
        pagination.value = null;
        error.value = null;
        filters.value = {
            status: 'all',
            customer_id: null,
            search: '',
            from_date: null,
            to_date: null,
            sort: '-created_at',
            page: 1,
            per_page: 20,
        };
    };

    /**
     * Fetch paginated invoices for a company.
     */
    const fetchInvoices = async (companyId?: number | string): Promise<Pagination<Invoice> | null> => {
        const targetCompanyId = companyId || localStorage.getItem('billflow_current_company_id');
        if (!targetCompanyId) {
            clear();
            return null;
        }

        loading.value = true;
        error.value = null;
        try {
            const response = await invoiceService.getInvoices(targetCompanyId, filters.value);
            const paginatedData = response.data;
            pagination.value = paginatedData;
            invoices.value = paginatedData.data;
            return paginatedData;
        } catch (err: unknown) {
            const apiErr = err as { response?: { data?: { message?: string } } };
            error.value = apiErr.response?.data?.message || 'Failed to fetch invoices.';
            return null;
        } finally {
            loading.value = false;
        }
    };

    /**
     * Fetch single invoice details.
     */
    const fetchInvoice = async (
        invoiceId: number | string,
        companyId?: number | string
    ): Promise<Invoice | null> => {
        const targetCompanyId = companyId || localStorage.getItem('billflow_current_company_id');
        if (!targetCompanyId) {
            return null;
        }

        loading.value = true;
        error.value = null;
        try {
            const response = await invoiceService.getInvoice(targetCompanyId, invoiceId);
            currentInvoice.value = response.data;
            return response.data;
        } catch (err: unknown) {
            const apiErr = err as { response?: { data?: { message?: string } } };
            error.value = apiErr.response?.data?.message || 'Failed to fetch invoice.';
            return null;
        } finally {
            loading.value = false;
        }
    };

    /**
     * Create a new draft invoice.
     */
    const createInvoice = async (
        payload: CreateInvoicePayload,
        companyId?: number | string
    ): Promise<Invoice> => {
        const targetCompanyId = companyId || localStorage.getItem('billflow_current_company_id');
        if (!targetCompanyId) {
            throw new Error('No active company workspace.');
        }

        loading.value = true;
        error.value = null;
        try {
            const response = await invoiceService.createInvoice(targetCompanyId, payload);
            const created = response.data;
            invoices.value.unshift(created);
            return created;
        } catch (err: unknown) {
            const apiErr = err as { response?: { data?: { message?: string } } };
            error.value = apiErr.response?.data?.message || 'Failed to create invoice.';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    /**
     * Update an existing draft invoice.
     */
    const updateInvoice = async (
        invoiceId: number | string,
        payload: UpdateInvoicePayload,
        companyId?: number | string
    ): Promise<Invoice> => {
        const targetCompanyId = companyId || localStorage.getItem('billflow_current_company_id');
        if (!targetCompanyId) {
            throw new Error('No active company workspace.');
        }

        loading.value = true;
        error.value = null;
        try {
            const response = await invoiceService.updateInvoice(
                targetCompanyId,
                invoiceId,
                payload
            );
            const updated = response.data;
            const index = invoices.value.findIndex((inv) => inv.id === updated.id);
            if (index !== -1) {
                invoices.value[index] = updated;
            }
            if (currentInvoice.value?.id === updated.id) {
                currentInvoice.value = updated;
            }
            return updated;
        } catch (err: unknown) {
            const apiErr = err as { response?: { data?: { message?: string } } };
            error.value = apiErr.response?.data?.message || 'Failed to update invoice.';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    /**
     * Delete (soft delete) a draft invoice.
     */
    const deleteInvoice = async (
        invoiceId: number | string,
        companyId?: number | string
    ): Promise<void> => {
        const targetCompanyId = companyId || localStorage.getItem('billflow_current_company_id');
        if (!targetCompanyId) {
            throw new Error('No active company workspace.');
        }

        loading.value = true;
        error.value = null;
        try {
            await invoiceService.deleteInvoice(targetCompanyId, invoiceId);
            invoices.value = invoices.value.filter((inv) => String(inv.id) !== String(invoiceId));
            if (currentInvoice.value && String(currentInvoice.value.id) === String(invoiceId)) {
                currentInvoice.value = null;
            }
        } catch (err: unknown) {
            const apiErr = err as { response?: { data?: { message?: string } } };
            error.value = apiErr.response?.data?.message || 'Failed to delete invoice.';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    /**
     * Restore a soft-deleted invoice.
     */
    const restoreInvoice = async (
        invoiceId: number | string,
        companyId?: number | string
    ): Promise<Invoice> => {
        const targetCompanyId = companyId || localStorage.getItem('billflow_current_company_id');
        if (!targetCompanyId) {
            throw new Error('No active company workspace.');
        }

        loading.value = true;
        error.value = null;
        try {
            const response = await invoiceService.restoreInvoice(targetCompanyId, invoiceId);
            const restored = response.data;
            invoices.value.unshift(restored);
            return restored;
        } catch (err: unknown) {
            const apiErr = err as { response?: { data?: { message?: string } } };
            error.value = apiErr.response?.data?.message || 'Failed to restore invoice.';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    /**
     * Send a draft invoice.
     */
    const sendInvoice = async (
        invoiceId: number | string,
        companyId?: number | string
    ): Promise<Invoice> => {
        const targetCompanyId = companyId || localStorage.getItem('billflow_current_company_id');
        if (!targetCompanyId) {
            throw new Error('No active company workspace.');
        }

        loading.value = true;
        error.value = null;
        try {
            const response = await invoiceService.sendInvoice(targetCompanyId, invoiceId);
            const sent = response.data;
            const index = invoices.value.findIndex((inv) => inv.id === sent.id);
            if (index !== -1) {
                invoices.value[index] = sent;
            }
            if (currentInvoice.value?.id === sent.id) {
                currentInvoice.value = sent;
            }
            return sent;
        } catch (err: unknown) {
            const apiErr = err as { response?: { data?: { message?: string } } };
            error.value = apiErr.response?.data?.message || 'Failed to send invoice.';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    /**
     * Cancel an invoice.
     */
    const cancelInvoice = async (
        invoiceId: number | string,
        companyId?: number | string
    ): Promise<Invoice> => {
        const targetCompanyId = companyId || localStorage.getItem('billflow_current_company_id');
        if (!targetCompanyId) {
            throw new Error('No active company workspace.');
        }

        loading.value = true;
        error.value = null;
        try {
            const response = await invoiceService.cancelInvoice(targetCompanyId, invoiceId);
            const cancelled = response.data;
            const index = invoices.value.findIndex((inv) => inv.id === cancelled.id);
            if (index !== -1) {
                invoices.value[index] = cancelled;
            }
            if (currentInvoice.value?.id === cancelled.id) {
                currentInvoice.value = cancelled;
            }
            return cancelled;
        } catch (err: unknown) {
            const apiErr = err as { response?: { data?: { message?: string } } };
            error.value = apiErr.response?.data?.message || 'Failed to cancel invoice.';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    return {
        invoices,
        currentInvoice,
        pagination,
        loading,
        error,
        filters,
        clear,
        fetchInvoices,
        fetchInvoice,
        createInvoice,
        updateInvoice,
        deleteInvoice,
        restoreInvoice,
        sendInvoice,
        cancelInvoice,
    };
});
