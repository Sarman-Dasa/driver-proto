<template>
    <div class="public-invoice-view">
        <!-- Loading State Skeleton -->
        <v-card v-if="loading && !invoice" class="rounded-xl pa-8" elevation="1">
            <v-skeleton-loader type="article, table, actions"></v-skeleton-loader>
        </v-card>

        <!-- Error State / Expired / Invalid -->
        <v-card v-else-if="errorState" class="rounded-xl pa-8 pa-sm-10 text-center border" elevation="2">
            <v-avatar color="error" variant="tonal" size="72" class="mb-4">
                <v-icon icon="mdi-alert-circle-outline" size="40" color="error"></v-icon>
            </v-avatar>
            <h1 class="text-h5 font-weight-bold mb-2">{{ errorTitle }}</h1>
            <p class="text-body-1 text-medium-emphasis mb-6 max-w-md mx-auto">
                {{ errorMessage }}
            </p>
            <div class="d-flex justify-center gap-3">
                <v-btn
                    v-if="canRetry"
                    color="primary"
                    variant="flat"
                    class="text-none px-6 rounded-lg font-weight-medium"
                    prepend-icon="mdi-refresh"
                    @click="loadInvoiceData"
                >
                    Try Again
                </v-btn>
            </div>
        </v-card>

        <!-- Main Invoice Content -->
        <div v-else-if="invoice">
            <!-- Fully Paid Alert Banner -->
            <v-alert
                v-if="invoice.status === 'paid' || invoice.balance_due <= 0"
                type="success"
                variant="tonal"
                class="rounded-xl mb-6 elevation-1"
                icon="mdi-check-circle"
                title="Invoice Fully Paid"
                text="This invoice has been settled in full. Thank you for your business!"
            >
                <template #append>
                    <v-btn
                        size="small"
                        color="success"
                        variant="outlined"
                        class="text-none rounded-lg"
                        prepend-icon="mdi-receipt-text-outline"
                        :to="{ name: 'public.invoice.result', params: { token }, query: { status: 'success' } }"
                    >
                        View Receipt
                    </v-btn>
                </template>
            </v-alert>

            <v-row>
                <!-- Left Column: Detailed Invoice Document -->
                <v-col cols="12" md="8">
                    <v-card class="invoice-document-card rounded-xl pa-6 pa-md-8 mb-6" elevation="1">
                        <!-- Invoice Header -->
                        <div class="d-flex flex-column flex-sm-row justify-space-between align-start align-sm-center pb-6 border-b gap-4">
                            <div>
                                <div class="text-overline text-primary font-weight-bold tracking-wider mb-1">
                                    INVOICE
                                </div>
                                <h1 class="text-h4 font-weight-bold text-slate-900 tracking-tight">
                                    #{{ invoice.invoice_number }}
                                </h1>
                                <div class="text-body-2 text-medium-emphasis mt-1">
                                    Issued by <strong class="text-high-emphasis">{{ invoice.company.name }}</strong>
                                </div>
                            </div>
                            <div class="text-sm-right">
                                <v-chip
                                    :color="getStatusColor(invoice.status)"
                                    size="small"
                                    class="font-weight-bold text-uppercase px-3"
                                    variant="flat"
                                >
                                    {{ invoice.status_label }}
                                </v-chip>
                            </div>
                        </div>

                        <!-- Invoice Metadata Grid -->
                        <v-row class="py-6 border-b text-caption">
                            <v-col cols="6" sm="3">
                                <div class="text-medium-emphasis text-uppercase font-weight-bold mb-1">Issue Date</div>
                                <div class="font-weight-bold text-body-2">{{ invoice.issue_date }}</div>
                            </v-col>
                            <v-col cols="6" sm="3">
                                <div class="text-medium-emphasis text-uppercase font-weight-bold mb-1">Due Date</div>
                                <div class="font-weight-bold text-body-2">{{ invoice.due_date }}</div>
                            </v-col>
                            <v-col cols="6" sm="3">
                                <div class="text-medium-emphasis text-uppercase font-weight-bold mb-1">Billed To</div>
                                <div class="font-weight-bold text-body-2 text-truncate">{{ invoice.customer.name }}</div>
                                <div v-if="invoice.customer.company_name" class="text-medium-emphasis text-truncate">
                                    {{ invoice.customer.company_name }}
                                </div>
                            </v-col>
                            <v-col cols="6" sm="3">
                                <div class="text-medium-emphasis text-uppercase font-weight-bold mb-1">Customer Email</div>
                                <div class="text-body-2 text-truncate">{{ invoice.customer.email || '—' }}</div>
                            </v-col>
                        </v-row>

                        <!-- Line Items Table -->
                        <div class="py-6">
                            <h3 class="text-caption font-weight-bold mb-3 text-uppercase text-medium-emphasis tracking-wider">
                                Invoice Items &amp; Services
                            </h3>
                            <v-table density="comfortable" class="invoice-items-table border rounded-lg">
                                <thead>
                                    <tr class="bg-surface-variant">
                                        <th class="text-left font-weight-bold">Description</th>
                                        <th class="text-center font-weight-bold">Qty</th>
                                        <th class="text-right font-weight-bold">Unit Price</th>
                                        <th class="text-right font-weight-bold">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="item in invoice.items" :key="item.id">
                                        <td class="py-3">
                                            <div class="font-weight-medium text-body-2">{{ item.product_name }}</div>
                                            <div v-if="item.description" class="text-caption text-medium-emphasis mt-1">
                                                {{ item.description }}
                                            </div>
                                        </td>
                                        <td class="text-center py-3 text-body-2">
                                            {{ item.quantity }} {{ item.unit || '' }}
                                        </td>
                                        <td class="text-right py-3 text-body-2 font-mono">
                                            ${{ item.unit_price_decimal.toFixed(2) }}
                                        </td>
                                        <td class="text-right py-3 text-body-2 font-weight-bold font-mono">
                                            ${{ item.total_decimal.toFixed(2) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </v-table>
                        </div>

                        <!-- Notes & Terms -->
                        <div v-if="invoice.notes || invoice.terms" class="pt-4 border-t text-caption text-medium-emphasis">
                            <div v-if="invoice.notes" class="mb-3">
                                <strong class="text-high-emphasis">Notes:</strong> {{ invoice.notes }}
                            </div>
                            <div v-if="invoice.terms">
                                <strong class="text-high-emphasis">Terms &amp; Conditions:</strong> {{ invoice.terms }}
                            </div>
                        </div>
                    </v-card>
                </v-col>

                <!-- Right Column: Sticky Payment Breakdown & Pay Action -->
                <v-col cols="12" md="4">
                    <v-card class="payment-action-card rounded-xl pa-6 sticky-card" elevation="2">
                        <div class="d-flex align-center justify-space-between mb-4">
                            <h2 class="text-subtitle-1 font-weight-bold">Payment Summary</h2>
                            <v-icon icon="mdi-shield-check-outline" color="primary" size="20"></v-icon>
                        </div>

                        <!-- Subtotal, Taxes, Discounts -->
                        <div class="d-flex justify-space-between mb-2 text-body-2">
                            <span class="text-medium-emphasis">Subtotal</span>
                            <span class="font-mono">${{ invoice.subtotal_decimal.toFixed(2) }}</span>
                        </div>

                        <div v-if="invoice.discount_amount > 0" class="d-flex justify-space-between mb-2 text-body-2 text-success">
                            <span>Discount</span>
                            <span class="font-mono">-${{ invoice.discount_amount_decimal.toFixed(2) }}</span>
                        </div>

                        <div v-if="invoice.tax_amount > 0" class="d-flex justify-space-between mb-2 text-body-2">
                            <span class="text-medium-emphasis">Taxes</span>
                            <span class="font-mono">+${{ invoice.tax_amount_decimal.toFixed(2) }}</span>
                        </div>

                        <v-divider class="my-3"></v-divider>

                        <div class="d-flex justify-space-between mb-2 text-body-1 font-weight-bold">
                            <span>Invoice Total</span>
                            <span class="font-mono">${{ invoice.total_amount_decimal.toFixed(2) }} {{ invoice.currency }}</span>
                        </div>

                        <div v-if="invoice.amount_paid > 0" class="d-flex justify-space-between mb-2 text-body-2 text-success">
                            <span>Amount Paid</span>
                            <span class="font-mono">-${{ invoice.amount_paid_decimal.toFixed(2) }} {{ invoice.currency }}</span>
                        </div>

                        <!-- Amount Due Highlight Box -->
                        <div class="amount-due-box pa-4 rounded-xl my-4 text-center">
                            <div class="text-caption text-uppercase font-weight-bold mb-1 opacity-90">
                                Remaining Balance Due
                            </div>
                            <div class="text-h4 font-weight-black font-mono">
                                ${{ invoice.balance_due_decimal.toFixed(2) }}
                                <span class="text-caption font-weight-bold">{{ invoice.currency }}</span>
                            </div>
                        </div>

                        <!-- In-Application Stripe Elements Payment Form -->
                        <div v-if="invoice.is_payable && invoice.balance_due > 0" class="mt-4">
                            <div class="custom-payment-form">
                                <div class="d-flex align-center justify-space-between mb-3">
                                    <span class="text-caption font-weight-bold text-uppercase text-medium-emphasis tracking-wider">
                                        Card &amp; Payment Details
                                    </span>
                                    <v-icon icon="mdi-credit-card-outline" size="18" color="primary"></v-icon>
                                </div>

                                <!-- Elements Loading Skeleton -->
                                <div v-if="elementsLoading" class="elements-loading-box pa-6 rounded-xl border text-center mb-4">
                                    <v-progress-circular indeterminate color="primary" size="36" width="3" class="mb-3"></v-progress-circular>
                                    <div class="text-caption text-medium-emphasis">Initializing secure payment elements...</div>
                                </div>

                                <!-- Stripe Elements Mount Target Container -->
                                <div v-show="!elementsLoading && !elementsError" class="stripe-elements-wrapper mb-4">
                                    <div id="payment-element" class="stripe-element-mount rounded-xl"></div>
                                </div>

                                <!-- Elements Init Error Alert -->
                                <v-alert
                                    v-if="elementsError"
                                    type="error"
                                    variant="tonal"
                                    density="comfortable"
                                    class="rounded-xl mb-4 text-body-2"
                                >
                                    {{ elementsError }}
                                    <template #append>
                                        <v-btn
                                            size="x-small"
                                            variant="flat"
                                            color="error"
                                            class="text-none"
                                            @click="initializePaymentIntentAndElements"
                                        >
                                            Retry
                                        </v-btn>
                                    </template>
                                </v-alert>

                                <!-- Validation / Card Error Message -->
                                <v-alert
                                    v-if="cardErrorMessage"
                                    type="error"
                                    variant="tonal"
                                    density="comfortable"
                                    class="rounded-xl mb-4 text-body-2"
                                    closable
                                    @click:close="cardErrorMessage = ''"
                                >
                                    {{ cardErrorMessage }}
                                </v-alert>

                                <!-- Custom Pay Button -->
                                <v-btn
                                    color="primary"
                                    block
                                    size="x-large"
                                    class="text-none font-weight-bold rounded-xl mb-3 pay-btn elevation-2"
                                    prepend-icon="mdi-lock"
                                    :loading="submittingPayment || elementsLoading"
                                    :disabled="!elementsReady || submittingPayment || elementsLoading"
                                    @click="handleSubmitPayment"
                                >
                                    Pay ${{ invoice.balance_due_decimal.toFixed(2) }} {{ invoice.currency }}
                                </v-btn>

                                <!-- Stripe Trust & Encryption Note -->
                                <div class="stripe-trust-note pa-3 rounded-lg bg-surface-variant text-caption text-medium-emphasis">
                                    <div class="d-flex align-center gap-1 font-weight-medium text-slate-800 mb-1">
                                        <v-icon icon="mdi-shield-check" size="14" color="success" class="mr-1"></v-icon>
                                        <span>256-Bit SSL Encrypted Payment</span>
                                    </div>
                                    <div class="lh-sm">
                                        Card details are securely processed directly by Stripe on behalf of <strong>{{ invoice.company.name }}</strong>. Your sensitive payment data is never stored on our servers.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Ineligible / Disabled Notice -->
                        <div v-else-if="!invoice.stripe_connect_ready" class="mt-4">
                            <v-alert
                                type="info"
                                variant="tonal"
                                density="comfortable"
                                class="text-body-2 rounded-lg"
                                icon="mdi-information-outline"
                            >
                                Online credit card payment is currently being configured for this merchant. Please contact <strong>{{ invoice.company.name }}</strong> for alternative payment arrangements.
                            </v-alert>
                        </div>
                        <div v-else-if="invoice.balance_due <= 0" class="mt-4 text-center">
                            <v-btn
                                color="success"
                                variant="tonal"
                                block
                                size="large"
                                disabled
                                class="text-none rounded-xl"
                                prepend-icon="mdi-check-circle"
                            >
                                Invoice Settled in Full
                            </v-btn>
                        </div>
                        <div v-else class="mt-4">
                            <v-alert
                                type="warning"
                                variant="tonal"
                                density="comfortable"
                                class="text-body-2 rounded-lg"
                            >
                                This invoice is not currently eligible for online payment.
                            </v-alert>
                        </div>

                        <v-divider class="my-4"></v-divider>

                        <!-- Merchant Contact -->
                        <div class="text-caption text-medium-emphasis">
                            <div class="font-weight-medium mb-1">Questions regarding this invoice?</div>
                            <div>Contact: <strong class="text-high-emphasis">{{ invoice.company.email || invoice.company.name }}</strong></div>
                        </div>
                    </v-card>
                </v-col>
            </v-row>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { loadStripe, type Stripe, type StripeElements, type StripePaymentElement } from '@stripe/stripe-js';
import { usePaymentStore } from '@/stores/payment';
import type { PublicInvoiceData } from '@/types/payment';

const route = useRoute();
const router = useRouter();
const paymentStore = usePaymentStore();

const token = computed(() => (route.params.token as string) || '');

const loading = ref(true);
const errorState = ref(false);
const errorTitle = ref('');
const errorMessage = ref('');
const canRetry = ref(true);

const invoice = ref<PublicInvoiceData | null>(null);

// Stripe Elements custom state
let stripe: Stripe | null = null;
let elements: StripeElements | null = null;
let paymentElement: StripePaymentElement | null = null;

const elementsLoading = ref(false);
const elementsReady = ref(false);
const elementsError = ref<string | null>(null);
const cardErrorMessage = ref<string | null>(null);
const submittingPayment = ref(false);

const getStatusColor = (status: string): string => {
    switch (status) {
        case 'paid':
            return 'success';
        case 'partially_paid':
            return 'info';
        case 'sent':
            return 'primary';
        case 'overdue':
            return 'error';
        case 'cancelled':
            return 'grey';
        default:
            return 'default';
    }
};

const loadInvoiceData = async () => {
    if (!token.value) {
        errorState.value = true;
        errorTitle.value = 'Invalid Payment Link';
        errorMessage.value = 'No payment token provided in the URL.';
        canRetry.value = false;
        loading.value = false;
        return;
    }

    loading.value = true;
    errorState.value = false;

    try {
        const data = await paymentStore.fetchPublicInvoice(token.value);
        invoice.value = data;
    } catch (err: any) {
        errorState.value = true;
        errorTitle.value = 'Invoice Not Found or Expired';
        errorMessage.value = err.message || 'Unable to retrieve invoice information. The payment link may have expired or been revoked.';
    } finally {
        loading.value = false;
    }
};

const initializePaymentIntentAndElements = async () => {
    if (!token.value || !invoice.value || !invoice.value.is_payable || invoice.value.balance_due <= 0) {
        return;
    }

    elementsLoading.value = true;
    elementsReady.value = false;
    elementsError.value = null;
    cardErrorMessage.value = '';

    try {
        const intentData = await paymentStore.createPaymentIntent(token.value);
        const publishableKey = intentData.publishable_key || invoice.value.stripe_publishable_key;
        const stripeAccountId = intentData.stripe_account_id || invoice.value.stripe_account_id;

        if (!publishableKey) {
            throw new Error('Stripe publishable key is not configured.');
        }

        stripe = await loadStripe(
            publishableKey,
            stripeAccountId ? { stripeAccount: stripeAccountId } : undefined
        );

        if (!stripe) {
            throw new Error('Failed to load Stripe SDK.');
        }

        elements = stripe.elements({
            clientSecret: intentData.client_secret,
            appearance: {
                theme: 'stripe',
                variables: {
                    colorPrimary: '#4f46e5',
                    colorBackground: '#ffffff',
                    colorText: '#1e293b',
                    colorDanger: '#ef4444',
                    fontFamily: 'Inter, system-ui, -apple-system, sans-serif',
                    borderRadius: '8px',
                    spacingUnit: '4px',
                },
            },
        });

        paymentElement = elements.create('payment', {
            layout: 'tabs',
        });

        paymentElement.on('ready', () => {
            elementsLoading.value = false;
            elementsReady.value = true;
        });

        paymentElement.on('change', (event: any) => {
            if (event.error) {
                cardErrorMessage.value = event.error.message;
            } else {
                cardErrorMessage.value = '';
            }
        });

        await nextTick();
        paymentElement.mount('#payment-element');
    } catch (err: any) {
        elementsLoading.value = false;
        elementsError.value = err.message || 'Unable to initialize secure payment fields.';
    }
};

const handleSubmitPayment = async () => {
    if (!stripe || !elements || submittingPayment.value) return;

    submittingPayment.value = true;
    cardErrorMessage.value = '';

    const returnUrl = `${window.location.origin}/pay/${token.value}/result`;

    try {
        const { error, paymentIntent } = await stripe.confirmPayment({
            elements,
            confirmParams: {
                return_url: returnUrl,
                receipt_email: invoice.value?.customer?.email || undefined,
            },
            redirect: 'if_required',
        });

        if (error) {
            if (error.type === 'card_error' || error.type === 'validation_error') {
                cardErrorMessage.value = error.message || 'Payment details could not be validated.';
            } else {
                cardErrorMessage.value = error.message || 'An unexpected error occurred during payment processing.';
            }
            submittingPayment.value = false;
            return;
        }

        if (paymentIntent && (paymentIntent.status === 'succeeded' || paymentIntent.status === 'processing')) {
            router.push({
                name: 'public.invoice.result',
                params: { token: token.value },
                query: {
                    payment_intent: paymentIntent.id,
                    payment_intent_client_secret: paymentIntent.client_secret,
                    redirect_status: paymentIntent.status,
                },
            });
        }
    } catch (err: any) {
        cardErrorMessage.value = err.message || 'Payment confirmation failed.';
        submittingPayment.value = false;
    }
};

onMounted(async () => {
    // If arriving with Stripe session_id, payment_intent, or redirect status, forward to result view
    if (
        route.query.payment_intent ||
        route.query.session_id ||
        route.query.redirect_status ||
        route.query.status ||
        route.query.success === '1' ||
        route.query.cancelled === '1'
    ) {
        router.replace({
            name: 'public.invoice.result',
            params: { token: token.value },
            query: route.query,
        });
        return;
    }

    await loadInvoiceData();

    if (invoice.value?.is_payable && invoice.value.balance_due > 0) {
        await initializePaymentIntentAndElements();
    }
});

onUnmounted(() => {
    if (paymentElement) {
        paymentElement.destroy();
    }
});
</script>

<style scoped>
.public-invoice-view {
    width: 100%;
}

.invoice-document-card {
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.8);
}

.payment-action-card {
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.8);
}

.sticky-card {
    position: sticky;
    top: 24px;
}

.amount-due-box {
    background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
    color: #ffffff;
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2);
}

.font-mono {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}

.pay-btn {
    letter-spacing: 0.3px;
}

.stripe-elements-wrapper {
    min-height: 120px;
}

.elements-loading-box {
    background-color: #f8fafc;
    border-color: #e2e8f0;
}

.stripe-trust-note {
    border: 1px solid #e2e8f0;
}

.lh-sm {
    line-height: 1.35;
}

.gap-1 {
    gap: 4px;
}

.gap-3 {
    gap: 12px;
}

.gap-4 {
    gap: 16px;
}

.invoice-items-table th {
    font-size: 11px;
    letter-spacing: 0.5px;
    color: #64748b;
}

@media print {
    .payment-action-card,
    .v-alert,
    .v-btn {
        display: none !important;
    }
    .invoice-document-card {
        border: none !important;
        box-shadow: none !important;
        padding: 0 !important;
    }
}
</style>
