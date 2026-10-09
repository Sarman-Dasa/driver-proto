<template>
    <div class="public-invoice-container">
        <!-- Top Bar -->
        <header class="public-header">
            <div class="d-flex align-center justify-space-between max-w-content mx-auto px-4 py-3">
                <div class="d-flex align-center gap-2">
                    <v-avatar color="primary" rounded="lg" size="34">
                        <v-icon icon="mdi-shield-check-outline" color="white" size="20"></v-icon>
                    </v-avatar>
                    <span class="text-subtitle-1 font-weight-bold ml-2">BillFlow Secure Pay</span>
                </div>
                <div class="d-flex align-center gap-1 text-caption text-medium-emphasis">
                    <v-icon icon="mdi-lock-outline" size="small" color="success" class="mr-1"></v-icon>
                    <span>Encrypted Payment</span>
                </div>
            </div>
        </header>

        <main class="public-main-content max-w-content mx-auto px-4 py-6 py-md-10">
            <!-- Loading Skeleton -->
            <v-card v-if="loading && !invoice" class="rounded-xl pa-8" elevation="2">
                <v-skeleton-loader type="article, table, actions"></v-skeleton-loader>
            </v-card>

            <!-- Error State / Expired / Invalid -->
            <v-card v-else-if="errorState" class="rounded-xl pa-8 text-center" elevation="2">
                <v-avatar color="error" variant="tonal" size="64" class="mb-4">
                    <v-icon icon="mdi-alert-circle-outline" size="36" color="error"></v-icon>
                </v-avatar>
                <h2 class="text-h5 font-weight-bold mb-2">{{ errorTitle }}</h2>
                <p class="text-body-1 text-medium-emphasis mb-6 max-w-md mx-auto">
                    {{ errorMessage }}
                </p>
                <div class="d-flex justify-center gap-3">
                    <v-btn
                        v-if="canRetry"
                        color="primary"
                        variant="flat"
                        class="text-none px-6 rounded-lg"
                        @click="loadInvoiceData"
                    >
                        Try Again
                    </v-btn>
                </div>
            </v-card>

            <!-- Verified Payment Succeeded State (from redirect or already paid invoice) -->
            <div v-else-if="isPaymentSuccessConfirmed" class="text-center">
                <v-card class="rounded-xl pa-8 max-w-lg mx-auto" elevation="3">
                    <v-avatar color="success" variant="tonal" size="72" class="mb-4">
                        <v-icon icon="mdi-check-circle" size="48" color="success"></v-icon>
                    </v-avatar>
                    <h2 class="text-h5 font-weight-bold text-success mb-1">Payment Successful!</h2>
                    <p class="text-body-2 text-medium-emphasis mb-6">
                        Thank you. Your payment has been received and verified for
                        <strong>Invoice #{{ invoice?.invoice_number }}</strong>.
                    </p>

                    <v-card variant="outlined" class="rounded-lg pa-4 mb-6 text-left bg-surface-variant">
                        <div class="d-flex justify-space-between mb-2 text-caption">
                            <span class="text-medium-emphasis">Issued By:</span>
                            <span class="font-weight-bold">{{ invoice?.company.name }}</span>
                        </div>
                        <div class="d-flex justify-space-between mb-2 text-caption">
                            <span class="text-medium-emphasis">Invoice Total:</span>
                            <span>${{ invoice?.total_amount_decimal.toFixed(2) }} {{ invoice?.currency }}</span>
                        </div>
                        <div class="d-flex justify-space-between mb-2 text-caption">
                            <span class="text-medium-emphasis">Amount Paid:</span>
                            <span class="text-success font-weight-bold">
                                ${{ (paymentStatus?.amount_paid_decimal ?? invoice?.amount_paid_decimal ?? 0).toFixed(2) }} {{ invoice?.currency }}
                            </span>
                        </div>
                        <div v-if="paymentStatus?.payment?.payment_reference" class="d-flex justify-space-between mb-2 text-caption">
                            <span class="text-medium-emphasis">Payment Reference:</span>
                            <span class="font-mono">{{ paymentStatus.payment.payment_reference }}</span>
                        </div>
                        <div class="d-flex justify-space-between text-caption pt-2 border-t">
                            <span class="text-medium-emphasis">Remaining Balance:</span>
                            <span class="font-weight-bold">
                                ${{ (paymentStatus?.balance_due_decimal ?? invoice?.balance_due_decimal ?? 0).toFixed(2) }} {{ invoice?.currency }}
                            </span>
                        </div>
                    </v-card>

                    <v-btn
                        color="primary"
                        variant="tonal"
                        block
                        class="text-none rounded-lg"
                        prepend-icon="mdi-printer"
                        @click="handlePrintReceipt"
                    >
                        Print Payment Confirmation
                    </v-btn>
                </v-card>
            </div>

            <!-- Standard Invoice Payment View -->
            <div v-else-if="invoice">
                <!-- Cancelled return banner -->
                <v-alert
                    v-if="isCancelledReturn"
                    type="warning"
                    variant="tonal"
                    closable
                    class="rounded-xl mb-6"
                    title="Checkout Cancelled"
                    text="Your checkout session was cancelled. No charges were made. You can proceed with payment whenever you are ready."
                ></v-alert>

                <!-- Fully Paid Notice -->
                <v-alert
                    v-if="invoice.status === 'paid' || invoice.balance_due <= 0"
                    type="success"
                    variant="tonal"
                    class="rounded-xl mb-6"
                    icon="mdi-check-circle"
                    title="Invoice Paid in Full"
                    text="This invoice has been fully paid. No further payments are required."
                ></v-alert>

                <v-row>
                    <!-- Left Column: Invoice Details -->
                    <v-col cols="12" md="8">
                        <v-card class="rounded-xl pa-6 pa-md-8 mb-6" elevation="2">
                            <!-- Invoice Header -->
                            <div class="d-flex flex-column flex-sm-row justify-space-between align-start align-sm-center pb-6 border-b gap-4">
                                <div>
                                    <h1 class="text-h5 font-weight-bold mb-1">
                                        Invoice #{{ invoice.invoice_number }}
                                    </h1>
                                    <div class="text-caption text-medium-emphasis">
                                        Issued by <strong class="text-high-emphasis">{{ invoice.company.name }}</strong>
                                    </div>
                                </div>
                                <div class="text-sm-right">
                                    <v-chip
                                        :color="getStatusColor(invoice.status)"
                                        size="small"
                                        class="font-weight-bold text-uppercase"
                                        variant="flat"
                                    >
                                        {{ invoice.status_label }}
                                    </v-chip>
                                </div>
                            </div>

                            <!-- Meta Details Grid -->
                            <v-row class="py-6 border-b text-caption">
                                <v-col cols="6" sm="3">
                                    <div class="text-medium-emphasis mb-1">Issue Date</div>
                                    <div class="font-weight-bold">{{ invoice.issue_date }}</div>
                                </v-col>
                                <v-col cols="6" sm="3">
                                    <div class="text-medium-emphasis mb-1">Due Date</div>
                                    <div class="font-weight-bold">{{ invoice.due_date }}</div>
                                </v-col>
                                <v-col cols="6" sm="3">
                                    <div class="text-medium-emphasis mb-1">Billed To</div>
                                    <div class="font-weight-bold text-truncate">{{ invoice.customer.name }}</div>
                                    <div v-if="invoice.customer.company_name" class="text-medium-emphasis text-truncate">
                                        {{ invoice.customer.company_name }}
                                    </div>
                                </v-col>
                                <v-col cols="6" sm="3">
                                    <div class="text-medium-emphasis mb-1">Customer Email</div>
                                    <div class="text-truncate">{{ invoice.customer.email || '—' }}</div>
                                </v-col>
                            </v-row>

                            <!-- Line Items Table -->
                            <div class="py-4">
                                <h3 class="text-subtitle-2 font-weight-bold mb-3 text-uppercase text-medium-emphasis">
                                    Invoice Items
                                </h3>
                                <v-table density="compact" class="invoice-items-table">
                                    <thead>
                                        <tr>
                                            <th class="text-left">Item Description</th>
                                            <th class="text-center">Qty</th>
                                            <th class="text-right">Price</th>
                                            <th class="text-right">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="item in invoice.items" :key="item.id">
                                            <td>
                                                <div class="font-weight-medium">{{ item.product_name }}</div>
                                                <div v-if="item.description" class="text-caption text-medium-emphasis">
                                                    {{ item.description }}
                                                </div>
                                            </td>
                                            <td class="text-center">{{ item.quantity }} {{ item.unit || '' }}</td>
                                            <td class="text-right">${{ item.unit_price_decimal.toFixed(2) }}</td>
                                            <td class="text-right font-weight-bold">${{ item.total_decimal.toFixed(2) }}</td>
                                        </tr>
                                    </tbody>
                                </v-table>
                            </div>

                            <!-- Notes & Terms -->
                            <div v-if="invoice.notes || invoice.terms" class="pt-4 border-t text-caption text-medium-emphasis">
                                <div v-if="invoice.notes" class="mb-2">
                                    <strong>Notes:</strong> {{ invoice.notes }}
                                </div>
                                <div v-if="invoice.terms">
                                    <strong>Terms:</strong> {{ invoice.terms }}
                                </div>
                            </div>
                        </v-card>
                    </v-col>

                    <!-- Right Column: Payment Card -->
                    <v-col cols="12" md="4">
                        <v-card class="rounded-xl pa-6 sticky-card" elevation="3">
                            <h2 class="text-subtitle-1 font-weight-bold mb-4">Payment Summary</h2>

                            <div class="d-flex justify-space-between mb-2 text-caption">
                                <span class="text-medium-emphasis">Subtotal</span>
                                <span>${{ invoice.subtotal_decimal.toFixed(2) }}</span>
                            </div>

                            <div v-if="invoice.discount_amount > 0" class="d-flex justify-space-between mb-2 text-caption text-success">
                                <span>Discount</span>
                                <span>-${{ invoice.discount_amount_decimal.toFixed(2) }}</span>
                            </div>

                            <div v-if="invoice.tax_amount > 0" class="d-flex justify-space-between mb-2 text-caption">
                                <span class="text-medium-emphasis">Tax</span>
                                <span>+${{ invoice.tax_amount_decimal.toFixed(2) }}</span>
                            </div>

                            <v-divider class="my-3"></v-divider>

                            <div class="d-flex justify-space-between mb-2 text-body-1 font-weight-bold">
                                <span>Total Amount</span>
                                <span>${{ invoice.total_amount_decimal.toFixed(2) }} {{ invoice.currency }}</span>
                            </div>

                            <div v-if="invoice.amount_paid > 0" class="d-flex justify-space-between mb-2 text-caption text-success">
                                <span>Amount Paid</span>
                                <span>-${{ invoice.amount_paid_decimal.toFixed(2) }} {{ invoice.currency }}</span>
                            </div>

                            <div class="balance-due-highlight pa-4 rounded-lg my-4 text-center">
                                <div class="text-caption text-uppercase font-weight-bold mb-1 opacity-80">
                                    Amount Due
                                </div>
                                <div class="text-h4 font-weight-black">
                                    ${{ invoice.balance_due_decimal.toFixed(2) }}
                                    <span class="text-caption font-weight-bold">{{ invoice.currency }}</span>
                                </div>
                            </div>

                            <!-- Pay Action Button -->
                            <div v-if="invoice.is_payable && invoice.balance_due > 0" class="mt-4">
                                <v-btn
                                    color="primary"
                                    block
                                    size="large"
                                    class="text-none font-weight-bold rounded-lg mb-3"
                                    prepend-icon="mdi-credit-card-outline"
                                    :loading="checkoutLoading"
                                    @click="handleInitiatePayment"
                                >
                                    Pay Now with Stripe
                                </v-btn>

                                <div class="d-flex align-center justify-center gap-1 text-caption text-medium-emphasis">
                                    <v-icon icon="mdi-shield-lock" size="small" color="primary" class="mr-1"></v-icon>
                                    <span>Routed to {{ invoice.company.name }} via Stripe</span>
                                </div>
                            </div>

                            <!-- Payment Disabled States -->
                            <div v-else-if="!invoice.stripe_connect_ready" class="mt-4">
                                <v-alert
                                    type="info"
                                    variant="tonal"
                                    density="compact"
                                    class="text-caption rounded-lg"
                                >
                                    Online payment is not yet configured for this workspace. Please contact {{ invoice.company.name }}.
                                </v-alert>
                            </div>
                            <div v-else-if="invoice.balance_due <= 0" class="mt-4 text-center">
                                <v-btn
                                    color="success"
                                    variant="tonal"
                                    block
                                    disabled
                                    class="text-none rounded-lg"
                                    prepend-icon="mdi-check-circle"
                                >
                                    Invoice Fully Paid
                                </v-btn>
                            </div>
                            <div v-else class="mt-4">
                                <v-alert
                                    type="warning"
                                    variant="tonal"
                                    density="compact"
                                    class="text-caption rounded-lg"
                                >
                                    This invoice is currently not eligible for online payment.
                                </v-alert>
                            </div>

                            <v-divider class="my-4"></v-divider>

                            <!-- Issuer Contacts -->
                            <div class="text-caption text-medium-emphasis">
                                <div class="font-weight-medium mb-1">Questions regarding this invoice?</div>
                                <div>Contact: <strong class="text-high-emphasis">{{ invoice.company.email || invoice.company.name }}</strong></div>
                            </div>
                        </v-card>
                    </v-col>
                </v-row>
            </div>
        </main>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { usePaymentStore } from '@/stores/payment';
import type { PublicInvoiceData, PublicPaymentStatus } from '@/types/payment';

const route = useRoute();
const paymentStore = usePaymentStore();

const token = computed(() => (route.params.token as string) || '');
const isSuccessRedirect = computed(() => route.query.success === '1');
const isCancelledReturn = computed(() => route.query.cancelled === '1');
const sessionId = computed(() => (route.query.session_id as string) || '');

const loading = ref(true);
const checkoutLoading = ref(false);
const errorState = ref(false);
const errorTitle = ref('');
const errorMessage = ref('');
const canRetry = ref(true);

const invoice = ref<PublicInvoiceData | null>(null);
const paymentStatus = ref<PublicPaymentStatus | null>(null);

const isPaymentSuccessConfirmed = computed(() => {
    if (isSuccessRedirect.value && paymentStatus.value?.payment?.status === 'succeeded') {
        return true;
    }
    if (isSuccessRedirect.value && paymentStatus.value?.is_paid) {
        return true;
    }
    return false;
});

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

        // If returned from Stripe Checkout with session_id, verify status with backend
        if (isSuccessRedirect.value && sessionId.value) {
            await verifyPaymentStatus();
        }
    } catch (err: any) {
        errorState.value = true;
        errorTitle.value = 'Invoice Not Found or Expired';
        errorMessage.value = err.message || 'Unable to retrieve invoice information. The payment link may have expired or been revoked.';
    } finally {
        loading.value = false;
    }
};

const verifyPaymentStatus = async () => {
    try {
        const statusData = await paymentStore.fetchPublicPaymentStatus(token.value, sessionId.value);
        paymentStatus.value = statusData;

        // If invoice is now paid or updated, refresh local state
        if (invoice.value) {
            invoice.value.status = statusData.invoice_status;
            invoice.value.status_label = statusData.invoice_status_label;
            invoice.value.balance_due = statusData.balance_due;
            invoice.value.balance_due_decimal = statusData.balance_due_decimal;
            invoice.value.amount_paid = statusData.amount_paid;
            invoice.value.amount_paid_decimal = statusData.amount_paid_decimal;
        }
    } catch (err) {
        console.error('Failed to verify payment status:', err);
    }
};

const handleInitiatePayment = async () => {
    if (!token.value || checkoutLoading.value) return;

    checkoutLoading.value = true;
    try {
        const result = await paymentStore.createCheckoutSession(token.value);
        if (result?.checkout_url) {
            window.location.href = result.checkout_url;
        } else {
            throw new Error('No checkout URL returned from payment server.');
        }
    } catch (err: any) {
        alert(err.message || 'Failed to initialize payment session. Please try again.');
        checkoutLoading.value = false;
    }
};

const handlePrintReceipt = () => {
    window.print();
};

onMounted(() => {
    loadInvoiceData();
});
</script>

<style scoped>
.public-invoice-container {
    min-height: 100vh;
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    color: #1e293b;
}

.public-header {
    background-color: #ffffff;
    border-bottom: 1px solid #e2e8f0;
}

.max-w-content {
    max-width: 1040px;
}

.max-w-md {
    max-width: 480px;
}

.max-w-lg {
    max-width: 580px;
}

.sticky-card {
    position: sticky;
    top: 24px;
}

.balance-due-highlight {
    background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
    color: #ffffff;
}

.font-mono {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}

.invoice-items-table th {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #64748b;
}

@media print {
    .public-header,
    .v-btn,
    .sticky-card {
        display: none !important;
    }
    .public-invoice-container {
        background: #ffffff !important;
    }
}
</style>
