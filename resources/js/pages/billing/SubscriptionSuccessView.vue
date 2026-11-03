<template>
    <div class="py-8 px-4 mx-auto" style="max-width: 680px;">
        <v-card class="rounded-2xl border pa-8 text-center bg-white elevation-1">
            <!-- Verifying state -->
            <div v-if="verificationState === 'verifying'" class="py-8">
                <v-progress-circular
                    indeterminate
                    color="primary"
                    size="64"
                    width="6"
                    class="mb-6"
                ></v-progress-circular>
                <h1 class="text-h5 font-weight-bold text-slate-900 mb-2">
                    Verifying Your Subscription
                </h1>
                <p class="text-body-1 text-slate-600 mb-4">
                    Please wait a moment while we securely verify your payment with Stripe.
                </p>
                <div class="text-caption text-slate-400">
                    Attempt {{ pollCount }} of {{ maxPollAttempts }} &bull; Webhook confirmation in progress...
                </div>
            </div>

            <!-- Confirmed state -->
            <div v-else-if="verificationState === 'confirmed'" class="py-6">
                <div class="d-inline-flex align-center justify-center rounded-circle bg-emerald-50 pa-4 mb-5 border border-emerald-200">
                    <v-icon icon="mdi-check-circle" color="#10B981" size="56"></v-icon>
                </div>
                <h1 class="text-h4 font-weight-bold text-slate-900 mb-2">
                    Subscription Confirmed!
                </h1>
                <p class="text-body-1 text-slate-600 mb-6">
                    Your workspace has been successfully upgraded to the
                    <strong class="text-slate-900 font-weight-bold">{{ planName }}</strong> plan.
                    All features and limits are now active.
                </p>

                <div class="d-flex flex-column flex-sm-row justify-center gap-3 mt-4">
                    <v-btn
                        color="primary"
                        size="large"
                        variant="flat"
                        class="rounded-lg text-none font-weight-bold px-6"
                        to="/dashboard"
                    >
                        Go to Dashboard
                    </v-btn>
                    <v-btn
                        variant="outlined"
                        size="large"
                        class="rounded-lg text-none font-weight-bold px-6 ml-sm-3 mt-3 mt-sm-0"
                        to="/settings/subscription"
                    >
                        View Subscription
                    </v-btn>
                </div>
            </div>

            <!-- Error state -->
            <div v-else-if="verificationState === 'error'" class="py-6">
                <div class="d-inline-flex align-center justify-center rounded-circle bg-red-50 pa-4 mb-5 border border-red-200">
                    <v-icon icon="mdi-alert-circle-outline" color="#EF4444" size="56"></v-icon>
                </div>
                <h1 class="text-h5 font-weight-bold text-slate-900 mb-2">
                    Verification Issue
                </h1>
                <p class="text-body-1 text-slate-600 mb-6">
                    {{ errorMessage || 'We were unable to verify this checkout session. Please check your workspace subscription settings.' }}
                </p>

                <div class="d-flex flex-column flex-sm-row justify-center gap-3 mt-4">
                    <v-btn
                        color="primary"
                        size="large"
                        variant="flat"
                        class="rounded-lg text-none font-weight-bold px-6"
                        to="/settings/subscription"
                    >
                        Subscription Settings
                    </v-btn>
                    <v-btn
                        variant="outlined"
                        size="large"
                        class="rounded-lg text-none font-weight-bold px-6 ml-sm-3 mt-3 mt-sm-0"
                        @click="checkSubscription"
                    >
                        Retry Verification
                    </v-btn>
                </div>
            </div>

            <!-- Pending confirmation (timed out waiting for webhook) -->
            <div v-else class="py-6">
                <div class="d-inline-flex align-center justify-center rounded-circle bg-amber-50 pa-4 mb-5 border border-amber-200">
                    <v-icon icon="mdi-clock-outline" color="#F59E0B" size="56"></v-icon>
                </div>
                <h1 class="text-h5 font-weight-bold text-slate-900 mb-2">
                    Payment Processing
                </h1>
                <p class="text-body-1 text-slate-600 mb-6">
                    Your payment was accepted by Stripe! Webhook confirmation is taking a little longer than usual.
                    Your workspace subscription will update automatically within the next minute.
                </p>

                <div class="d-flex flex-column flex-sm-row justify-center gap-3 mt-4">
                    <v-btn
                        color="primary"
                        size="large"
                        variant="flat"
                        class="rounded-lg text-none font-weight-bold px-6"
                        to="/dashboard"
                    >
                        Return to Dashboard
                    </v-btn>
                    <v-btn
                        variant="outlined"
                        size="large"
                        class="rounded-lg text-none font-weight-bold px-6 ml-sm-3 mt-3 mt-sm-0"
                        to="/settings/subscription"
                    >
                        Subscription Settings
                    </v-btn>
                </div>
            </div>
        </v-card>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { useRoute } from 'vue-router';
import { useSubscriptionStore } from '@/stores/subscription';
import { useCompanyStore } from '@/stores/company';

const route = useRoute();
const subscriptionStore = useSubscriptionStore();
const companyStore = useCompanyStore();

const verificationState = ref<'verifying' | 'confirmed' | 'pending' | 'error'>('verifying');
const errorMessage = ref('');
const pollCount = ref(0);
const maxPollAttempts = 15;
let pollTimer: ReturnType<typeof setInterval> | null = null;

const planName = computed(() => subscriptionStore.currentPlan?.name ?? 'Premium');

const checkSubscription = async () => {
    pollCount.value++;
    const targetCompanyId = companyStore.currentCompany?.id || Number(localStorage.getItem('billflow_current_company_id'));
    const sessionId = (route.query.session_id as string) || null;

    try {
        if (sessionId && targetCompanyId) {
            try {
                const verified = await subscriptionStore.verifySession(sessionId, targetCompanyId);
                const sub = verified?.subscription;
                if (sub && sub.is_active && sub.stripe_subscription_id) {
                    verificationState.value = 'confirmed';
                    await subscriptionStore.fetchPlan(targetCompanyId);
                    await companyStore.fetchCompanies();
                    if (pollTimer) {
                        clearInterval(pollTimer);
                        pollTimer = null;
                    }
                    return;
                }
            } catch (verErr: any) {
                if (verErr.response?.status === 403) {
                    verificationState.value = 'error';
                    errorMessage.value = verErr.response?.data?.message || 'Unauthorized checkout session.';
                    if (pollTimer) {
                        clearInterval(pollTimer);
                        pollTimer = null;
                    }
                    return;
                }
            }
        }

        const summary = await subscriptionStore.fetchSubscription(targetCompanyId);
        const sub = summary?.subscription;

        // If subscription is active with stripe_subscription_id, verified!
        if (sub && sub.is_active && sub.stripe_subscription_id) {
            verificationState.value = 'confirmed';
            await subscriptionStore.fetchPlan(targetCompanyId);
            await companyStore.fetchCompanies();
            if (pollTimer) {
                clearInterval(pollTimer);
                pollTimer = null;
            }
            return;
        }
    } catch {
        // Continue polling
    }

    if (pollCount.value >= maxPollAttempts) {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
        if (verificationState.value === 'verifying') {
            verificationState.value = 'pending';
        }
    }
};

onMounted(() => {
    checkSubscription();
    pollTimer = setInterval(checkSubscription, 2000);
});

onUnmounted(() => {
    if (pollTimer) {
        clearInterval(pollTimer);
    }
});
</script>

<style scoped>
.text-slate-900 {
    color: #0f172a !important;
}
.text-slate-600 {
    color: #475569 !important;
}
.text-slate-400 {
    color: #94a3b8 !important;
}
.bg-emerald-50 {
    background-color: #ecfdf5 !important;
}
.border-emerald-200 {
    border-color: #a7f3d0 !important;
}
.bg-amber-50 {
    background-color: #fffbeb !important;
}
.border-amber-200 {
    border-color: #fde68a !important;
}
.bg-red-50 {
    background-color: #fef2f2 !important;
}
.border-red-200 {
    border-color: #fecaca !important;
}
</style>
