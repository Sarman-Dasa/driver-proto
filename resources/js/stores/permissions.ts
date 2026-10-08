import { defineStore } from 'pinia';
import { ref } from 'vue';
import apiClient from '@/services/api';
import type { Role, CompanyAuthorization, ApiResponse } from '@/types';

export const usePermissionsStore = defineStore('permissions', () => {
    const role = ref<Role | null>(null);
    const permissions = ref<string[]>([]);
    const isOwner = ref<boolean>(false);
    const loading = ref<boolean>(false);

    const can = (permission: string): boolean => {
        if (isOwner.value) {
            return true;
        }
        return permissions.value.includes(permission);
    };

    const hasRole = (roleSlug: string | string[]): boolean => {
        if (!role.value) {
            return false;
        }
        if (Array.isArray(roleSlug)) {
            return roleSlug.includes(role.value.slug);
        }
        return role.value.slug === roleSlug;
    };

    const fetchAuthorization = async (companyId: number): Promise<CompanyAuthorization | null> => {
        loading.value = true;
        try {
            const response = await apiClient.get<ApiResponse<CompanyAuthorization>>(
                `/companies/${companyId}/authorization`
            );
            const authData = response.data.data;
            role.value = authData.role;
            permissions.value = authData.permissions || [];
            isOwner.value = authData.is_owner || false;
            return authData;
        } catch {
            clear();
            return null;
        } finally {
            loading.value = false;
        }
    };

    const clear = () => {
        role.value = null;
        permissions.value = [];
        isOwner.value = false;
    };

    return {
        role,
        permissions,
        isOwner,
        loading,
        can,
        hasRole,
        fetchAuthorization,
        clear,
    };
});
