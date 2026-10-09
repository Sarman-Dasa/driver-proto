<template>
    <div class="payments-view">
        <!-- Header Section -->
        <div class="d-flex flex-column flex-sm-row justify-space-between align-start align-sm-center mb-6 gap-4">
            <div>
                <h1 class="text-h4 font-weight-bold">Customer Payments</h1>
                <p class="text-body-2 text-medium-emphasis">
                    Monitor online customer payments collected through your connected Stripe account.
                </p>
            </div>
            <div class="d-flex gap-2">
                <v-btn
                    variant="tonal"
                    color="primary"
                    class="text-none rounded-lg"
                    prepend-icon="mdi-refresh"
                    :loading="loading"
                    @click="loadPayments"
                >
                    Refresh
                </v-btn>
                <v-btn
                    v-if="permissionsStore.can('invoices.view')"
                    variant="flat"
                    color="primary"
                    class="text-none rounded-lg"
                    prepend-icon="mdi-file-document-outline"
                    :to="{ name: 'invoices' }"
                >
                    View Invoices
                </v-btn>
            </div>
        </div>

        <!-- Filter Bar -->
        <v-card class="rounded-xl pa-4 mb-6" elevation="1">
            <v-row dense align="center">
                <v-col cols="12" sm="5" md="4">
                    <v-text-field
                        v-model="search"
                        prepend-inner-icon="mdi-magnify"
                        placeholder="Search reference, invoice, or customer..."
                        density="compact"
                        variant="outlined"
                        hide-details
                        clearable
                        @update:model-value="handleSearch"
                    ></v-text-field>
                </v-col>
                <v-col cols="12" sm="4" md="3">
                    <v-select
                        v-model="selectedStatus"
                        :items="statusOptions"
                        item-title="title"
                        item-value="value"
                        label="Payment Status"
                        density="compact"
                        variant="outlined"
                        hide-details
                        clearable
                        @update:model-value="loadPayments"
                    ></v-select>
                </v-col>
            </v-row>
        </v-card>

        <!-- Payments Table -->
        <v-card class="rounded-xl" elevation="1">
            <v-table density="comfortable" hover>
                <thead>
                    <tr>
                        <th class="text-left font-weight-bold">Payment Reference</th>
                        <th class="text-left font-weight-bold">Invoice</th>
                        <th class="text-left font-weight-bold">Customer</th>
                        <th class="text-right font-weight-bold">Amount</th>
                        <th class="text-center font-weight-bold">Status</th>
                        <th class="text-left font-weight-bold">Date</th>
                        <th class="text-center font-weight-bold">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Loading state -->
                    <tr v-if="loading && payments.length === 0">
                        <td colspan="7" class="text-center py-8">
                            <v-progress-circular indeterminate color="primary"></v-progress-circular>
                            <div class="text-caption text-medium-emphasis mt-2">Loading customer payments...</div>
                        </td>
                    </tr>

                    <!-- Empty state -->
                    <tr v-else-if="payments.length === 0">
                        <td colspan="7" class="text-center py-10">
                            <v-avatar color="primary" variant="tonal" size="56" class="mb-3">
                                <v-icon icon="mdi-cash-multiple" size="32" color="primary"></v-icon>
                            </v-avatar>
                            <h3 class="text-subtitle-1 font-weight-bold">No Payments Found</h3>
                            <p class="text-caption text-medium-emphasis mb-4">
                                {{ search || selectedStatus ? 'No payments matched your filter criteria.' : 'When customers pay your invoices online, payments will appear here.' }}
                            </p>
                            <v-btn
                                v-if="search || selectedStatus"
                                variant="text"
                                size="small"
                                color="primary"
                                @click="resetFilters"
                            >
                                Clear Filters
                            </v-btn>
                        </td>
                    </tr>

                    <!-- Data Rows -->
                    <tr v-for="payment in payments" :key="payment.id">
                        <td>
                            <div class="font-mono font-weight-medium text-caption">{{ payment.payment_reference }}</div>
                            <div v-if="payment.payment_method_type" class="text-caption text-medium-emphasis">
                                {{ payment.payment_method_type }}
                            </div>
                        </td>
                        <td>
                            <router-link
                                v-if="payment.invoice_id"
                                :to="{ name: 'invoices.detail', params: { invoice: payment.invoice_id } }"
                                class="text-primary font-weight-bold text-decoration-none"
                            >
                                {{ payment.invoice_number || `#${payment.invoice_id}` }}
                            </router-link>
                            <span v-else>—</span>
                        </td>
                        <td>
                            <div class="font-weight-medium">{{ payment.customer_name || 'Customer' }}</div>
                            <div v-if="payment.customer_email" class="text-caption text-medium-emphasis">
                                {{ payment.customer_email }}
                            </div>
                        </td>
                        <td class="text-right font-weight-bold">
                            {{ payment.formatted_amount }}
                        </td>
                        <td class="text-center">
                            <v-chip
                                :color="getStatusColor(payment.status)"
                                size="small"
                                class="font-weight-bold"
                                variant="tonal"
                            >
                                {{ payment.status_label }}
                            </v-chip>
                        </td>
                        <td class="text-caption text-medium-emphasis">
                            {{ formatDate(payment.paid_at || payment.created_at) }}
                        </td>
                        <td class="text-center">
                            <v-btn
                                icon="mdi-eye-outline"
                                variant="text"
                                size="small"
                                color="primary"
                                @click="viewPaymentDetails(payment)"
                            >
                                <v-icon icon="mdi-eye-outline"></v-icon>
                                <v-tooltip activator="parent" location="top">View Details</v-tooltip>
                            </v-btn>
                        </td>
                    </tr>
                </tbody>
            </v-table>

            <!-- Pagination -->
            <div v-if="lastPage > 1" class="d-flex justify-space-between align-center px-4 py-3 border-t">
                <span class="text-caption text-medium-emphasis">
                    Showing page {{ currentPage }} of {{ lastPage }} ({{ totalPayments }} total)
                </span>
                <v-pagination
                    v-model="currentPage"
                    :length="lastPage"
                    total-visible="5"
                    density="compact"
                    @update:model-value="handlePageChange"
                ></v-pagination>
            </div>
        </v-card>

        <!-- Payment Detail Dialog -->
        <v-dialog v-model="detailDialog" max-width="540">
            <v-card v-if="selectedPayment" class="rounded-xl pa-6">
                <div class="d-flex justify-space-between align-center mb-4">
                    <h2 class="text-h6 font-weight-bold">Payment Details</h2>
                    <v-chip
                        :color="getStatusColor(selectedPayment.status)"
                        size="small"
                        class="font-weight-bold"
                        variant="tonal"
                    >
                        {{ selectedPayment.status_label }}
                    </v-chip>
                </div>

                <v-list density="compact" class="bg-surface-variant rounded-lg pa-2 mb-4">
                    <v-list-item>
                        <template #title><span class="text-caption text-medium-emphasis">Payment Reference</span></template>
                        <template #subtitle><span class="font-mono font-weight-bold">{{ selectedPayment.payment_reference }}</span></template>
                    </v-list-item>
                    <v-list-item>
                        <template #title><span class="text-caption text-medium-emphasis">Amount</span></template>
                        <template #subtitle><span class="text-h6 font-weight-black text-primary">{{ selectedPayment.formatted_amount }}</span></template>
                    </v-list-item>
                    <v-list-item v-if="selectedPayment.invoice_number">
                        <template #title><span class="text-caption text-medium-emphasis">Invoice</span></template>
                        <template #subtitle><span class="font-weight-bold">{{ selectedPayment.invoice_number }}</span></template>
                    </v-list-item>
                    <v-list-item v-if="selectedPayment.customer_name">
                        <template #title><span class="text-caption text-medium-emphasis">Customer</span></template>
                        <template #subtitle>{{ selectedPayment.customer_name }} ({{ selectedPayment.customer_email || 'No email' }})</template>
                    </v-list-item>
                    <v-list-item v-if="selectedPayment.stripe_checkout_session_id">
                        <template #title><span class="text-caption text-medium-emphasis">Stripe Session ID</span></template>
                        <template #subtitle><span class="font-mono text-caption text-truncate">{{ selectedPayment.stripe_checkout_session_id }}</span></template>
                    </v-list-item>
                    <v-list-item v-if="selectedPayment.stripe_payment_intent_id">
                        <template #title><span class="text-caption text-medium-emphasis">Stripe PaymentIntent ID</span></template>
                        <template #subtitle><span class="font-mono text-caption text-truncate">{{ selectedPayment.stripe_payment_intent_id }}</span></template>
                    </v-list-item>
                    <v-list-item v-if="selectedPayment.paid_at">
                        <template #title><span class="text-caption text-medium-emphasis">Paid Timestamp</span></template>
                        <template #subtitle>{{ formatDate(selectedPayment.paid_at) }}</template>
                    </v-list-item>
                    <v-list-item v-if="selectedPayment.failure_reason">
                        <template #title><span class="text-caption text-error">Failure Reason</span></template>
                        <template #subtitle><span class="text-error">{{ selectedPayment.failure_reason }}</span></template>
                    </v-list-item>
                </v-list>

                <div class="d-flex justify-end gap-2">
                    <v-btn
                        variant="tonal"
                        color="secondary"
                        class="text-none rounded-lg"
                        @click="detailDialog = false"
                    >
                        Close
                    </v-btn>
                </div>
            </v-card>
        </v-dialog>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useCompanyStore } from '@/stores/company';
import { usePermissionsStore } from '@/stores/permissions';
import { usePaymentStore } from '@/stores/payment';
import type { PaymentRecord } from '@/types/payment';

const companyStore = useCompanyStore();
const permissionsStore = usePermissionsStore();
const paymentStore = usePaymentStore();

const search = ref('');
const selectedStatus = ref<string | null>(null);
const loading = ref(false);
const currentPage = ref(1);
const lastPage = ref(1);
const totalPayments = ref(0);
const payments = ref<PaymentRecord[]>([]);

const detailDialog = ref(false);
const selectedPayment = ref<PaymentRecord | null>(null);

const statusOptions = [
    { title: 'All Statuses', value: null },
    { title: 'Succeeded', value: 'succeeded' },
    { title: 'Processing', value: 'processing' },
    { title: 'Pending', value: 'pending' },
    { title: 'Failed', value: 'failed' },
    { title: 'Cancelled', value: 'cancelled' },
    { title: 'Refunded', value: 'refunded' },
];

const getStatusColor = (status: string): string => {
    switch (status) {
        case 'succeeded':
            return 'success';
        case 'processing':
            return 'info';
        case 'pending':
            return 'warning';
        case 'failed':
            return 'error';
        case 'cancelled':
            return 'grey';
        case 'refunded':
            return 'purple';
        default:
            return 'default';
    }
};

const formatDate = (isoString?: string | null): string => {
    if (!isoString) return '—';
    const date = new Date(isoString);
    return date.toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const loadPayments = async () => {
    if (!companyStore.currentCompany) return;

    loading.value = true;
    try {
        const params: Record<string, any> = {
            page: currentPage.value,
        };
        if (selectedStatus.value) params.status = selectedStatus.value;
        if (search.value) params.search = search.value;

        const result = await paymentStore.fetchPayments(companyStore.currentCompany.id, params);
        payments.value = paymentStore.payments;
        currentPage.value = paymentStore.currentPage;
        lastPage.value = paymentStore.lastPage;
        totalPayments.value = paymentStore.totalPayments;
    } catch (err) {
        console.error('Failed to load payments:', err);
    } finally {
        loading.value = false;
    }
};

let searchTimeout: any = null;
const handleSearch = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        currentPage.value = 1;
        loadPayments();
    }, 400);
};

const resetFilters = () => {
    search.value = '';
    selectedStatus.value = null;
    currentPage.value = 1;
    loadPayments();
};

const handlePageChange = (page: number) => {
    currentPage.value = page;
    loadPayments();
};

const viewPaymentDetails = (payment: PaymentRecord) => {
    selectedPayment.value = payment;
    detailDialog.value = true;
};

onMounted(() => {
    loadPayments();
});
</script>

<style scoped>
.font-mono {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}
</style>
