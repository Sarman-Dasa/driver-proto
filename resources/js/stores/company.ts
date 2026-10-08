import { defineStore } from 'pinia';
import { ref } from 'vue';
import apiClient from '@/services/api';
import type { Company, CreateCompanyPayload, ApiResponse } from '@/types';

export const useCompanyStore = defineStore('company', () => {
    const companies = ref<Company[]>([]);
    const currentCompany = ref<Company | null>(null);
    const loading = ref(false);

    const setCurrentCompany = (company: Company | null) => {
        currentCompany.value = company;
        if (company) {
            localStorage.setItem('billflow_current_company_id', String(company.id));
        } else {
            localStorage.removeItem('billflow_current_company_id');
        }
    };

    const fetchCompanies = async (): Promise<Company[]> => {
        loading.value = true;
        try {
            const response = await apiClient.get<ApiResponse<Company[]>>('/companies');
            companies.value = response.data.data;

            // Auto-select current company if none selected or if previously saved
            const savedCompanyId = localStorage.getItem('billflow_current_company_id');
            if (savedCompanyId) {
                const found = companies.value.find((c) => String(c.id) === savedCompanyId);
                if (found && found.is_active) {
                    currentCompany.value = found;
                } else if (companies.value.length > 0) {
                    const firstActive = companies.value.find((c) => c.is_active) || companies.value[0];
                    setCurrentCompany(firstActive);
                }
            } else if (companies.value.length > 0) {
                const firstActive = companies.value.find((c) => c.is_active) || companies.value[0];
                setCurrentCompany(firstActive);
            }

            return companies.value;
        } finally {
            loading.value = false;
        }
    };

    const switchCompany = async (companyId: number): Promise<Company> => {
        loading.value = true;
        try {
            const response = await apiClient.post<ApiResponse<Company>>('/companies/switch', {
                company_id: companyId,
            });
            const updated = response.data.data;
            setCurrentCompany(updated);
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
            return created;
        } finally {
            loading.value = false;
        }
    };

    return {
        companies,
        currentCompany,
        loading,
        setCurrentCompany,
        fetchCompanies,
        switchCompany,
        createCompany,
    };
});
