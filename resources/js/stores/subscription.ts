import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import apiClient from '@/services/api';
import subscriptionService, { type SubscriptionSummary } from '@/services/subscription';
import type {
    ApiResponse,
    Plan,
    Subscription,
    CompanyFeatureStatus,
    CompanyPlanResponse,
    BillingHistory,
    BillingHistoryPagination,
} from '@/types';

export const useSubscriptionStore = defineStore('subscription', () => {
    const currentPlan = ref<Plan | null>(null);
    const subscription = ref<Subscription | null>(null);
    const features = ref<Record<string, CompanyFeatureStatus>>({});
    const availablePlans = ref<Plan[]>([]);
    const hasStripeCustomer = ref(false);
    const billingHistories = ref<BillingHistory[]>([]);
    const billingPagination = ref<BillingHistoryPagination>({
        current_page: 1,
        per_page: 15,
        total: 0,
        last_page: 1,
    });
    const loading = ref(false);

    const isSubscribed = computed(() => subscription.value?.is_active ?? false);
    const isCancelled = computed(() => subscription.value?.cancel_at_period_end ?? false);
    const isPro = computed(() => currentPlan.value?.slug === 'pro');
    const isBusiness = computed(() => currentPlan.value?.slug === 'business');

    /**
     * Clear subscription and feature state (e.g. on workspace switch or logout).
     */
    const clear = () => {
        currentPlan.value = null;
        subscription.value = null;
        features.value = {};
        hasStripeCustomer.value = false;
        billingHistories.value = [];
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
     * Fetch subscription status from billing endpoint.
     */
    const fetchSubscription = async (companyId?: number | string): Promise<SubscriptionSummary | null> => {
        const targetId = Number(companyId || localStorage.getItem('billflow_current_company_id'));
        if (!targetId) {
            return null;
        }

        loading.value = true;
        try {
            const data = await subscriptionService.getSubscription(targetId);
            subscription.value = data.subscription;
            if (data.subscription?.plan) {
                currentPlan.value = data.subscription.plan;
            }
            hasStripeCustomer.value = data.has_stripe_customer;
            return data;
        } finally {
            loading.value = false;
        }
    };

    /**
     * Start Stripe Checkout flow.
     */
    const checkout = async (
        planId: number,
        interval: 'monthly' | 'yearly',
        companyId?: number | string
    ): Promise<string> => {
        const targetId = Number(companyId || localStorage.getItem('billflow_current_company_id'));
        if (!targetId) {
            throw new Error('No active company selected.');
        }

        const data = await subscriptionService.createCheckoutSession(targetId, {
            plan_id: planId,
            interval,
        });

        return data.checkout_url;
    };

    /**
     * Open Stripe Customer Portal.
     */
    const openPortal = async (companyId?: number | string): Promise<string> => {
        const targetId = Number(companyId || localStorage.getItem('billflow_current_company_id'));
        if (!targetId) {
            throw new Error('No active company selected.');
        }

        const data = await subscriptionService.createPortalSession(targetId);
        return data.portal_url;
    };

    /**
     * Cancel current active subscription.
     */
    const cancel = async (immediately = false, companyId?: number | string) => {
        const targetId = Number(companyId || localStorage.getItem('billflow_current_company_id'));
        if (!targetId) {
            throw new Error('No active company selected.');
        }

        const res = await subscriptionService.cancelSubscription(targetId, immediately);
        subscription.value = res.subscription;
        return res;
    };

    /**
     * Resume current cancelled subscription.
     */
    const resume = async (companyId?: number | string) => {
        const targetId = Number(companyId || localStorage.getItem('billflow_current_company_id'));
        if (!targetId) {
            throw new Error('No active company selected.');
        }

        const res = await subscriptionService.resumeSubscription(targetId);
        subscription.value = res.subscription;
        return res;
    };

    /**
     * Fetch billing history records.
     */
    const fetchBillingHistory = async (page = 1, perPage = 15, companyId?: number | string) => {
        const targetId = Number(companyId || localStorage.getItem('billflow_current_company_id'));
        if (!targetId) {
            return;
        }

        const res = await subscriptionService.getBillingHistory(targetId, page, perPage);
        billingHistories.value = res.data;
        billingPagination.value = res.pagination;
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
        hasStripeCustomer,
        billingHistories,
        billingPagination,
        loading,
        isSubscribed,
        isCancelled,
        isPro,
        isBusiness,
        clear,
        fetchPlans,
        fetchPlan,
        fetchSubscription,
        checkout,
        openPortal,
        cancel,
        resume,
        fetchBillingHistory,
        canUse,
        getLimit,
        isUnlimited,
    };
});
