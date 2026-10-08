import { defineStore } from 'pinia';
import { ref } from 'vue';
import { customerService } from '@/services/customers';
import type {
    Customer,
    CreateCustomerPayload,
    UpdateCustomerPayload,
    CustomerFilterParams,
    Pagination,
} from '@/types';

export const useCustomerStore = defineStore('customers', () => {
    const customers = ref<Customer[]>([]);
    const currentCustomer = ref<Customer | null>(null);
    const pagination = ref<Pagination<Customer> | null>(null);
    const loading = ref(false);
    const error = ref<string | null>(null);

    const filters = ref<CustomerFilterParams>({
        status: 'active',
        search: '',
        sort: '-created_at',
        page: 1,
        per_page: 20,
    });

    /**
     * Clear customer state (e.g. on workspace switch).
     */
    const clear = () => {
        customers.value = [];
        currentCustomer.value = null;
        pagination.value = null;
        error.value = null;
        filters.value = {
            status: 'active',
            search: '',
            sort: '-created_at',
            page: 1,
            per_page: 20,
        };
    };

    /**
     * Fetch paginated customers for a company.
     */
    const fetchCustomers = async (companyId?: number | string): Promise<Pagination<Customer> | null> => {
        const targetCompanyId = companyId || localStorage.getItem('billflow_current_company_id');
        if (!targetCompanyId) {
            clear();
            return null;
        }

        loading.value = true;
        error.value = null;
        try {
            const response = await customerService.getCustomers(targetCompanyId, filters.value);
            const paginatedData = response.data;
            pagination.value = paginatedData;
            customers.value = paginatedData.data;
            return paginatedData;
        } catch (err: unknown) {
            const apiErr = err as { response?: { data?: { message?: string } } };
            error.value = apiErr.response?.data?.message || 'Failed to fetch customers.';
            return null;
        } finally {
            loading.value = false;
        }
    };

    /**
     * Fetch single customer details.
     */
    const fetchCustomer = async (
        customerId: number | string,
        companyId?: number | string
    ): Promise<Customer | null> => {
        const targetCompanyId = companyId || localStorage.getItem('billflow_current_company_id');
        if (!targetCompanyId) {
            return null;
        }

        loading.value = true;
        error.value = null;
        try {
            const response = await customerService.getCustomer(targetCompanyId, customerId);
            currentCustomer.value = response.data;
            return response.data;
        } catch (err: unknown) {
            const apiErr = err as { response?: { data?: { message?: string } } };
            error.value = apiErr.response?.data?.message || 'Failed to fetch customer.';
            return null;
        } finally {
            loading.value = false;
        }
    };

    /**
     * Create a new customer.
     */
    const createCustomer = async (
        payload: CreateCustomerPayload,
        companyId?: number | string
    ): Promise<Customer> => {
        const targetCompanyId = companyId || localStorage.getItem('billflow_current_company_id');
        if (!targetCompanyId) {
            throw new Error('No active company workspace.');
        }

        loading.value = true;
        error.value = null;
        try {
            const response = await customerService.createCustomer(targetCompanyId, payload);
            const created = response.data;
            customers.value.unshift(created);
            return created;
        } catch (err: unknown) {
            const apiErr = err as { response?: { data?: { message?: string } } };
            error.value = apiErr.response?.data?.message || 'Failed to create customer.';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    /**
     * Update an existing customer.
     */
    const updateCustomer = async (
        customerId: number | string,
        payload: UpdateCustomerPayload,
        companyId?: number | string
    ): Promise<Customer> => {
        const targetCompanyId = companyId || localStorage.getItem('billflow_current_company_id');
        if (!targetCompanyId) {
            throw new Error('No active company workspace.');
        }

        loading.value = true;
        error.value = null;
        try {
            const response = await customerService.updateCustomer(
                targetCompanyId,
                customerId,
                payload
            );
            const updated = response.data;
            const index = customers.value.findIndex((c) => c.id === updated.id);
            if (index !== -1) {
                customers.value[index] = updated;
            }
            if (currentCustomer.value?.id === updated.id) {
                currentCustomer.value = updated;
            }
            return updated;
        } catch (err: unknown) {
            const apiErr = err as { response?: { data?: { message?: string } } };
            error.value = apiErr.response?.data?.message || 'Failed to update customer.';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    /**
     * Archive a customer.
     */
    const archiveCustomer = async (
        customerId: number | string,
        companyId?: number | string
    ): Promise<Customer> => {
        const targetCompanyId = companyId || localStorage.getItem('billflow_current_company_id');
        if (!targetCompanyId) {
            throw new Error('No active company workspace.');
        }

        loading.value = true;
        error.value = null;
        try {
            const response = await customerService.archiveCustomer(targetCompanyId, customerId);
            const archived = response.data;
            if (filters.value.status === 'active') {
                customers.value = customers.value.filter((c) => c.id !== archived.id);
            } else {
                const index = customers.value.findIndex((c) => c.id === archived.id);
                if (index !== -1) {
                    customers.value[index] = archived;
                }
            }
            if (currentCustomer.value?.id === archived.id) {
                currentCustomer.value = archived;
            }
            return archived;
        } catch (err: unknown) {
            const apiErr = err as { response?: { data?: { message?: string } } };
            error.value = apiErr.response?.data?.message || 'Failed to archive customer.';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    /**
     * Restore an archived customer.
     */
    const restoreCustomer = async (
        customerId: number | string,
        companyId?: number | string
    ): Promise<Customer> => {
        const targetCompanyId = companyId || localStorage.getItem('billflow_current_company_id');
        if (!targetCompanyId) {
            throw new Error('No active company workspace.');
        }

        loading.value = true;
        error.value = null;
        try {
            const response = await customerService.restoreCustomer(targetCompanyId, customerId);
            const restored = response.data;
            if (filters.value.status === 'archived') {
                customers.value = customers.value.filter((c) => c.id !== restored.id);
            } else {
                const index = customers.value.findIndex((c) => c.id === restored.id);
                if (index !== -1) {
                    customers.value[index] = restored;
                }
            }
            if (currentCustomer.value?.id === restored.id) {
                currentCustomer.value = restored;
            }
            return restored;
        } catch (err: unknown) {
            const apiErr = err as { response?: { data?: { message?: string } } };
            error.value = apiErr.response?.data?.message || 'Failed to restore customer.';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    return {
        customers,
        currentCustomer,
        pagination,
        loading,
        error,
        filters,
        clear,
        fetchCustomers,
        fetchCustomer,
        createCustomer,
        updateCustomer,
        archiveCustomer,
        restoreCustomer,
    };
});
