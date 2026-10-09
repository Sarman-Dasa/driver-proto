<template>
    <v-container fluid class="pa-6" style="max-width: 1100px">
        <!-- Back Navigation -->
        <div class="mb-4">
            <v-btn
                variant="text"
                prepend-icon="mdi-arrow-left"
                class="text-none font-weight-medium px-0"
                :to="{ name: 'invoices' }"
            >
                Back to Invoices
            </v-btn>
        </div>

        <!-- Loading State -->
        <div v-if="invoiceStore.loading && !invoice" class="text-center py-16">
            <v-progress-circular indeterminate color="primary" size="48"></v-progress-circular>
            <div class="text-body-2 text-medium-emphasis mt-3">Loading invoice details...</div>
        </div>

        <!-- Error State -->
        <v-alert
            v-else-if="invoiceStore.error && !invoice"
            type="error"
            variant="tonal"
            class="rounded-xl"
        >
            {{ invoiceStore.error }}
        </v-alert>

        <!-- Main Details Content -->
        <div v-else-if="invoice">
            <!-- Invoice Header Banner -->
            <v-card class="rounded-xl border pa-6 mb-6" elevation="0">
                <div class="d-flex flex-column flex-md-row align-md-center justify-space-between gap-4">
                    <div class="d-flex align-center gap-4">
                        <v-avatar color="primary" variant="tonal" size="64" class="rounded-xl">
                            <v-icon icon="mdi-file-document-outline" size="32"></v-icon>
                        </v-avatar>
                        <div>
                            <div class="d-flex align-center gap-2 flex-wrap">
                                <h1 class="text-h4 font-weight-bold tracking-tight">
                                    {{ invoice.invoice_number }}
                                </h1>
                                <v-chip
                                    :color="getStatusColor(invoice.status)"
                                    size="small"
                                    variant="tonal"
                                    class="font-weight-bold text-capitalize"
                                >
                                    {{ invoice.status_label || invoice.status }}
                                </v-chip>
                            </div>
                            <div class="text-subtitle-1 text-medium-emphasis mt-1">
                                Issued: <span class="text-high-emphasis font-weight-medium">{{ invoice.issue_date }}</span>
                                <span class="mx-2">•</span>
                                Due: <span class="text-high-emphasis font-weight-medium">{{ invoice.due_date }}</span>
                                <span class="mx-2">•</span>
                                <span class="text-caption text-uppercase font-weight-bold">{{ invoice.currency }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="d-flex align-center gap-2 flex-wrap">
                        <!-- Download PDF -->
                        <v-btn
                            color="secondary"
                            variant="outlined"
                            prepend-icon="mdi-file-pdf-box"
                            class="text-none font-weight-bold rounded-lg"
                            :loading="downloadingPdf"
                            @click="handleDownloadPdf"
                        >
                            Download PDF
                        </v-btn>

                        <!-- Send via Email -->
                        <v-btn
                            v-if="invoice.status !== 'cancelled' && permissionsStore.can('invoices.send')"
                            color="primary"
                            variant="flat"
                            prepend-icon="mdi-email-send-outline"
                            class="text-none font-weight-bold rounded-lg"
                            @click="openEmailDialog"
                        >
                            Send via Email
                        </v-btn>

                        <!-- Edit Draft -->
                        <v-btn
                            v-if="invoice.is_editable && permissionsStore.can('invoices.update')"
                            color="primary"
                            variant="outlined"
                            prepend-icon="mdi-pencil-outline"
                            class="text-none font-weight-bold rounded-lg"
                            :to="{ name: 'invoices.edit', params: { invoice: invoice.id } }"
                        >
                            Edit Draft
                        </v-btn>

                        <!-- Cancel Invoice -->
                        <v-btn
                            v-if="invoice.can_be_cancelled && permissionsStore.can('invoices.update')"
                            color="warning"
                            variant="outlined"
                            prepend-icon="mdi-cancel"
                            class="text-none font-weight-bold rounded-lg"
                            @click="cancelDialog = true"
                        >
                            Cancel
                        </v-btn>

                        <!-- Delete Draft -->
                        <v-btn
                            v-if="invoice.is_editable && permissionsStore.can('invoices.delete')"
                            color="error"
                            variant="text"
                            prepend-icon="mdi-delete-outline"
                            class="text-none font-weight-bold rounded-lg"
                            @click="deleteDialog = true"
                        >
                            Delete
                        </v-btn>
                    </div>
                </div>
            </v-card>

            <!-- Customer & Invoice Info Row -->
            <v-row class="mb-6">
                <!-- Customer Billing Details Snapshot -->
                <v-col cols="12" md="6">
                    <v-card class="rounded-xl border pa-6 h-100" elevation="0">
                        <div class="text-subtitle-1 font-weight-bold mb-4 d-flex align-center">
                            <v-icon icon="mdi-account-outline" class="mr-2" color="primary"></v-icon>
                            Billed To (Customer Snapshot)
                        </div>

                        <div class="text-h6 font-weight-bold mb-1">
                            {{ invoice.customer?.name || invoice.customer_snapshot?.name || 'Customer' }}
                        </div>
                        <div v-if="invoice.customer?.company_name || invoice.customer_snapshot?.company_name" class="text-body-2 font-weight-medium text-medium-emphasis mb-2">
                            {{ invoice.customer?.company_name || invoice.customer_snapshot?.company_name }}
                        </div>

                        <div class="text-body-2 text-medium-emphasis mb-1">
                            <span v-if="invoice.customer?.email || invoice.customer_snapshot?.email">
                                <v-icon icon="mdi-email-outline" size="16" class="mr-1"></v-icon>
                                {{ invoice.customer?.email || invoice.customer_snapshot?.email }}
                            </span>
                            <span v-if="(invoice.customer?.email || invoice.customer_snapshot?.email) && (invoice.customer?.phone || invoice.customer_snapshot?.phone)" class="mx-2">•</span>
                            <span v-if="invoice.customer?.phone || invoice.customer_snapshot?.phone">
                                <v-icon icon="mdi-phone-outline" size="16" class="mr-1"></v-icon>
                                {{ invoice.customer?.phone || invoice.customer_snapshot?.phone }}
                            </span>
                        </div>

                        <div v-if="invoice.customer?.tax_number || invoice.customer_snapshot?.tax_number" class="text-caption text-medium-emphasis mb-2">
                            Tax ID: <strong>{{ invoice.customer?.tax_number || invoice.customer_snapshot?.tax_number }}</strong>
                        </div>

                        <div v-if="billingAddressFormatted" class="text-body-2 text-medium-emphasis mt-2">
                            <v-icon icon="mdi-map-marker-outline" size="16" class="mr-1"></v-icon>
                            {{ billingAddressFormatted }}
                        </div>
                    </v-card>
                </v-col>

                <!-- Financial Balance Overview -->
                <v-col cols="12" md="6">
                    <v-card class="rounded-xl border pa-6 h-100 bg-surface" elevation="0">
                        <div class="text-subtitle-1 font-weight-bold mb-4 d-flex align-center">
                            <v-icon icon="mdi-cash-multiple" class="mr-2" color="primary"></v-icon>
                            Payment Status & Balances
                        </div>

                        <div class="d-flex justify-space-between align-center mb-4">
                            <div>
                                <div class="text-caption text-uppercase font-weight-bold text-medium-emphasis">
                                    Total Amount
                                </div>
                                <div class="text-h4 font-weight-bold text-primary">
                                    {{ invoice.formatted_total_amount }}
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-caption text-uppercase font-weight-bold text-medium-emphasis">
                                    Balance Due
                                </div>
                                <div class="text-h4 font-weight-bold" :class="invoice.balance_due > 0 ? 'text-error' : 'text-success'">
                                    {{ invoice.formatted_balance_due }}
                                </div>
                            </div>
                        </div>

                        <v-divider class="my-3"></v-divider>

                        <div class="d-flex justify-space-between text-body-2 py-1">
                            <span class="text-medium-emphasis">Amount Paid</span>
                            <span class="font-weight-medium">${{ (invoice.amount_paid / 100).toFixed(2) }}</span>
                        </div>
                        <div class="d-flex justify-space-between text-body-2 py-1">
                            <span class="text-medium-emphasis">Sent Timestamp</span>
                            <span class="font-weight-medium">{{ invoice.sent_at || 'Not sent yet' }}</span>
                        </div>
                        <div v-if="invoice.cancelled_at" class="d-flex justify-space-between text-body-2 py-1 text-warning">
                            <span>Cancelled At</span>
                            <span class="font-weight-medium">{{ invoice.cancelled_at }}</span>
                        </div>
                    </v-card>
                </v-col>
            </v-row>

            <!-- Line Items Table -->
            <v-card class="rounded-xl border overflow-hidden mb-6" elevation="0">
                <div class="pa-4 border-b d-flex align-center">
                    <v-icon icon="mdi-format-list-bulleted" class="mr-2" color="primary"></v-icon>
                    <span class="text-subtitle-1 font-weight-bold">Invoice Line Items</span>
                </div>

                <v-table hover>
                    <thead>
                        <tr class="bg-surface-variant text-caption font-weight-bold text-uppercase">
                            <th class="py-3 px-4">Item & Description</th>
                            <th class="py-3 px-4 text-center">Qty</th>
                            <th class="py-3 px-4 text-right">Unit Price</th>
                            <th class="py-3 px-4 text-right">Tax Rate</th>
                            <th class="py-3 px-4 text-right">Tax Amount</th>
                            <th class="py-3 px-4 text-right">Line Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in invoice.items" :key="item.id">
                            <td class="py-3 px-4">
                                <div class="font-weight-bold">{{ item.product_name }}</div>
                                <div v-if="item.description" class="text-caption text-medium-emphasis">
                                    {{ item.description }}
                                </div>
                            </td>
                            <td class="py-3 px-4 text-center">
                                {{ item.quantity }} <span v-if="item.unit" class="text-caption text-disabled">{{ item.unit }}</span>
                            </td>
                            <td class="py-3 px-4 text-right font-weight-medium">
                                {{ item.formatted_unit_price }}
                            </td>
                            <td class="py-3 px-4 text-right">
                                {{ item.tax_rate }}%
                            </td>
                            <td class="py-3 px-4 text-right text-medium-emphasis">
                                {{ item.formatted_tax_amount }}
                            </td>
                            <td class="py-3 px-4 text-right font-weight-bold">
                                {{ item.formatted_total }}
                            </td>
                        </tr>
                    </tbody>
                </v-table>

                <!-- Totals Breakdown Footer -->
                <div class="pa-6 border-t bg-surface">
                    <v-row justify="end">
                        <v-col cols="12" sm="6" md="5">
                            <div class="d-flex justify-space-between mb-2 text-body-2">
                                <span class="text-medium-emphasis">Subtotal</span>
                                <span class="font-weight-medium">{{ invoice.formatted_subtotal }}</span>
                            </div>

                            <div v-if="invoice.discount_amount > 0" class="d-flex justify-space-between mb-2 text-body-2 text-success">
                                <span>Discount <span v-if="invoice.discount_type === 'percentage'">({{ invoice.discount_value }}%)</span></span>
                                <span class="font-weight-medium">-{{ invoice.formatted_discount_amount }}</span>
                            </div>

                            <div class="d-flex justify-space-between mb-2 text-body-2">
                                <span class="text-medium-emphasis">Total Taxes</span>
                                <span class="font-weight-medium">{{ invoice.formatted_tax_amount }}</span>
                            </div>

                            <v-divider class="my-3"></v-divider>

                            <div class="d-flex justify-space-between align-center mb-2">
                                <span class="text-h6 font-weight-bold">Total Amount</span>
                                <span class="text-h6 font-weight-bold text-primary">
                                    {{ invoice.formatted_total_amount }}
                                </span>
                            </div>

                            <div class="d-flex justify-space-between align-center">
                                <span class="text-subtitle-2 font-weight-bold">Balance Due</span>
                                <span class="text-subtitle-2 font-weight-bold" :class="invoice.balance_due > 0 ? 'text-error' : 'text-success'">
                                    {{ invoice.formatted_balance_due }}
                                </span>
                            </div>
                        </v-col>
                    </v-row>
                </div>
            </v-card>

            <!-- Notes & Payment Terms -->
            <v-card v-if="invoice.notes || invoice.terms" class="rounded-xl border pa-6 mb-6" elevation="0">
                <v-row dense>
                    <v-col v-if="invoice.notes" cols="12" md="6">
                        <div class="text-caption font-weight-bold text-uppercase text-medium-emphasis mb-1">
                            Client Notes
                        </div>
                        <div class="text-body-2 whitespace-pre-line">{{ invoice.notes }}</div>
                    </v-col>

                    <v-col v-if="invoice.terms" cols="12" md="6">
                        <div class="text-caption font-weight-bold text-uppercase text-medium-emphasis mb-1">
                            Payment Terms
                        </div>
                        <div class="text-body-2 whitespace-pre-line">{{ invoice.terms }}</div>
                    </v-col>
                </v-row>
            </v-card>
            <!-- Delivery & Email History Section -->
            <v-card class="rounded-xl border pa-6 mb-6" elevation="0">
                <div class="d-flex align-center justify-space-between mb-4">
                    <div class="d-flex align-center gap-2">
                        <v-icon icon="mdi-email-fast-outline" color="primary"></v-icon>
                        <h2 class="text-h6 font-weight-bold">Email & Delivery History</h2>
                    </div>
                    <v-btn
                        variant="text"
                        size="small"
                        icon="mdi-refresh"
                        :loading="loadingDeliveries"
                        @click="refreshDeliveries"
                    ></v-btn>
                </div>

                <!-- Empty State -->
                <div
                    v-if="!invoice.deliveries || invoice.deliveries.length === 0"
                    class="text-center py-6 text-body-2 text-medium-emphasis"
                >
                    <v-icon icon="mdi-email-outline" size="36" class="text-disabled mb-2"></v-icon>
                    <div>No email delivery attempts recorded yet.</div>
                    <div class="text-caption">Use "Send via Email" above to dispatch this invoice and track delivery logs.</div>
                </div>

                <!-- Deliveries List -->
                <div v-else class="border rounded-lg overflow-hidden">
                    <v-table density="comfortable">
                        <thead>
                            <tr class="bg-surface-variant">
                                <th class="text-left font-weight-bold">Recipient</th>
                                <th class="text-left font-weight-bold">Subject</th>
                                <th class="text-center font-weight-bold">Status</th>
                                <th class="text-center font-weight-bold">Attempts</th>
                                <th class="text-right font-weight-bold">Sent / Attempted</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="delivery in invoice.deliveries" :key="delivery.id">
                                <td class="font-weight-medium">
                                    {{ delivery.recipient_email }}
                                </td>
                                <td class="text-body-2 text-truncate" style="max-width: 250px;">
                                    {{ delivery.subject }}
                                </td>
                                <td class="text-center">
                                    <v-chip
                                        :color="getDeliveryStatusColor(delivery.status)"
                                        size="x-small"
                                        variant="tonal"
                                        class="font-weight-bold text-capitalize"
                                    >
                                        {{ delivery.status_label || delivery.status }}
                                    </v-chip>
                                </td>
                                <td class="text-center text-body-2">
                                    {{ delivery.attempts }}
                                </td>
                                <td class="text-right text-caption text-medium-emphasis">
                                    {{ formatDate(delivery.sent_at || delivery.failed_at || delivery.created_at) }}
                                </td>
                            </tr>
                        </tbody>
                    </v-table>
                </div>
            </v-card>
        </div>

        <!-- Send via Email Dialog -->
        <v-dialog v-model="emailDialog" max-width="560">
            <v-card class="rounded-xl pa-4">
                <v-card-title class="d-flex align-center gap-2 text-h6 font-weight-bold">
                    <v-icon icon="mdi-email-send-outline" color="primary"></v-icon>
                    Email Invoice {{ invoice?.invoice_number }}
                </v-card-title>
                <v-card-text class="py-2">
                    <div class="text-body-2 text-medium-emphasis mb-4">
                        Send this invoice directly to the customer. A professional PDF invoice will be automatically attached.
                    </div>

                    <v-form ref="emailFormRef" v-model="emailFormValid" @submit.prevent="handleSendEmail">
                        <!-- Recipient Email -->
                        <v-text-field
                            v-model="emailPayload.recipient_email"
                            label="Recipient Email *"
                            variant="outlined"
                            density="comfortable"
                            type="email"
                            :rules="[rules.required, rules.email]"
                            placeholder="customer@example.com"
                            class="mb-3"
                        ></v-text-field>

                        <!-- Subject -->
                        <v-text-field
                            v-model="emailPayload.subject"
                            label="Subject Line *"
                            variant="outlined"
                            density="comfortable"
                            :rules="[rules.required]"
                            class="mb-3"
                        ></v-text-field>

                        <!-- Custom Message -->
                        <v-textarea
                            v-model="emailPayload.message"
                            label="Message Note (Optional)"
                            variant="outlined"
                            density="comfortable"
                            rows="3"
                            placeholder="Add a personalized message or instructions for the recipient..."
                            class="mb-2"
                        ></v-textarea>

                        <!-- Attachment Indicator -->
                        <v-alert
                            type="info"
                            variant="tonal"
                            density="compact"
                            icon="mdi-paperclip"
                            class="rounded-lg text-caption"
                        >
                            Attached: <strong>Invoice-{{ invoice?.invoice_number }}.pdf</strong> (Autoritative snapshot PDF)
                        </v-alert>
                    </v-form>
                </v-card-text>
                <v-card-actions class="d-flex justify-end gap-2 pt-4">
                    <v-btn variant="text" class="text-none" :disabled="actionLoading" @click="emailDialog = false">
                        Cancel
                    </v-btn>
                    <v-btn
                        color="primary"
                        variant="flat"
                        class="text-none font-weight-bold"
                        :loading="actionLoading"
                        :disabled="!emailFormValid"
                        @click="handleSendEmail"
                    >
                        Send Invoice
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Cancel Invoice Dialog -->
        <v-dialog v-model="cancelDialog" max-width="480">
            <v-card class="rounded-xl pa-4">
                <v-card-title class="d-flex align-center gap-2 text-h6 font-weight-bold text-warning">
                    <v-icon icon="mdi-cancel" color="warning"></v-icon>
                    Cancel Invoice {{ invoice?.invoice_number }}?
                </v-card-title>
                <v-card-text class="text-body-2 text-medium-emphasis py-2">
                    Are you sure you want to cancel this invoice? The invoice status will permanently become Cancelled.
                </v-card-text>
                <v-card-actions class="d-flex justify-end gap-2 pt-4">
                    <v-btn variant="text" class="text-none" @click="cancelDialog = false">Close</v-btn>
                    <v-btn
                        color="warning"
                        variant="flat"
                        class="text-none font-weight-bold"
                        :loading="actionLoading"
                        @click="confirmCancel"
                    >
                        Yes, Cancel Invoice
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Delete Draft Dialog -->
        <v-dialog v-model="deleteDialog" max-width="480">
            <v-card class="rounded-xl pa-4">
                <v-card-title class="d-flex align-center gap-2 text-h6 font-weight-bold text-error">
                    <v-icon icon="mdi-delete-alert-outline" color="error"></v-icon>
                    Delete Draft Invoice?
                </v-card-title>
                <v-card-text class="text-body-2 text-medium-emphasis py-2">
                    This will soft-delete draft invoice <strong>{{ invoice?.invoice_number }}</strong>.
                </v-card-text>
                <v-card-actions class="d-flex justify-end gap-2 pt-4">
                    <v-btn variant="text" class="text-none" @click="deleteDialog = false">Cancel</v-btn>
                    <v-btn
                        color="error"
                        variant="flat"
                        class="text-none font-weight-bold"
                        :loading="actionLoading"
                        @click="confirmDelete"
                    >
                        Delete Draft
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Notification Snackbar -->
        <v-snackbar v-model="snackbar" :color="snackbarColor" timeout="4000" location="bottom end">
            {{ snackbarText }}
        </v-snackbar>
    </v-container>
</template>

<script setup lang="ts">
import { ref, computed, reactive, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useInvoiceStore } from '@/stores/invoices';
import { usePermissionsStore } from '@/stores/permissions';
import { useCompanyStore } from '@/stores/company';
import type { InvoiceStatus, InvoiceDeliveryStatus, SendInvoiceEmailPayload } from '@/types';

const route = useRoute();
const router = useRouter();
const invoiceStore = useInvoiceStore();
const permissionsStore = usePermissionsStore();
const companyStore = useCompanyStore();

const invoiceId = Number(route.params.invoice);
const invoice = computed(() => invoiceStore.currentInvoice);

const emailDialog = ref(false);
const cancelDialog = ref(false);
const deleteDialog = ref(false);
const actionLoading = ref(false);
const downloadingPdf = ref(false);
const loadingDeliveries = ref(false);

const emailFormValid = ref(false);
const emailPayload = reactive<SendInvoiceEmailPayload>({
    recipient_email: '',
    subject: '',
    message: '',
});

const snackbar = ref(false);
const snackbarText = ref('');
const snackbarColor = ref('success');

const rules = {
    required: (v: string) => !!v || 'Field is required',
    email: (v: string) => /.+@.+\..+/.test(v) || 'Must be a valid email address',
};

const getStatusColor = (status: InvoiceStatus): string => {
    switch (status) {
        case 'draft': return 'default';
        case 'sent': return 'primary';
        case 'partially_paid': return 'warning';
        case 'paid': return 'success';
        case 'overdue': return 'error';
        case 'cancelled': return 'secondary';
        default: return 'default';
    }
};

const getDeliveryStatusColor = (status: InvoiceDeliveryStatus): string => {
    switch (status) {
        case 'queued': return 'warning';
        case 'sent': return 'success';
        case 'failed': return 'error';
        default: return 'default';
    }
};

const formatDate = (dateString?: string | null): string => {
    if (!dateString) return '—';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const billingAddressFormatted = computed(() => {
    if (!invoice.value) return '';
    const snap = invoice.value.customer_snapshot || invoice.value.customer;
    if (!snap) return '';
    const parts = [
        snap.billing_address,
        snap.billing_city,
        snap.billing_state,
        snap.billing_postal_code,
        snap.billing_country,
    ].filter(Boolean);
    return parts.join(', ');
});

const handleDownloadPdf = async () => {
    if (!invoice.value) return;
    downloadingPdf.value = true;
    try {
        await invoiceStore.downloadPdf(invoice.value.id, invoice.value.invoice_number);
        snackbarText.value = 'PDF downloaded successfully.';
        snackbarColor.value = 'success';
        snackbar.value = true;
    } catch {
        snackbarText.value = invoiceStore.error || 'Failed to download PDF.';
        snackbarColor.value = 'error';
        snackbar.value = true;
    } finally {
        downloadingPdf.value = false;
    }
};

const openEmailDialog = () => {
    if (!invoice.value) return;
    const snap = invoice.value.customer_snapshot || invoice.value.customer;
    const companyName = companyStore.currentCompany?.name || 'BillFlow';

    emailPayload.recipient_email = snap?.email || '';
    emailPayload.subject = `Invoice ${invoice.value.invoice_number} from ${companyName}`;
    emailPayload.message = `Please find attached your invoice ${invoice.value.invoice_number}. Please let us know if you have any questions.`;

    emailDialog.value = true;
};

const handleSendEmail = async () => {
    if (!invoice.value || !emailPayload.recipient_email) return;
    actionLoading.value = true;
    try {
        await invoiceStore.sendEmail(invoice.value.id, {
            recipient_email: emailPayload.recipient_email,
            subject: emailPayload.subject,
            message: emailPayload.message,
        });
        emailDialog.value = false;
        snackbarText.value = `Invoice email queued for delivery to ${emailPayload.recipient_email}.`;
        snackbarColor.value = 'success';
        snackbar.value = true;
    } catch {
        snackbarText.value = invoiceStore.error || 'Failed to queue invoice email.';
        snackbarColor.value = 'error';
        snackbar.value = true;
    } finally {
        actionLoading.value = false;
    }
};

const refreshDeliveries = async () => {
    if (!invoice.value) return;
    loadingDeliveries.value = true;
    try {
        await invoiceStore.fetchDeliveries(invoice.value.id);
    } finally {
        loadingDeliveries.value = false;
    }
};

const confirmCancel = async () => {
    if (!invoice.value) return;
    actionLoading.value = true;
    try {
        await invoiceStore.cancelInvoice(invoice.value.id);
        cancelDialog.value = false;
        snackbarText.value = 'Invoice cancelled successfully.';
        snackbarColor.value = 'success';
        snackbar.value = true;
    } finally {
        actionLoading.value = false;
    }
};

const confirmDelete = async () => {
    if (!invoice.value) return;
    actionLoading.value = true;
    try {
        await invoiceStore.deleteInvoice(invoice.value.id);
        deleteDialog.value = false;
        router.push({ name: 'invoices' });
    } finally {
        actionLoading.value = false;
    }
};

onMounted(() => {
    invoiceStore.fetchInvoice(invoiceId);
});
</script>

