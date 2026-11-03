<template>
    <div class="payment-result-container">
        <!-- Verifying State with Bounded Polling -->
        <v-card v-if="state === 'verifying'" class="result-card pa-8 pa-sm-10 text-center" elevation="2">
            <div class="mb-6 position-relative d-inline-block">
                <v-progress-circular
                    indeterminate
                    color="primary"
                    size="72"
                    width="5"
                ></v-progress-circular>
            </div>
            <h2 class="text-h5 font-weight-bold mb-2">Verifying Payment with Stripe</h2>
            <p class="text-body-1 text-medium-emphasis max-w-sm mx-auto mb-6">
                Please wait while we securely confirm your transaction with Stripe and update the invoice records.
            </p>
            <div class="d-flex align-center justify-center gap-2 text-caption text-disabled">
                <v-icon icon="mdi-shield-check" size="16" color="primary"></v-icon>
                <span>Verification check {{ pollAttempt }} of {{ maxPollAttempts }}</span>
            </div>
        </v-card>

        <!-- Verified Success State -->
        <v-card v-else-if="state === 'success'" class="result-card pa-8 pa-sm-10 text-center" elevation="3">
            <v-avatar color="success" variant="tonal" size="76" class="mb-5 elevation-1">
                <v-icon icon="mdi-check-circle" size="48" color="success"></v-icon>
            </v-avatar>
            <h1 class="text-h4 font-weight-black text-slate-900 tracking-tight mb-2">
                Payment Confirmed!
            </h1>
            <p class="text-body-1 text-medium-emphasis mb-6 max-w-md mx-auto">
                Thank you! Your payment for <strong>Invoice #{{ invoiceData?.invoice_number || invoiceNumber }}</strong> has been verified and processed successfully.
            </p>

            <!-- Formal Receipt Card -->
            <v-card variant="outlined" class="receipt-card pa-5 mb-6 text-left rounded-xl">
                <div class="d-flex justify-space-between align-center pb-3 border-b mb-3">
                    <span class="text-caption font-weight-bold text-uppercase text-medium-emphasis">Official Receipt</span>
                    <v-chip color="success" size="small" variant="flat" class="font-weight-bold text-uppercase">
                        Paid in Full
                    </v-chip>
                </div>

                <div class="receipt-row d-flex justify-space-between py-1 text-body-2">
                    <span class="text-medium-emphasis">Issued By</span>
                    <span class="font-weight-bold">{{ invoiceData?.company?.name || 'Authorized Merchant' }}</span>
                </div>

                <div v-if="paymentInfo?.payment_reference" class="receipt-row d-flex justify-space-between py-1 text-body-2">
                    <span class="text-medium-emphasis">Payment Reference</span>
                    <span class="font-mono font-weight-medium">{{ paymentInfo.payment_reference }}</span>
                </div>

                <div v-if="formattedPaidAt" class="receipt-row d-flex justify-space-between py-1 text-body-2">
                    <span class="text-medium-emphasis">Transaction Date</span>
                    <span>{{ formattedPaidAt }}</span>
                </div>

                <div class="receipt-row d-flex justify-space-between py-1 text-body-2">
                    <span class="text-medium-emphasis">Payment Method</span>
                    <span class="d-flex align-center">
                        <v-icon icon="mdi-credit-card-outline" size="16" class="mr-1"></v-icon>
                        Credit / Debit Card (Stripe)
                    </span>
                </div>

                <v-divider class="my-3"></v-divider>

                <div class="receipt-row d-flex justify-space-between py-1 text-body-1 font-weight-bold">
                    <span>Amount Paid</span>
                    <span class="text-success text-h6 font-weight-bold">
                        ${{ (paymentInfo?.amount_decimal ?? invoiceData?.amount_paid_decimal ?? 0).toFixed(2) }} {{ invoiceData?.currency || 'USD' }}
                    </span>
                </div>

                <div class="receipt-row d-flex justify-space-between py-1 text-caption text-medium-emphasis">
                    <span>Remaining Balance</span>
                    <span class="font-weight-medium">
                        ${{ (invoiceData?.balance_due_decimal ?? 0).toFixed(2) }} {{ invoiceData?.currency || 'USD' }}
                    </span>
                </div>
            </v-card>

            <!-- Actions -->
            <div class="d-flex flex-column flex-sm-row justify-center gap-3">
                <v-btn
                    color="primary"
                    variant="flat"
                    size="large"
                    class="text-none font-weight-bold rounded-lg px-6"
                    prepend-icon="mdi-printer"
                    @click="handlePrint"
                >
                    Print Receipt
                </v-btn>
                <v-btn
                    variant="outlined"
                    size="large"
                    class="text-none rounded-lg px-6"
                    prepend-icon="mdi-file-document-outline"
                    :to="{ name: 'public.invoice.pay', params: { token } }"
                >
                    View Invoice
                </v-btn>
            </div>
        </v-card>

        <!-- Pending State (Async Processing / Awaiting Webhook) -->
        <v-card v-else-if="state === 'pending'" class="result-card pa-8 pa-sm-10 text-center" elevation="2">
            <v-avatar color="warning" variant="tonal" size="76" class="mb-5 elevation-1">
                <v-icon icon="mdi-clock-outline" size="48" color="warning"></v-icon>
            </v-avatar>
            <h1 class="text-h4 font-weight-black text-slate-900 tracking-tight mb-2">
                Payment Processing
            </h1>
            <p class="text-body-1 text-medium-emphasis mb-6 max-w-md mx-auto">
                Your payment request has been received by Stripe and is currently being cleared. Once verified, your invoice will be marked as paid automatically.
            </p>

            <v-alert
                type="info"
                variant="tonal"
                density="comfortable"
                class="rounded-xl text-left mb-6 max-w-md mx-auto text-body-2"
                icon="mdi-information-outline"
            >
                Depending on your card issuer or payment method, confirmation may take a few moments. You can re-check the status below or return to your invoice.
            </v-alert>

            <div class="d-flex flex-column flex-sm-row justify-center gap-3">
                <v-btn
                    color="primary"
                    size="large"
                    class="text-none font-weight-bold rounded-lg px-6"
                    prepend-icon="mdi-refresh"
                    :loading="refreshing"
                    @click="handleManualRefresh"
                >
                    Refresh Status
                </v-btn>
                <v-btn
                    variant="outlined"
                    size="large"
                    class="text-none rounded-lg px-6"
                    :to="{ name: 'public.invoice.pay', params: { token } }"
                >
                    Return to Invoice
                </v-btn>
            </div>
        </v-card>

        <!-- Failed State -->
        <v-card v-else-if="state === 'failed'" class="result-card pa-8 pa-sm-10 text-center" elevation="2">
            <v-avatar color="error" variant="tonal" size="76" class="mb-5 elevation-1">
                <v-icon icon="mdi-close-circle-outline" size="48" color="error"></v-icon>
            </v-avatar>
            <h1 class="text-h4 font-weight-black text-slate-900 tracking-tight mb-2">
                Payment Unsuccessful
            </h1>
            <p class="text-body-1 text-medium-emphasis mb-4 max-w-md mx-auto">
                We were unable to process your payment through Stripe. No funds were captured.
            </p>

            <v-alert
                v-if="failureReason"
                type="error"
                variant="tonal"
                density="comfortable"
                class="rounded-xl text-left mb-6 max-w-md mx-auto text-body-2"
            >
                {{ failureReason }}
            </v-alert>

            <div class="d-flex flex-column flex-sm-row justify-center gap-3 mt-6">
                <v-btn
                    color="primary"
                    size="large"
                    class="text-none font-weight-bold rounded-lg px-6"
                    prepend-icon="mdi-credit-card-refresh-outline"
                    :to="{ name: 'public.invoice.pay', params: { token } }"
                >
                    Try Again
                </v-btn>
            </div>
        </v-card>

        <!-- Cancelled State -->
        <v-card v-else-if="state === 'cancelled'" class="result-card pa-8 pa-sm-10 text-center" elevation="2">
            <v-avatar color="grey-darken-1" variant="tonal" size="76" class="mb-5">
                <v-icon icon="mdi-cancel" size="48" color="grey-darken-2"></v-icon>
            </v-avatar>
            <h1 class="text-h4 font-weight-black text-slate-900 tracking-tight mb-2">
                Checkout Cancelled
            </h1>
            <p class="text-body-1 text-medium-emphasis mb-6 max-w-md mx-auto">
                You cancelled the checkout session before completing payment. No charges were made to your account.
            </p>

            <div class="d-flex justify-center gap-3">
                <v-btn
                    color="primary"
                    size="large"
                    class="text-none font-weight-bold rounded-lg px-6"
                    prepend-icon="mdi-arrow-left"
                    :to="{ name: 'public.invoice.pay', params: { token } }"
                >
                    Back to Invoice &amp; Pay
                </v-btn>
            </div>
        </v-card>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRoute } from 'vue-router';
import { usePaymentStore } from '@/stores/payment';
import type { PublicInvoiceData, PublicPaymentStatus } from '@/types/payment';

type ResultState = 'verifying' | 'success' | 'pending' | 'failed' | 'cancelled';

const route = useRoute();
const paymentStore = usePaymentStore();

const token = computed(() => (route.params.token as string) || '');
const paymentIntentId = computed(() => (route.query.payment_intent as string) || (route.query.payment_intent_id as string) || '');
const sessionId = computed(() => (route.query.session_id as string) || '');
const queryStatus = computed(() => (route.query.status as string) || (route.query.redirect_status as string) || '');
const isCancelled = computed(() => queryStatus.value === 'cancelled' || route.query.cancelled === '1');

const state = ref<ResultState>('verifying');
const refreshing = ref(false);
const failureReason = ref<string | null>(null);

const invoiceData = ref<PublicInvoiceData | null>(null);
const paymentInfo = ref<any>(null);
const invoiceNumber = ref('');

// Bounded polling configuration
const pollAttempt = ref(1);
const maxPollAttempts = 5;
const pollIntervalMs = 2500;
let pollTimer: ReturnType<typeof setTimeout> | null = null;

const formattedPaidAt = computed(() => {
    if (!paymentInfo.value?.paid_at) return '';
    try {
        return new Date(paymentInfo.value.paid_at).toLocaleString('en-US', {
            dateStyle: 'medium',
            timeStyle: 'short',
        });
    } catch {
        return paymentInfo.value.paid_at;
    }
});

const handlePrint = () => {
    window.print();
};

const pollPaymentStatus = async () => {
    if (!token.value) {
        state.value = 'failed';
        failureReason.value = 'Invalid payment token.';
        return;
    }

    try {
        const identifier = paymentIntentId.value || sessionId.value || undefined;
        const statusData: PublicPaymentStatus = await paymentStore.fetchPublicPaymentStatus(
            token.value,
            identifier
        );

        invoiceNumber.value = statusData.invoice_number;
        paymentInfo.value = statusData.payment;

        // Also refresh invoice data if available
        if (!invoiceData.value) {
            try {
                invoiceData.value = await paymentStore.fetchPublicInvoice(token.value);
            } catch {
                // Ignore secondary fetch failure
            }
        }

        // 1. Check if backend confirms success
        if (statusData.payment?.status === 'succeeded' || statusData.is_paid) {
            state.value = 'success';
            return;
        }

        // 2. Check if backend reports failure
        if (statusData.payment?.status === 'failed') {
            state.value = 'failed';
            failureReason.value = statusData.payment.failure_reason || 'Card was declined.';
            return;
        }

        // 3. If still pending/processing and attempts remain, poll again
        if (pollAttempt.value < maxPollAttempts) {
            pollAttempt.value++;
            pollTimer = setTimeout(pollPaymentStatus, pollIntervalMs);
        } else {
            // Reached polling limit without final confirmation -> transition to pending state
            state.value = 'pending';
        }
    } catch (err: any) {
        if (pollAttempt.value < maxPollAttempts) {
            pollAttempt.value++;
            pollTimer = setTimeout(pollPaymentStatus, pollIntervalMs);
        } else {
            state.value = 'pending';
        }
    }
};

const handleManualRefresh = async () => {
    refreshing.value = true;
    try {
        const identifier = paymentIntentId.value || sessionId.value || undefined;
        const statusData = await paymentStore.fetchPublicPaymentStatus(
            token.value,
            identifier
        );
        paymentInfo.value = statusData.payment;
        if (statusData.payment?.status === 'succeeded' || statusData.is_paid) {
            state.value = 'success';
        } else if (statusData.payment?.status === 'failed') {
            state.value = 'failed';
            failureReason.value = statusData.payment.failure_reason || 'Card was declined.';
        }
    } finally {
        refreshing.value = false;
    }
};

onMounted(() => {
    // If explicitly cancelled by user
    if (isCancelled.value) {
        state.value = 'cancelled';
        return;
    }

    if (queryStatus.value === 'failed') {
        state.value = 'failed';
        failureReason.value = 'Payment processing failed or was declined.';
        return;
    }

    // Otherwise, always verify with backend. Never trust client parameters alone!
    pollPaymentStatus();
});

onUnmounted(() => {
    if (pollTimer) {
        clearTimeout(pollTimer);
    }
});
</script>

<style scoped>
.payment-result-container {
    max-width: 580px;
    margin: 0 auto;
}

.result-card {
    border-radius: 20px;
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.8);
}

.receipt-card {
    background-color: #f8fafc;
    border-color: #e2e8f0;
}

.max-w-sm {
    max-width: 380px;
}

.max-w-md {
    max-width: 480px;
}

.font-mono {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}

.gap-2 {
    gap: 8px;
}

.gap-3 {
    gap: 12px;
}

@media print {
    .v-btn,
    .v-progress-circular {
        display: none !important;
    }
    .result-card {
        border: none !important;
        box-shadow: none !important;
        padding: 0 !important;
    }
}
</style>
