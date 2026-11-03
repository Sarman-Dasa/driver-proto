<template>
    <div class="mx-auto" style="max-width: 1100px;">
        <!-- Page Header -->
        <div class="d-flex flex-column flex-sm-row align-start align-sm-center justify-space-between mb-8 gap-4">
            <div>
                <div class="d-flex align-center gap-2 mb-1">
                    <h1 class="text-h4 font-weight-bold tracking-tight text-slate-900">
                        Stripe Connect
                    </h1>
                    <v-chip
                        v-if="!stripeStore.loading && stripeStore.summary"
                        :color="statusColor"
                        size="small"
                        variant="flat"
                        class="font-weight-bold text-uppercase ml-2"
                    >
                        {{ statusLabel }}
                    </v-chip>
                </div>
                <p class="text-body-1 text-slate-600 mb-0">
                    Connect your Stripe account to receive direct payments from your invoice customers.
                </p>
            </div>
            <div class="d-flex align-center gap-2">
                <v-btn
                    variant="tonal"
                    color="primary"
                    prepend-icon="mdi-sync"
                    class="rounded-lg text-none font-weight-bold"
                    :loading="stripeStore.loading"
                    @click="handleSync"
                >
                    Refresh Status
                </v-btn>
                <v-btn
                    v-if="stripeStore.isConnected && stripeStore.detailsSubmitted"
                    variant="outlined"
                    color="primary"
                    prepend-icon="mdi-open-in-new"
                    class="rounded-lg text-none font-weight-bold ml-2"
                    :loading="dashboardLoading"
                    @click="openDashboard"
                >
                    Stripe Dashboard
                </v-btn>
            </div>
        </div>

        <!-- Banner for Free Plan Feature Restriction -->
        <v-alert
            v-if="!stripeStore.featureEnabled"
            type="warning"
            variant="tonal"
            class="mb-6 rounded-xl border"
            icon="mdi-lock-outline"
        >
            <div class="d-flex flex-column flex-sm-row align-sm-center justify-space-between gap-3">
                <div>
                    <div class="font-weight-bold text-subtitle-1">Stripe Connect Requires Pro or Business Plan</div>
                    <div class="text-body-2">
                        Online invoice payments via Stripe Connect are available on our Pro and Business tiers. Upgrade your workspace plan to start collecting client payments online.
                    </div>
                </div>
                <v-btn
                    color="warning"
                    variant="flat"
                    class="rounded-lg text-none font-weight-bold mt-2 mt-sm-0"
                    to="/pricing"
                >
                    Upgrade Plan
                </v-btn>
            </div>
        </v-alert>

        <!-- Redirect Notification Alerts -->
        <v-alert
            v-if="redirectMessage"
            :type="redirectMessageType"
            variant="tonal"
            class="mb-6 rounded-xl border"
            closable
            @click:close="redirectMessage = null"
        >
            <div class="font-weight-bold">{{ redirectMessageTitle }}</div>
            <div class="text-body-2">{{ redirectMessage }}</div>
        </v-alert>

        <!-- General Error Alert -->
        <v-alert
            v-if="stripeStore.error"
            type="error"
            variant="tonal"
            class="mb-6 rounded-xl border"
            closable
            @click:close="stripeStore.error = null"
        >
            {{ stripeStore.error }}
        </v-alert>

        <!-- Connection State Cards -->
        <v-card class="rounded-2xl border bg-white mb-8 elevation-1 pa-6">
            <v-row align="center">
                <v-col cols="12" md="8">
                    <div class="d-flex align-center gap-3 mb-2">
                        <v-avatar color="indigo-lighten-5" size="48" class="mr-2 rounded-lg">
                            <v-icon icon="mdi-credit-card-wireless-outline" color="indigo-darken-1" size="28"></v-icon>
                        </v-avatar>
                        <div>
                            <h2 class="text-h6 font-weight-bold text-slate-900 mb-0">
                                {{ connectionCardTitle }}
                            </h2>
                            <p class="text-body-2 text-slate-600 mb-0">
                                {{ connectionCardSubtitle }}
                            </p>
                        </div>
                    </div>
                </v-col>
                <v-col cols="12" md="4" class="text-md-right">
                    <!-- Action Button: Connect / Continue Onboarding -->
                    <template v-if="stripeStore.featureEnabled">
                        <v-btn
                            v-if="!stripeStore.isConnected"
                            color="indigo-darken-1"
                            size="large"
                            prepend-icon="mdi-lightning-bolt"
                            class="rounded-lg text-none font-weight-bold"
                            :loading="stripeStore.onboardingLoading"
                            :disabled="!stripeStore.canManage"
                            @click="handleOnboard"
                        >
                            Connect with Stripe
                        </v-btn>

                        <v-btn
                            v-else-if="!stripeStore.isReadyForPayments"
                            color="warning"
                            size="large"
                            prepend-icon="mdi-alert-circle-outline"
                            class="rounded-lg text-none font-weight-bold"
                            :loading="stripeStore.onboardingLoading"
                            :disabled="!stripeStore.canManage"
                            @click="handleOnboard"
                        >
                            Complete Onboarding
                        </v-btn>

                        <div v-else class="d-flex align-center justify-md-end text-success font-weight-bold">
                            <v-icon icon="mdi-check-decagram" class="mr-2" size="24"></v-icon>
                            <span>Ready to accept payments</span>
                        </div>
                    </template>
                </v-col>
            </v-row>

            <v-divider class="my-6"></v-divider>

            <!-- Capabilities & Readiness Grid -->
            <v-row>
                <v-col cols="12" sm="6" md="3">
                    <div class="pa-4 rounded-xl border bg-slate-50 h-100">
                        <div class="text-caption font-weight-bold text-slate-500 text-uppercase mb-1">
                            Card Charges
                        </div>
                        <div class="d-flex align-center">
                            <v-icon
                                :icon="stripeStore.chargesEnabled ? 'mdi-check-circle' : 'mdi-close-circle'"
                                :color="stripeStore.chargesEnabled ? 'success' : 'grey-lighten-1'"
                                size="20"
                                class="mr-2"
                            ></v-icon>
                            <span class="font-weight-bold text-body-1">
                                {{ stripeStore.chargesEnabled ? 'Enabled' : 'Disabled' }}
                            </span>
                        </div>
                        <div class="text-caption text-slate-500 mt-1">
                            Accept customer credit card payments
                        </div>
                    </div>
                </v-col>

                <v-col cols="12" sm="6" md="3">
                    <div class="pa-4 rounded-xl border bg-slate-50 h-100">
                        <div class="text-caption font-weight-bold text-slate-500 text-uppercase mb-1">
                            Bank Payouts
                        </div>
                        <div class="d-flex align-center">
                            <v-icon
                                :icon="stripeStore.payoutsEnabled ? 'mdi-check-circle' : 'mdi-close-circle'"
                                :color="stripeStore.payoutsEnabled ? 'success' : 'grey-lighten-1'"
                                size="20"
                                class="mr-2"
                            ></v-icon>
                            <span class="font-weight-bold text-body-1">
                                {{ stripeStore.payoutsEnabled ? 'Enabled' : 'Disabled' }}
                            </span>
                        </div>
                        <div class="text-caption text-slate-500 mt-1">
                            Automatic payouts to your bank account
                        </div>
                    </div>
                </v-col>

                <v-col cols="12" sm="6" md="3">
                    <div class="pa-4 rounded-xl border bg-slate-50 h-100">
                        <div class="text-caption font-weight-bold text-slate-500 text-uppercase mb-1">
                            Details Submitted
                        </div>
                        <div class="d-flex align-center">
                            <v-icon
                                :icon="stripeStore.detailsSubmitted ? 'mdi-check-circle' : 'mdi-clock-outline'"
                                :color="stripeStore.detailsSubmitted ? 'success' : 'warning'"
                                size="20"
                                class="mr-2"
                            ></v-icon>
                            <span class="font-weight-bold text-body-1">
                                {{ stripeStore.detailsSubmitted ? 'Submitted' : 'Pending' }}
                            </span>
                        </div>
                        <div class="text-caption text-slate-500 mt-1">
                            Business & bank verification info
                        </div>
                    </div>
                </v-col>

                <v-col cols="12" sm="6" md="3">
                    <div class="pa-4 rounded-xl border bg-slate-50 h-100">
                        <div class="text-caption font-weight-bold text-slate-500 text-uppercase mb-1">
                            Invoice Payment Status
                        </div>
                        <div class="d-flex align-center">
                            <v-icon
                                :icon="stripeStore.isReadyForPayments ? 'mdi-shield-check' : 'mdi-shield-alert'"
                                :color="stripeStore.isReadyForPayments ? 'success' : 'warning'"
                                size="20"
                                class="mr-2"
                            ></v-icon>
                            <span class="font-weight-bold text-body-1">
                                {{ stripeStore.isReadyForPayments ? 'Ready' : 'Not Ready' }}
                            </span>
                        </div>
                        <div class="text-caption text-slate-500 mt-1">
                            {{ stripeStore.isReadyForPayments ? 'Invoices can be paid online' : 'Finish onboarding to enable' }}
                        </div>
                    </div>
                </v-col>
            </v-row>
        </v-card>

        <!-- Requirements or Information Required Card -->
        <v-card
            v-if="stripeStore.hasPendingRequirements"
            class="rounded-2xl border bg-amber-lighten-5 mb-8 elevation-1 pa-6 border-amber-lighten-2"
        >
            <div class="d-flex align-start gap-3">
                <v-icon icon="mdi-alert-circle" color="warning" size="28" class="mr-2 mt-1"></v-icon>
                <div>
                    <h3 class="text-h6 font-weight-bold text-slate-900 mb-1">
                        Additional Information Required by Stripe
                    </h3>
                    <p class="text-body-2 text-slate-700 mb-3">
                        Stripe needs additional verification information before full charge and payout functionality is enabled.
                    </p>

                    <div v-if="stripeStore.summary?.requirements?.disabled_reason" class="mb-3">
                        <strong class="text-caption text-error font-weight-bold text-uppercase">Disabled Reason:</strong>
                        <div class="text-body-2 text-error font-weight-medium">
                            {{ stripeStore.summary.requirements.disabled_reason }}
                        </div>
                    </div>

                    <div v-if="stripeStore.summary?.requirements?.currently_due?.length" class="mb-2">
                        <span class="text-caption font-weight-bold text-slate-600 text-uppercase">Action items needed:</span>
                        <div class="d-flex flex-wrap gap-2 mt-1">
                            <v-chip
                                v-for="item in stripeStore.summary.requirements.currently_due"
                                :key="item"
                                size="small"
                                color="warning"
                                variant="tonal"
                                class="font-weight-medium"
                            >
                                {{ formatRequirement(item) }}
                            </v-chip>
                        </div>
                    </div>

                    <v-btn
                        v-if="stripeStore.canManage"
                        color="warning"
                        variant="flat"
                        class="rounded-lg text-none font-weight-bold mt-3"
                        prepend-icon="mdi-pencil-outline"
                        :loading="stripeStore.onboardingLoading"
                        @click="handleOnboard"
                    >
                        Provide Required Details on Stripe
                    </v-btn>
                </div>
            </div>
        </v-card>

        <!-- Account Details Card -->
        <v-card v-if="stripeStore.isConnected" class="rounded-2xl border bg-white mb-8 elevation-1 pa-6">
            <h3 class="text-h6 font-weight-bold text-slate-900 mb-4">
                Connected Account Information
            </h3>
            <v-row>
                <v-col cols="12" sm="6" md="3">
                    <div class="text-caption text-slate-500 font-weight-medium">Stripe Account ID</div>
                    <div class="text-body-2 font-weight-bold text-slate-800 font-mono">
                        {{ stripeStore.summary?.account_id }}
                    </div>
                </v-col>
                <v-col cols="12" sm="6" md="3">
                    <div class="text-caption text-slate-500 font-weight-medium">Account Type</div>
                    <div class="text-body-2 font-weight-bold text-slate-800 text-capitalize">
                        Express
                    </div>
                </v-col>
                <v-col cols="12" sm="6" md="3">
                    <div class="text-caption text-slate-500 font-weight-medium">Country / Currency</div>
                    <div class="text-body-2 font-weight-bold text-slate-800">
                        {{ stripeStore.summary?.country }} / {{ stripeStore.summary?.currency }}
                    </div>
                </v-col>
                <v-col cols="12" sm="6" md="3">
                    <div class="text-caption text-slate-500 font-weight-medium">Last Synchronized</div>
                    <div class="text-body-2 font-weight-bold text-slate-800">
                        {{ formatDate(stripeStore.summary?.last_synced_at) }}
                    </div>
                </v-col>
            </v-row>

            <v-divider class="my-6"></v-divider>

            <div class="d-flex align-center justify-space-between flex-wrap gap-4">
                <div>
                    <h4 class="text-subtitle-2 font-weight-bold text-slate-900">Disconnect Connection</h4>
                    <p class="text-caption text-slate-500 mb-0">
                        Disconnecting will disable online invoice payments for this workspace.
                    </p>
                </div>
                <v-btn
                    v-if="stripeStore.canManage"
                    variant="outlined"
                    color="error"
                    size="small"
                    class="rounded-lg text-none font-weight-bold"
                    :loading="disconnectLoading"
                    @click="confirmDisconnect = true"
                >
                    Disconnect Account
                </v-btn>
            </div>
        </v-card>

        <!-- Disconnect Confirmation Dialog -->
        <v-dialog v-model="confirmDisconnect" max-width="480">
            <v-card class="pa-4 rounded-xl">
                <v-card-title class="font-weight-bold">Disconnect Stripe Account?</v-card-title>
                <v-card-text class="text-slate-600">
                    Are you sure you want to disconnect this Stripe account? Your invoice customers will no longer be able to pay invoices online until you reconnect.
                </v-card-text>
                <v-card-actions class="justify-end gap-2">
                    <v-btn variant="text" class="text-none" @click="confirmDisconnect = false">Cancel</v-btn>
                    <v-btn
                        color="error"
                        variant="flat"
                        class="text-none font-weight-bold rounded-lg"
                        :loading="disconnectLoading"
                        @click="handleDisconnect"
                    >
                        Confirm Disconnect
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { useCompanyStore } from '@/stores/company';
import { useStripeConnectStore } from '@/stores/stripeConnect';

const route = useRoute();
const companyStore = useCompanyStore();
const stripeStore = useStripeConnectStore();

const dashboardLoading = ref(false);
const disconnectLoading = ref(false);
const confirmDisconnect = ref(false);
const redirectMessage = ref<string | null>(null);
const redirectMessageType = ref<'info' | 'success' | 'warning'>('info');
const redirectMessageTitle = ref<string>('');

const companyId = computed(() => companyStore.currentCompany?.id ?? 0);

const statusColor = computed(() => {
    switch (stripeStore.status) {
        case 'active':
            return 'success';
        case 'restricted':
            return 'warning';
        case 'pending':
            return 'info';
        case 'disabled':
            return 'error';
        default:
            return 'grey';
    }
});

const statusLabel = computed(() => {
    if (stripeStore.isReadyForPayments) {
        return 'Active & Ready';
    }
    return stripeStore.summary?.status_label || 'Not Connected';
});

const connectionCardTitle = computed(() => {
    if (!stripeStore.isConnected) {
        return 'Not Connected';
    }
    if (stripeStore.isReadyForPayments) {
        return 'Stripe Connected & Verified';
    }
    if (stripeStore.hasPendingRequirements) {
        return 'Additional Information Required';
    }
    return 'Onboarding In Progress';
});

const connectionCardSubtitle = computed(() => {
    if (!stripeStore.isConnected) {
        return 'Connect an Express Stripe account in just a few minutes to accept credit cards on invoices.';
    }
    if (stripeStore.isReadyForPayments) {
        return 'Your Stripe account is fully configured and ready to process customer invoice payments.';
    }
    return 'Please complete the remaining onboarding steps to enable payouts and charges.';
});

const formatRequirement = (item: string) => {
    return item.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
};

const formatDate = (dateStr?: string | null) => {
    if (!dateStr) return 'Never';
    return new Date(dateStr).toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
};

const handleSync = async () => {
    if (!companyId.value) return;
    try {
        await stripeStore.syncStatus(companyId.value);
    } catch {
        // Handled in store
    }
};

const handleOnboard = async () => {
    if (!companyId.value) return;
    try {
        const url = await stripeStore.startOnboarding(companyId.value);
        if (url) {
            window.location.href = url;
        }
    } catch {
        // Handled in store
    }
};

const openDashboard = async () => {
    if (!companyId.value) return;
    dashboardLoading.value = true;
    try {
        const url = await stripeStore.getDashboardLink(companyId.value);
        if (url) {
            window.open(url, '_blank', 'noopener,noreferrer');
        }
    } catch {
        // Handled in store
    } finally {
        dashboardLoading.value = false;
    }
};

const handleDisconnect = async () => {
    if (!companyId.value) return;
    disconnectLoading.value = true;
    try {
        await stripeStore.disconnect(companyId.value);
        confirmDisconnect.value = false;
    } catch {
        // Handled in store
    } finally {
        disconnectLoading.value = false;
    }
};

onMounted(async () => {
    if (!companyId.value) return;

    // Check if returning from Stripe onboarding
    const isReturn = route.query.return === 'true';
    const isRefresh = route.query.refresh === 'true';

    await stripeStore.fetchStatus(companyId.value);

    if (isReturn) {
        // Authoritatively sync status from Stripe
        await handleSync();
        if (stripeStore.isReadyForPayments) {
            redirectMessageType.value = 'success';
            redirectMessageTitle.value = 'Stripe Onboarding Complete!';
            redirectMessage.value = 'Your Stripe account has been verified and is ready to process customer invoice payments.';
        } else {
            redirectMessageType.value = 'info';
            redirectMessageTitle.value = 'Stripe Verification In Progress';
            redirectMessage.value = 'You have returned from Stripe. Some verification checks or requirements may still be pending with Stripe.';
        }
    } else if (isRefresh) {
        redirectMessageType.value = 'warning';
        redirectMessageTitle.value = 'Onboarding Link Expired';
        redirectMessage.value = 'The previous onboarding session timed out. Click "Complete Onboarding" to resume where you left off.';
    }
});
</script>
