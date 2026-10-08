import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import apiClient from '@/services/api';
import type {
    ApiResponse,
    Plan,
    Subscription,
    CompanyFeatureStatus,
    CompanyPlanResponse,
} from '@/types';

export const useSubscriptionStore = defineStore('subscription', () => {
    const currentPlan = ref<Plan | null>(null);
    const subscription = ref<Subscription | null>(null);
    const features = ref<Record<string, CompanyFeatureStatus>>({});
    const availablePlans = ref<Plan[]>([]);
    const loading = ref(false);

    const isSubscribed = computed(() => subscription.value?.is_active ?? false);

    /**
     * Clear subscription and feature state (e.g. on workspace switch or logout).
     */
    const clear = () => {
        currentPlan.value = null;
        subscription.value = null;
        features.value = {};
    };

    /**
     * Fetch all available plans for pricing display.
     */
    const fetchPlans = async (): Promise<Plan[]> => {
        loading.value = true;
        try {
            const response = await apiClient.get<ApiResponse<Plan[]>>('/plans');
            availablePlans.value = response.data.data;
            return availablePlans.value;
        } finally {
            loading.value = false;
        }
    };

    /**
     * Fetch current company's plan, subscription, and feature capabilities.
     */
    const fetchPlan = async (companyId?: number | string): Promise<CompanyPlanResponse | null> => {
        const targetId = companyId || localStorage.getItem('billflow_current_company_id');
        if (!targetId) {
            clear();
            return null;
        }

        loading.value = true;
        try {
            const response = await apiClient.get<ApiResponse<CompanyPlanResponse>>(
                `/companies/${targetId}/plan`
            );
            const data = response.data.data;
            subscription.value = data.subscription;
            currentPlan.value = data.plan;
            features.value = data.features || {};
            return data;
        } catch {
            clear();
            return null;
        } finally {
            loading.value = false;
        }
    };

    /**
     * Determine if a feature is enabled and accessible on the current company plan.
     */
    const canUse = (featureKey: string): boolean => {
        const feature = features.value[featureKey];
        if (!feature) {
            return false;
        }
        return feature.enabled;
    };

    /**
     * Get numeric limit for a feature, or null if unlimited.
     */
    const getLimit = (featureKey: string): number | null => {
        const feature = features.value[featureKey];
        if (!feature || !feature.enabled) {
            return 0;
        }
        return feature.limit;
    };

    /**
     * Check if a feature has unlimited usage on the current plan.
     */
    const isUnlimited = (featureKey: string): boolean => {
        const feature = features.value[featureKey];
        if (!feature || !feature.enabled) {
            return false;
        }
        return feature.is_unlimited;
    };

    return {
        currentPlan,
        subscription,
        features,
        availablePlans,
        loading,
        isSubscribed,
        clear,
        fetchPlans,
        fetchPlan,
        canUse,
        getLimit,
        isUnlimited,
    };
});
