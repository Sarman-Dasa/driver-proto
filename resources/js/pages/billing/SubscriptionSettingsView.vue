<template>
    <v-container class="py-8 px-4 px-md-6" style="max-width: 1200px;">
        <!-- Page Header -->
        <div class="d-flex flex-column flex-sm-row align-start align-sm-center justify-space-between mb-8 gap-4">
            <div>
                <h1 class="text-h4 font-weight-bold text-slate-900 mb-1">
                    Subscription & Billing
                </h1>
                <p class="text-body-1 text-slate-600 mb-0">
                    Manage your workspace plan, payment methods, and billing history.
                </p>
            </div>
            <div class="d-flex align-center gap-2">
                <v-btn
                    variant="outlined"
                    color="primary"
                    prepend-icon="mdi-arrow-up-bold-circle-outline"
                    class="rounded-lg text-none font-weight-bold"
                    to="/pricing"
                >
                    Compare Plans
                </v-btn>
                <v-btn
                    variant="tonal"
                    color="primary"
                    prepend-icon="mdi-sync"
                    class="rounded-lg text-none font-weight-bold ml-2"
                    :loading="syncLoading"
                    @click="handleSync"
                >
                    Sync Status
                </v-btn>
                <v-btn
                    v-if="hasStripeCustomer"
                    color="primary"
                    prepend-icon="mdi-credit-card-outline"
                    class="rounded-lg text-none font-weight-bold ml-2"
                    :loading="portalLoading"
                    @click="openPortal"
                >
                    Manage Invoices & Cards
                </v-btn>
            </div>
        </div>

        <!-- Cancellation Notice Alert -->
        <v-alert
            v-if="subscription?.cancel_at_period_end"
            type="warning"
            variant="tonal"
            class="mb-6 rounded-xl border"
            closable
        >
            <div class="d-flex flex-column flex-sm-row align-sm-center justify-space-between gap-3">
                <div>
                    <div class="font-weight-bold">Subscription Scheduled for Cancellation</div>
                    <div class="text-body-2">
                        Your subscription will end on
                        <strong>{{ formatDate(subscription.current_period_end) }}</strong>.
                        You retain full access until then. You will not be charged again.
                    </div>
                </div>
                <v-btn
                    color="warning"
                    variant="flat"
                    size="small"
                    class="rounded-lg text-none font-weight-bold mt-2 mt-sm-0"
                    :loading="resumeLoading"
                    @click="handleResume"
                >
                    Resume Subscription
                </v-btn>
            </div>
        </v-alert>

        <!-- Current Plan Overview Card -->
        <v-card class="rounded-2xl border bg-white mb-8 elevation-1 pa-6">
            <v-row align="center">
                <v-col cols="12" md="6">
                    <div class="d-flex align-center gap-3 mb-2">
                        <h2 class="text-h5 font-weight-bold text-slate-900">
                            {{ planName }} Plan
                        </h2>
                        <v-chip
                            :color="statusColor"
                            size="small"
                            variant="flat"
                            class="font-weight-bold text-uppercase ml-2"
                        >
                            {{ statusLabel }}
                        </v-chip>
                    </div>
                    <p class="text-body-2 text-slate-600 mb-4">
                        {{ planDescription }}
                    </p>

                    <div class="d-flex flex-wrap gap-4 text-body-2 text-slate-600">
                        <div v-if="subscription?.current_period_end" class="d-flex align-center mr-4">
                            <v-icon icon="mdi-calendar-sync" size="18" class="mr-1 text-slate-400"></v-icon>
                            <span>
                                {{ subscription.cancel_at_period_end ? 'Expires' : 'Renews' }}:
                                <strong>{{ formatDate(subscription.current_period_end) }}</strong>
                            </span>
                        </div>
                        <div v-if="subscription?.billing_interval" class="d-flex align-center">
                            <v-icon icon="mdi-repeat" size="18" class="mr-1 text-slate-400"></v-icon>
                            <span>
                                Cycle: <strong class="text-capitalize">{{ subscription.billing_interval }}</strong>
                            </span>
                        </div>
                    </div>
                </v-col>

                <v-col cols="12" md="6" class="d-flex justify-md-end align-center mt-4 mt-md-0 gap-3">
                    <v-btn
                        v-if="isPaidSubscription && !subscription?.cancel_at_period_end"
                        variant="text"
                        color="error"
                        class="text-none font-weight-bold"
                        @click="showCancelDialog = true"
                    >
                        Cancel Subscription
                    </v-btn>
                    <v-btn
                        v-if="!isPaidSubscription"
                        color="primary"
                        size="large"
                        class="rounded-lg text-none font-weight-bold"
                        to="/pricing"
                    >
                        Upgrade Workspace
                    </v-btn>
                </v-col>
            </v-row>
        </v-card>

        <!-- Billing History Section -->
        <v-card class="rounded-2xl border bg-white elevation-1">
            <div class="pa-6 border-b d-flex align-center justify-space-between">
                <div>
                    <h2 class="text-h6 font-weight-bold text-slate-900 mb-1">
                        Billing & Payment History
                    </h2>
                    <p class="text-body-2 text-slate-500 mb-0">
                        Official record of Stripe transactions, renewals, and receipts.
                    </p>
                </div>
                <v-btn
                    variant="text"
                    icon="mdi-refresh"
                    size="small"
                    :loading="historyLoading"
                    @click="loadHistory(1)"
                ></v-btn>
            </div>

            <!-- Empty State -->
            <div v-if="billingHistories.length === 0 && !historyLoading" class="text-center py-12 px-4">
                <v-icon icon="mdi-receipt-text-outline" size="48" color="#94A3B8" class="mb-3"></v-icon>
                <div class="text-h6 font-weight-bold text-slate-700 mb-1">No billing records yet</div>
                <p class="text-body-2 text-slate-500 mx-auto" style="max-width: 400px;">
                    Invoices and transaction receipts will appear here once you subscribe or renew.
                </p>
            </div>

            <!-- History Table -->
            <v-table v-else hover class="billing-table">
                <thead>
                    <tr>
                        <th class="text-left font-weight-bold text-slate-700">Date</th>
                        <th class="text-left font-weight-bold text-slate-700">Description</th>
                        <th class="text-left font-weight-bold text-slate-700">Amount</th>
                        <th class="text-left font-weight-bold text-slate-700">Status</th>
                        <th class="text-right font-weight-bold text-slate-700">Receipt</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in billingHistories" :key="item.id">
                        <td class="text-slate-800 text-body-2 font-weight-medium">
                            {{ formatDate(item.event_at) }}
                        </td>
                        <td class="text-slate-600 text-body-2">
                            {{ item.description || formatBillingReason(item.billing_reason) }}
                        </td>
                        <td class="text-slate-900 text-body-2 font-weight-bold">
                            ${{ item.formatted_amount }} {{ item.currency }}
                        </td>
                        <td>
                            <v-chip
                                :color="getHistoryStatusColor(item.status)"
                                size="x-small"
                                variant="flat"
                                class="font-weight-bold text-uppercase"
                            >
                                {{ item.status }}
                            </v-chip>
                        </td>
                        <td class="text-right">
                            <v-btn
                                v-if="item.invoice_pdf_url"
                                :href="item.invoice_pdf_url"
                                target="_blank"
                                rel="noopener"
                                size="small"
                                variant="text"
                                color="primary"
                                prepend-icon="mdi-download"
                                class="text-none font-weight-bold"
                            >
                                PDF
                            </v-btn>
                            <v-btn
                                v-else-if="item.hosted_invoice_url"
                                :href="item.hosted_invoice_url"
                                target="_blank"
                                rel="noopener"
                                size="small"
                                variant="text"
                                color="primary"
                                prepend-icon="mdi-open-in-new"
                                class="text-none font-weight-bold"
                            >
                                View
                            </v-btn>
                            <span v-else class="text-caption text-slate-400">—</span>
                        </td>
                    </tr>
                </tbody>
            </v-table>

            <!-- Table Pagination -->
            <div
                v-if="billingPagination.last_page > 1"
                class="pa-4 border-t d-flex align-center justify-space-between"
            >
                <div class="text-caption text-slate-500">
                    Showing page {{ billingPagination.current_page }} of {{ billingPagination.last_page }}
                </div>
                <v-pagination
                    v-model="billingPagination.current_page"
                    :length="billingPagination.last_page"
                    total-visible="5"
                    size="small"
                    @update:model-value="loadHistory"
                ></v-pagination>
            </div>
        </v-card>

        <!-- Cancel Confirmation Dialog -->
        <v-dialog v-model="showCancelDialog" max-width="480">
            <v-card class="rounded-2xl pa-6 bg-white">
                <div class="d-flex align-center gap-3 mb-4">
                    <div class="pa-2 rounded-circle bg-red-50">
                        <v-icon icon="mdi-alert-circle-outline" color="#EF4444" size="28"></v-icon>
                    </div>
                    <h3 class="text-h6 font-weight-bold text-slate-900 mb-0">
                        Cancel Subscription?
                    </h3>
                </div>
                <p class="text-body-2 text-slate-600 mb-6">
                    Are you sure you want to cancel? Your subscription will remain active until the end of your billing cycle on
                    <strong>{{ formatDate(subscription?.current_period_end) }}</strong>.
                    You will not be billed again, and you can resume anytime before then.
                </p>

                <div class="d-flex justify-end gap-2">
                    <v-btn
                        variant="text"
                        class="rounded-lg text-none font-weight-bold"
                        @click="showCancelDialog = false"
                    >
                        Keep Subscription
                    </v-btn>
                    <v-btn
                        color="error"
                        variant="flat"
                        class="rounded-lg text-none font-weight-bold ml-2"
                        :loading="cancelLoading"
                        @click="handleCancel"
                    >
                        Confirm Cancellation
                    </v-btn>
                </div>
            </v-card>
        </v-dialog>

        <!-- Snackbar Alerts -->
        <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="4000">
            {{ snackbar.text }}
        </v-snackbar>
    </v-container>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { useSubscriptionStore } from '@/stores/subscription';
import { useCompanyStore } from '@/stores/company';

const subscriptionStore = useSubscriptionStore();
const companyStore = useCompanyStore();

const syncLoading = ref(false);
const portalLoading = ref(false);
const cancelLoading = ref(false);
const resumeLoading = ref(false);
const historyLoading = ref(false);
const showCancelDialog = ref(false);

const snackbar = ref({
    show: false,
    text: '',
    color: 'success',
});

const subscription = computed(() => subscriptionStore.subscription);
const hasStripeCustomer = computed(() => subscriptionStore.hasStripeCustomer);
const billingHistories = computed(() => subscriptionStore.billingHistories);
const billingPagination = computed(() => subscriptionStore.billingPagination);

const planName = computed(() => subscriptionStore.currentPlan?.name ?? 'Free');
const planDescription = computed(
    () => subscriptionStore.currentPlan?.description ?? 'Starter plan for small operations.'
);

const isPaidSubscription = computed(
    () => !!subscription.value?.stripe_subscription_id && subscription.value?.plan_id !== 1
);

const statusLabel = computed(() => {
    if (subscription.value?.cancel_at_period_end) {
        return 'Cancelling';
    }
    return subscription.value?.status_label ?? 'Active';
});

const statusColor = computed(() => {
    if (subscription.value?.cancel_at_period_end) {
        return 'warning';
    }
    const status = subscription.value?.status;
    switch (status) {
        case 'active':
            return 'success';
        case 'trialing':
            return 'info';
        case 'past_due':
            return 'warning';
        case 'cancelled':
        case 'expired':
            return 'error';
        default:
            return 'slate';
    }
});

const formatDate = (dateStr?: string | null) => {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const formatBillingReason = (reason?: string | null) => {
    if (!reason) return 'Subscription payment';
    return reason.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
};

const getHistoryStatusColor = (status: string) => {
    switch (status) {
        case 'paid':
            return 'success';
        case 'failed':
            return 'error';
        case 'refunded':
            return 'info';
        default:
            return 'slate';
    }
};

const handleSync = async () => {
    syncLoading.value = true;
    try {
        await subscriptionStore.syncSubscription();
        await subscriptionStore.fetchPlan();
        await companyStore.fetchCompanies();
        snackbar.value = {
            show: true,
            text: 'Subscription status successfully synchronized from Stripe.',
            color: 'success',
        };
    } catch (e: any) {
        snackbar.value = {
            show: true,
            text: e.message || 'Failed to sync with Stripe.',
            color: 'error',
        };
    } finally {
        syncLoading.value = false;
    }
};

const openPortal = async () => {
    portalLoading.value = true;
    try {
        const portalUrl = await subscriptionStore.openPortal();
        window.location.href = portalUrl;
    } catch (e: any) {
        snackbar.value = {
            show: true,
            text: e.message || 'Unable to open billing portal.',
            color: 'error',
        };
    } finally {
        portalLoading.value = false;
    }
};

const handleCancel = async () => {
    cancelLoading.value = true;
    try {
        await subscriptionStore.cancel(false);
        showCancelDialog.value = false;
        snackbar.value = {
            show: true,
            text: 'Subscription scheduled for cancellation at period end.',
            color: 'success',
        };
    } catch (e: any) {
        snackbar.value = {
            show: true,
            text: e.message || 'Failed to cancel subscription.',
            color: 'error',
        };
    } finally {
        cancelLoading.value = false;
    }
};

const handleResume = async () => {
    resumeLoading.value = true;
    try {
        await subscriptionStore.resume();
        snackbar.value = {
            show: true,
            text: 'Subscription successfully resumed!',
            color: 'success',
        };
    } catch (e: any) {
        snackbar.value = {
            show: true,
            text: e.message || 'Failed to resume subscription.',
            color: 'error',
        };
    } finally {
        resumeLoading.value = false;
    }
};

const loadHistory = async (page = 1) => {
    historyLoading.value = true;
    try {
        await subscriptionStore.fetchBillingHistory(page);
    } finally {
        historyLoading.value = false;
    }
};

onMounted(async () => {
    await subscriptionStore.fetchSubscription();
    await loadHistory(1);
});
</script>

<style scoped>
.text-slate-900 {
    color: #0f172a !important;
}
.text-slate-800 {
    color: #1e293b !important;
}
.text-slate-700 {
    color: #334155 !important;
}
.text-slate-600 {
    color: #475569 !important;
}
.text-slate-500 {
    color: #64748b !important;
}
.text-slate-400 {
    color: #94a3b8 !important;
}
.bg-red-50 {
    background-color: #fef2f2 !important;
}
.border-b {
    border-bottom: 1px solid #e2e8f0 !important;
}
.border-t {
    border-top: 1px solid #e2e8f0 !important;
}
</style>
