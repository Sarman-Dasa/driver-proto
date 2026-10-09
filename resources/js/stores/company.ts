import { defineStore } from 'pinia';
import { ref } from 'vue';
import apiClient from '@/services/api';
import { usePermissionsStore } from '@/stores/permissions';
import { useSubscriptionStore } from '@/stores/subscription';
import { useCustomerStore } from '@/stores/customers';
import { useProductStore } from '@/stores/products';
import { useInvoiceStore } from '@/stores/invoices';
import type { Company, CreateCompanyPayload, UpdateCompanyPayload, CompanyMember, Role, ApiResponse } from '@/types';

export const useCompanyStore = defineStore('company', () => {
    const permissionsStore = usePermissionsStore();
    const subscriptionStore = useSubscriptionStore();
    const customerStore = useCustomerStore();
    const productStore = useProductStore();
    const invoiceStore = useInvoiceStore();
    const companies = ref<Company[]>([]);
    const currentCompany = ref<Company | null>(null);
    const loading = ref(false);

    const setCurrentCompany = (company: Company | null) => {
        currentCompany.value = company;
        if (company && company.is_active) {
            localStorage.setItem('billflow_current_company_id', String(company.id));
            permissionsStore.fetchAuthorization(company.id);
            subscriptionStore.fetchPlan(company.id);
            customerStore.clear();
            productStore.clear();
            invoiceStore.clear();
        } else {
            localStorage.removeItem('billflow_current_company_id');
            permissionsStore.clear();
            subscriptionStore.clear();
            customerStore.clear();
            productStore.clear();
            invoiceStore.clear();
        }
    };

    /**
     * Clear any stale company-scoped state upon workspace switch.
     */
    const clearCompanyScopedData = () => {
        customerStore.clear();
        productStore.clear();
        invoiceStore.clear();

        // Dispatches workspace change event for future company-scoped modules
        window.dispatchEvent(new CustomEvent('billflow:workspace-changed', {
            detail: { companyId: currentCompany.value?.id },
        }));
    };

    const fetchCompanies = async (): Promise<Company[]> => {
        loading.value = true;
        try {
            const response = await apiClient.get<ApiResponse<Company[]>>('/companies');
            companies.value = response.data.data;

            // Revalidate persisted company ID against authorized active companies
            const savedCompanyId = localStorage.getItem('billflow_current_company_id');
            if (savedCompanyId) {
                const found = companies.value.find((c) => String(c.id) === savedCompanyId);
                if (found && found.is_active) {
                    setCurrentCompany(found);
                } else {
                    // Stored company is no longer member or is inactive: recover with first active
                    const firstActive = companies.value.find((c) => c.is_active) || null;
                    setCurrentCompany(firstActive);
                }
            } else {
                const firstActive = companies.value.find((c) => c.is_active) || null;
                setCurrentCompany(firstActive);
            }

            return companies.value;
        } finally {
            loading.value = false;
        }
    };

    const fetchCompany = async (companyId: number): Promise<Company> => {
        loading.value = true;
        try {
            const response = await apiClient.get<ApiResponse<Company>>(`/companies/${companyId}`);
            const fetched = response.data.data;

            const index = companies.value.findIndex((c) => c.id === companyId);
            if (index !== -1) {
                companies.value[index] = fetched;
            } else {
                companies.value.push(fetched);
            }

            if (currentCompany.value?.id === companyId) {
                currentCompany.value = fetched;
            }

            return fetched;
        } finally {
            loading.value = false;
        }
    };

    const switchCompany = async (companyId: number): Promise<Company> => {
        loading.value = true;
        try {
            const response = await apiClient.post<ApiResponse<Company>>(`/companies/${companyId}/switch`);
            const updated = response.data.data;
            setCurrentCompany(updated);
            clearCompanyScopedData();
            return updated;
        } finally {
            loading.value = false;
        }
    };

    const createCompany = async (payload: CreateCompanyPayload): Promise<Company> => {
        loading.value = true;
        try {
            const response = await apiClient.post<ApiResponse<Company>>('/companies', payload);
            const created = response.data.data;
            companies.value.unshift(created);
            setCurrentCompany(created);
            clearCompanyScopedData();
            return created;
        } finally {
            loading.value = false;
        }
    };

    const updateCompany = async (companyId: number, payload: UpdateCompanyPayload): Promise<Company> => {
        loading.value = true;
        try {
            const response = await apiClient.put<ApiResponse<Company>>(`/companies/${companyId}`, payload);
            const updated = response.data.data;

            const index = companies.value.findIndex((c) => c.id === companyId);
            if (index !== -1) {
                companies.value[index] = updated;
            }

            if (currentCompany.value?.id === companyId) {
                currentCompany.value = updated;
            }

            return updated;
        } finally {
            loading.value = false;
        }
    };

    const deactivateCompany = async (companyId: number): Promise<Company> => {
        loading.value = true;
        try {
            const response = await apiClient.post<ApiResponse<Company>>(`/companies/${companyId}/deactivate`);
            const deactivated = response.data.data;

            const index = companies.value.findIndex((c) => c.id === companyId);
            if (index !== -1) {
                companies.value[index] = deactivated;
            }

            // If deactivated workspace was current, recover to another active workspace or clear
            if (currentCompany.value?.id === companyId) {
                const nextActive = companies.value.find((c) => c.id !== companyId && c.is_active) || null;
                setCurrentCompany(nextActive);
                clearCompanyScopedData();
            }

            return deactivated;
        } finally {
            loading.value = false;
        }
    };

    const reactivateCompany = async (companyId: number): Promise<Company> => {
        loading.value = true;
        try {
            const response = await apiClient.post<ApiResponse<Company>>(`/companies/${companyId}/reactivate`);
            const reactivated = response.data.data;

            const index = companies.value.findIndex((c) => c.id === companyId);
            if (index !== -1) {
                companies.value[index] = reactivated;
            }

            if (!currentCompany.value) {
                setCurrentCompany(reactivated);
            }

            return reactivated;
        } finally {
            loading.value = false;
        }
    };

    const fetchMembers = async (companyId: number): Promise<CompanyMember[]> => {
        const response = await apiClient.get<ApiResponse<CompanyMember[]>>(`/companies/${companyId}/members`);
        return response.data.data;
    };

    const fetchRoles = async (companyId: number): Promise<Role[]> => {
        const response = await apiClient.get<ApiResponse<Role[]>>(`/companies/${companyId}/roles`);
        return response.data.data;
    };

    const assignMemberRole = async (companyId: number, memberId: number, roleId: number): Promise<CompanyMember> => {
        const response = await apiClient.post<ApiResponse<CompanyMember>>(
            `/companies/${companyId}/members/${memberId}/role`,
            { role_id: roleId }
        );
        return response.data.data;
    };

    const clearCurrentCompany = () => {
        setCurrentCompany(null);
        clearCompanyScopedData();
    };

    return {
        companies,
        currentCompany,
        loading,
        setCurrentCompany,
        clearCurrentCompany,
        fetchCompanies,
        fetchCompany,
        switchCompany,
        createCompany,
        updateCompany,
        deactivateCompany,
        reactivateCompany,
        fetchMembers,
        fetchRoles,
        assignMemberRole,
    };
});
