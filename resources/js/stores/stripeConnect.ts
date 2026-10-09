import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { stripeConnectService } from '@/services/stripeConnect';
import type { StripeConnectAccount, StripeConnectSummary, StripeConnectStatus } from '@/types';

export const useStripeConnectStore = defineStore('stripeConnect', () => {
    const summary = ref<StripeConnectSummary | null>(null);
    const account = ref<StripeConnectAccount | null>(null);
    const isReadyForPayments = ref(false);
    const featureEnabled = ref(true);
    const canManage = ref(false);
    const loading = ref(false);
    const onboardingLoading = ref(false);
    const error = ref<string | null>(null);

    const isConnected = computed(() => summary.value?.is_connected ?? false);
    const status = computed<StripeConnectStatus>(() => summary.value?.status ?? 'not_connected');
    const chargesEnabled = computed(() => summary.value?.charges_enabled ?? false);
    const payoutsEnabled = computed(() => summary.value?.payouts_enabled ?? false);
    const detailsSubmitted = computed(() => summary.value?.details_submitted ?? false);

    const hasPendingRequirements = computed(() => {
        if (!summary.value?.requirements) return false;
        return (
            (summary.value.requirements.currently_due?.length ?? 0) > 0 ||
            (summary.value.requirements.past_due?.length ?? 0) > 0 ||
            Boolean(summary.value.requirements.disabled_reason)
        );
    });

    const clear = () => {
        summary.value = null;
        account.value = null;
        isReadyForPayments.value = false;
        featureEnabled.value = true;
        canManage.value = false;
        loading.value = false;
        onboardingLoading.value = false;
        error.value = null;
    };

    const fetchStatus = async (companyId: number) => {
        loading.value = true;
        error.value = null;
        try {
            const data = await stripeConnectService.getStatus(companyId);
            summary.value = data.summary;
            account.value = data.account;
            isReadyForPayments.value = data.is_ready_for_payments;
            featureEnabled.value = data.feature_enabled;
            canManage.value = data.can_manage;
            return data;
        } catch (err: any) {
            error.value = err?.response?.data?.message || 'Failed to fetch Stripe Connect status.';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    const startOnboarding = async (
        companyId: number,
        returnUrl?: string,
        refreshUrl?: string
    ): Promise<string> => {
        onboardingLoading.value = true;
        error.value = null;
        try {
            const res = await stripeConnectService.startOnboarding(companyId, returnUrl, refreshUrl);
            return res.url;
        } catch (err: any) {
            error.value = err?.response?.data?.message || 'Failed to start Stripe onboarding.';
            throw err;
        } finally {
            onboardingLoading.value = false;
        }
    };

    const syncStatus = async (companyId: number) => {
        loading.value = true;
        error.value = null;
        try {
            const data = await stripeConnectService.syncAccount(companyId);
            summary.value = data.summary;
            account.value = data.account;
            isReadyForPayments.value = data.is_ready_for_payments;
            return data;
        } catch (err: any) {
            error.value = err?.response?.data?.message || 'Failed to sync Stripe status.';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    const getDashboardLink = async (companyId: number): Promise<string> => {
        loading.value = true;
        error.value = null;
        try {
            const res = await stripeConnectService.getDashboardLink(companyId);
            return res.url;
        } catch (err: any) {
            error.value = err?.response?.data?.message || 'Failed to generate Stripe dashboard link.';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    const disconnect = async (companyId: number) => {
        loading.value = true;
        error.value = null;
        try {
            const res = await stripeConnectService.disconnect(companyId);
            summary.value = res.summary;
            account.value = null;
            isReadyForPayments.value = false;
            return res;
        } catch (err: any) {
            error.value = err?.response?.data?.message || 'Failed to disconnect Stripe account.';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    return {
        summary,
        account,
        isReadyForPayments,
        featureEnabled,
        canManage,
        loading,
        onboardingLoading,
        error,
        isConnected,
        status,
        chargesEnabled,
        payoutsEnabled,
        detailsSubmitted,
        hasPendingRequirements,
        clear,
        fetchStatus,
        startOnboarding,
        syncStatus,
        getDashboardLink,
        disconnect,
    };
});
