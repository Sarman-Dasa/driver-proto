<template>
    <v-container fluid class="pa-6" style="max-width: 1200px">
        <!-- Page Header -->
        <div class="d-flex flex-column flex-sm-row align-sm-center justify-space-between mb-6 gap-4">
            <div>
                <div class="d-flex align-center gap-2 mb-1">
                    <h1 class="text-h4 font-weight-bold tracking-tight">Invoices</h1>
                    <v-chip
                        v-if="invoiceStore.pagination"
                        size="small"
                        color="primary"
                        variant="tonal"
                        class="font-weight-bold ml-2"
                    >
                        {{ invoiceStore.pagination.total }} invoices
                    </v-chip>
                </div>
                <p class="text-subtitle-1 text-medium-emphasis">
                    Create, manage, and track client billing, draft line items, and payment schedules
                </p>
            </div>

            <div class="d-flex align-center gap-2">
                <v-btn
                    v-if="permissionsStore.can('invoices.create')"
                    color="primary"
                    prepend-icon="mdi-plus"
                    class="text-none font-weight-bold rounded-lg"
                    size="large"
                    :disabled="isLimitReached"
                    :to="{ name: 'invoices.create' }"
                >
                    Create Invoice
                </v-btn>
            </div>
        </div>

        <!-- Plan Limit Reached Banner -->
        <v-alert
            v-if="isLimitReached"
            type="warning"
            variant="tonal"
            density="comfortable"
            class="mb-6 rounded-xl border border-warning"
        >
            <div class="d-flex flex-column flex-sm-row align-sm-center justify-space-between gap-3">
                <div class="d-flex align-center gap-2">
                    <v-icon icon="mdi-alert-circle-outline" size="24"></v-icon>
                    <div>
                        <div class="font-weight-bold">Monthly invoice limit reached on your plan</div>
                        <div class="text-body-2 text-medium-emphasis">
                            You have reached your monthly plan limit for invoices. Upgrade to Pro or Business for unlimited monthly invoicing.
                        </div>
                    </div>
                </div>
                <v-btn
                    color="warning"
                    variant="flat"
                    size="small"
                    prepend-icon="mdi-arrow-up-bold-circle-outline"
                    class="text-none font-weight-bold rounded-lg text-dark"
                    :to="{ name: 'pricing' }"
                >
                    Upgrade Plan
                </v-btn>
            </div>
        </v-alert>

        <!-- Store Errors -->
        <v-alert
            v-if="invoiceStore.error"
            type="error"
            variant="tonal"
            density="comfortable"
            class="mb-6 rounded-lg"
            closable
            @click:close="invoiceStore.error = null"
        >
            {{ invoiceStore.error }}
        </v-alert>

        <!-- Search & Filter Controls -->
        <v-card class="rounded-xl border pa-4 mb-6" elevation="0">
            <v-row dense align="center">
                <!-- Search Input -->
                <v-col cols="12" md="4">
                    <v-text-field
                        v-model="searchInput"
                        placeholder="Search by invoice # or client..."
                        prepend-inner-icon="mdi-magnify"
                        density="compact"
                        variant="outlined"
                        hide-details
                        clearable
                        class="rounded-lg"
                        @update:model-value="onSearchInput"
                    ></v-text-field>
                </v-col>

                <!-- Status Filter -->
                <v-col cols="12" sm="4" md="3">
                    <v-select
                        v-model="invoiceStore.filters.status"
                        :items="statusOptions"
                        item-title="title"
                        item-value="value"
                        label="Status"
                        density="compact"
                        variant="outlined"
                        hide-details
                        class="rounded-lg"
                        @update:model-value="applyFilters"
                    ></v-select>
                </v-col>

                <!-- Customer Filter -->
                <v-col cols="12" sm="4" md="3">
                    <v-autocomplete
                        v-model="invoiceStore.filters.customer_id"
                        :items="customersList"
                        item-title="name"
                        item-value="id"
                        label="Customer"
                        density="compact"
                        variant="outlined"
                        hide-details
                        clearable
                        class="rounded-lg"
                        @update:model-value="applyFilters"
                    ></v-autocomplete>
                </v-col>

                <!-- Refresh Button -->
                <v-col cols="12" sm="4" md="2" class="d-flex justify-end">
                    <v-btn
                        variant="outlined"
                        prepend-icon="mdi-refresh"
                        density="comfortable"
                        class="text-none rounded-lg"
                        :loading="invoiceStore.loading"
                        @click="refreshList"
                    >
                        Refresh
                    </v-btn>
                </v-col>
            </v-row>
        </v-card>

        <!-- Invoices Data Table -->
        <v-card class="rounded-xl border overflow-hidden" elevation="0">
            <v-table hover>
                <thead>
                    <tr class="bg-surface-variant text-caption font-weight-bold text-uppercase">
                        <th class="py-3 px-4">Invoice #</th>
                        <th class="py-3 px-4">Customer</th>
                        <th class="py-3 px-4">Issue Date</th>
                        <th class="py-3 px-4">Due Date</th>
                        <th class="py-3 px-4 text-right">Total Amount</th>
                        <th class="py-3 px-4 text-right">Balance Due</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Loading Skeleton -->
                    <tr v-if="invoiceStore.loading && invoiceStore.invoices.length === 0">
                        <td colspan="8" class="text-center py-8">
                            <v-progress-circular indeterminate color="primary"></v-progress-circular>
                            <div class="text-caption text-medium-emphasis mt-2">Loading invoices...</div>
                        </td>
                    </tr>

                    <!-- Empty State -->
                    <tr v-else-if="invoiceStore.invoices.length === 0">
                        <td colspan="8" class="text-center py-12">
                            <div class="d-flex flex-column align-center justify-center">
                                <v-avatar color="primary" variant="tonal" size="64" class="mb-3">
                                    <v-icon icon="mdi-file-document-outline" size="32"></v-icon>
                                </v-avatar>
                                <div class="text-h6 font-weight-bold mb-1">
                                    {{ searchInput || invoiceStore.filters.status !== 'all' ? 'No matching invoices' : 'No invoices created yet' }}
                                </div>
                                <div class="text-body-2 text-medium-emphasis mb-4" style="max-width: 400px">
                                    {{
                                        searchInput || invoiceStore.filters.status !== 'all'
                                            ? 'No invoices match your search or filter settings. Try adjusting your search query.'
                                            : 'Create your first draft invoice with custom line items and client billing details.'
                                    }}
                                </div>
                                <v-btn
                                    v-if="!searchInput && invoiceStore.filters.status === 'all' && permissionsStore.can('invoices.create')"
                                    color="primary"
                                    prepend-icon="mdi-plus"
                                    class="text-none font-weight-bold rounded-lg"
                                    :disabled="isLimitReached"
                                    :to="{ name: 'invoices.create' }"
                                >
                                    Create Invoice
                                </v-btn>
                                <v-btn
                                    v-else-if="searchInput || invoiceStore.filters.status !== 'all'"
                                    variant="outlined"
                                    size="small"
                                    class="text-none"
                                    @click="clearFilters"
                                >
                                    Clear Filters
                                </v-btn>
                            </div>
                        </td>
                    </tr>

                    <!-- Invoice Rows -->
                    <tr
                        v-for="invoice in invoiceStore.invoices"
                        :key="invoice.id"
                        class="cursor-pointer"
                        @click="viewInvoice(invoice)"
                    >
                        <!-- Invoice Number -->
                        <td class="py-3 px-4 font-weight-bold text-primary">
                            {{ invoice.invoice_number }}
                        </td>

                        <!-- Customer -->
                        <td class="py-3 px-4">
                            <div class="font-weight-medium">
                                {{ invoice.customer?.name || invoice.customer_snapshot?.name || 'Unknown Customer' }}
                            </div>
                            <div class="text-caption text-medium-emphasis">
                                {{ invoice.customer?.company_name || invoice.customer_snapshot?.company_name || '' }}
                            </div>
                        </td>

                        <!-- Issue Date -->
                        <td class="py-3 px-4 text-body-2">
                            {{ invoice.issue_date }}
                        </td>

                        <!-- Due Date -->
                        <td class="py-3 px-4 text-body-2">
                            {{ invoice.due_date }}
                        </td>

                        <!-- Total Amount -->
                        <td class="py-3 px-4 text-right font-weight-bold">
                            {{ invoice.formatted_total_amount }}
                        </td>

                        <!-- Balance Due -->
                        <td class="py-3 px-4 text-right font-weight-medium" :class="invoice.balance_due > 0 ? 'text-error' : 'text-success'">
                            {{ invoice.formatted_balance_due }}
                        </td>

                        <!-- Status Badge -->
                        <td class="py-3 px-4 text-center">
                            <v-chip
                                :color="getStatusColor(invoice.status)"
                                size="small"
                                variant="tonal"
                                class="font-weight-bold text-capitalize"
                            >
                                {{ invoice.status_label || invoice.status }}
                            </v-chip>
                        </td>

                        <!-- Actions Menu -->
                        <td class="py-3 px-4 text-right" @click.stop>
                            <v-menu location="bottom end">
                                <template #activator="{ props }">
                                    <v-btn
                                        icon="mdi-dots-vertical"
                                        variant="text"
                                        size="small"
                                        v-bind="props"
                                    ></v-btn>
                                </template>
                                <v-list density="compact" class="rounded-lg elevation-2">
                                    <!-- View Details -->
                                    <v-list-item
                                        prepend-icon="mdi-eye-outline"
                                        title="View Details"
                                        @click="viewInvoice(invoice)"
                                    ></v-list-item>

                                    <!-- Download PDF -->
                                    <v-list-item
                                        prepend-icon="mdi-file-pdf-box"
                                        title="Download PDF"
                                        @click="downloadPdf(invoice)"
                                    ></v-list-item>

                                    <!-- Send via Email -->
                                    <v-list-item
                                        v-if="invoice.status !== 'cancelled' && permissionsStore.can('invoices.send')"
                                        prepend-icon="mdi-email-send-outline"
                                        title="Email Invoice"
                                        color="primary"
                                        @click="openEmailDialog(invoice)"
                                    ></v-list-item>

                                    <!-- Edit Draft -->
                                    <v-list-item
                                        v-if="invoice.is_editable && permissionsStore.can('invoices.update')"
                                        prepend-icon="mdi-pencil-outline"
                                        title="Edit Draft"
                                        :to="{ name: 'invoices.edit', params: { invoice: invoice.id } }"
                                    ></v-list-item>

                                    <!-- Finalize Send Invoice -->
                                    <v-list-item
                                        v-if="invoice.can_be_sent && permissionsStore.can('invoices.send')"
                                        prepend-icon="mdi-check-circle-outline"
                                        title="Mark as Sent"
                                        @click="promptSend(invoice)"
                                    ></v-list-item>

                                    <!-- Cancel Invoice -->
                                    <v-list-item
                                        v-if="invoice.can_be_cancelled && permissionsStore.can('invoices.update')"
                                        prepend-icon="mdi-cancel"
                                        title="Cancel Invoice"
                                        color="warning"
                                        @click="promptCancel(invoice)"
                                    ></v-list-item>

                                    <v-divider v-if="invoice.is_editable && permissionsStore.can('invoices.delete')" class="my-1"></v-divider>

                                    <!-- Delete Draft -->
                                    <v-list-item
                                        v-if="invoice.is_editable && permissionsStore.can('invoices.delete')"
                                        prepend-icon="mdi-delete-outline"
                                        title="Delete Draft"
                                        color="error"
                                        @click="promptDelete(invoice)"
                                    ></v-list-item>
                                </v-list>
                            </v-menu>
                        </td>
                    </tr>
                </tbody>
            </v-table>

            <!-- Pagination Bar -->
            <div
                v-if="invoiceStore.pagination && invoiceStore.pagination.last_page > 1"
                class="d-flex justify-space-between align-center px-4 py-3 border-t"
            >
                <div class="text-caption text-medium-emphasis">
                    Showing {{ invoiceStore.pagination.from || 0 }} to {{ invoiceStore.pagination.to || 0 }} of {{ invoiceStore.pagination.total }} invoices
                </div>
                <v-pagination
                    v-model="invoiceStore.filters.page"
                    :length="invoiceStore.pagination.last_page"
                    total-visible="5"
                    density="compact"
                    @update:model-value="onPageChange"
                ></v-pagination>
            </div>
        </v-card>

        <!-- Send Invoice Dialog -->
        <v-dialog v-model="sendDialog" max-width="480">
            <v-card class="rounded-xl pa-4">
                <v-card-title class="d-flex align-center gap-2 text-h6 font-weight-bold">
                    <v-icon icon="mdi-send-outline" color="primary"></v-icon>
                    Send Invoice {{ selectedInvoice?.invoice_number }}?
                </v-card-title>
                <v-card-text class="text-body-2 text-medium-emphasis py-2">
                    Sending will mark this invoice as issued and official (status: Sent). Line items and totals will become locked from further edits.
                </v-card-text>
                <v-card-actions class="d-flex justify-end gap-2 pt-4">
                    <v-btn variant="text" class="text-none" @click="sendDialog = false">Cancel</v-btn>
                    <v-btn
                        color="primary"
                        variant="flat"
                        class="text-none font-weight-bold"
                        :loading="actionLoading"
                        @click="confirmSend"
                    >
                        Confirm & Send
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Cancel Invoice Dialog -->
        <v-dialog v-model="cancelDialog" max-width="480">
            <v-card class="rounded-xl pa-4">
                <v-card-title class="d-flex align-center gap-2 text-h6 font-weight-bold text-warning">
                    <v-icon icon="mdi-cancel" color="warning"></v-icon>
                    Cancel Invoice {{ selectedInvoice?.invoice_number }}?
                </v-card-title>
                <v-card-text class="text-body-2 text-medium-emphasis py-2">
                    Are you sure you want to cancel this invoice? The invoice will be permanently marked as Cancelled and cannot be un-cancelled.
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
                    This will soft-delete draft invoice <strong>{{ selectedInvoice?.invoice_number }}</strong>. It will be removed from your active invoices list.
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

        <!-- Send via Email Dialog -->
        <v-dialog v-model="emailDialog" max-width="560">
            <v-card class="rounded-xl pa-4">
                <v-card-title class="d-flex align-center gap-2 text-h6 font-weight-bold">
                    <v-icon icon="mdi-email-send-outline" color="primary"></v-icon>
                    Email Invoice {{ selectedInvoice?.invoice_number }}
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
                            Attached: <strong>Invoice-{{ selectedInvoice?.invoice_number }}.pdf</strong> (Autoritative snapshot PDF)
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

        <!-- Notification Snackbar -->
        <v-snackbar v-model="snackbar" :color="snackbarColor" timeout="4000" location="bottom end">
            {{ snackbarText }}
        </v-snackbar>
    </v-container>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useInvoiceStore } from '@/stores/invoices';
import { useCustomerStore } from '@/stores/customers';
import { useCompanyStore } from '@/stores/company';
import { usePermissionsStore } from '@/stores/permissions';
import { useSubscriptionStore } from '@/stores/subscription';
import type { Invoice, InvoiceStatus, SendInvoiceEmailPayload } from '@/types';

const router = useRouter();
const invoiceStore = useInvoiceStore();
const customerStore = useCustomerStore();
const companyStore = useCompanyStore();
const permissionsStore = usePermissionsStore();
const subscriptionStore = useSubscriptionStore();

const searchInput = ref('');
const actionLoading = ref(false);
const selectedInvoice = ref<Invoice | null>(null);

const sendDialog = ref(false);
const cancelDialog = ref(false);
const deleteDialog = ref(false);
const emailDialog = ref(false);
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

const statusOptions = [
    { title: 'All Invoices', value: 'all' },
    { title: 'Draft', value: 'draft' },
    { title: 'Sent', value: 'sent' },
    { title: 'Partially Paid', value: 'partially_paid' },
    { title: 'Paid', value: 'paid' },
    { title: 'Overdue', value: 'overdue' },
    { title: 'Cancelled', value: 'cancelled' },
];

const customersList = computed(() => customerStore.customers);

const isLimitReached = computed(() => {
    const feature = subscriptionStore.features['invoices'];
    if (!feature) {
        return false;
    }
    if (feature.is_unlimited) {
        return false;
    }
    return Boolean(feature.has_reached_limit);
});

let debounceTimer: ReturnType<typeof setTimeout> | null = null;
const onSearchInput = (val: string | null) => {
    if (debounceTimer) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        invoiceStore.filters.search = val || '';
        invoiceStore.filters.page = 1;
        invoiceStore.fetchInvoices();
    }, 400);
};

const applyFilters = () => {
    invoiceStore.filters.page = 1;
    invoiceStore.fetchInvoices();
};

const clearFilters = () => {
    searchInput.value = '';
    invoiceStore.filters.search = '';
    invoiceStore.filters.status = 'all';
    invoiceStore.filters.customer_id = null;
    invoiceStore.filters.page = 1;
    invoiceStore.fetchInvoices();
};

const refreshList = () => {
    invoiceStore.fetchInvoices();
};

const onPageChange = () => {
    invoiceStore.fetchInvoices();
};

const viewInvoice = (invoice: Invoice) => {
    router.push({ name: 'invoices.detail', params: { invoice: invoice.id } });
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

const promptSend = (invoice: Invoice) => {
    selectedInvoice.value = invoice;
    sendDialog.value = true;
};

const confirmSend = async () => {
    if (!selectedInvoice.value) return;
    actionLoading.value = true;
    try {
        await invoiceStore.sendInvoice(selectedInvoice.value.id);
        sendDialog.value = false;
        refreshList();
    } finally {
        actionLoading.value = false;
    }
};

const promptCancel = (invoice: Invoice) => {
    selectedInvoice.value = invoice;
    cancelDialog.value = true;
};

const confirmCancel = async () => {
    if (!selectedInvoice.value) return;
    actionLoading.value = true;
    try {
        await invoiceStore.cancelInvoice(selectedInvoice.value.id);
        cancelDialog.value = false;
        refreshList();
    } finally {
        actionLoading.value = false;
    }
};

const promptDelete = (invoice: Invoice) => {
    selectedInvoice.value = invoice;
    deleteDialog.value = true;
};

const confirmDelete = async () => {
    if (!selectedInvoice.value) return;
    actionLoading.value = true;
    try {
        await invoiceStore.deleteInvoice(selectedInvoice.value.id);
        deleteDialog.value = false;
        refreshList();
    } finally {
        actionLoading.value = false;
    }
};

const downloadPdf = async (invoice: Invoice) => {
    try {
        await invoiceStore.downloadPdf(invoice.id, invoice.invoice_number);
        snackbarText.value = `Downloaded PDF for ${invoice.invoice_number}.`;
        snackbarColor.value = 'success';
        snackbar.value = true;
    } catch {
        snackbarText.value = invoiceStore.error || 'Failed to download PDF.';
        snackbarColor.value = 'error';
        snackbar.value = true;
    }
};

const openEmailDialog = (invoice: Invoice) => {
    selectedInvoice.value = invoice;
    const snap = invoice.customer_snapshot || invoice.customer;
    const companyName = companyStore.currentCompany?.name || 'BillFlow';

    emailPayload.recipient_email = snap?.email || '';
    emailPayload.subject = `Invoice ${invoice.invoice_number} from ${companyName}`;
    emailPayload.message = `Please find attached your invoice ${invoice.invoice_number}. Please let us know if you have any questions.`;

    emailDialog.value = true;
};

const handleSendEmail = async () => {
    if (!selectedInvoice.value || !emailPayload.recipient_email) return;
    actionLoading.value = true;
    try {
        await invoiceStore.sendEmail(selectedInvoice.value.id, {
            recipient_email: emailPayload.recipient_email,
            subject: emailPayload.subject,
            message: emailPayload.message,
        });
        emailDialog.value = false;
        snackbarText.value = `Invoice email queued for delivery to ${emailPayload.recipient_email}.`;
        snackbarColor.value = 'success';
        snackbar.value = true;
        refreshList();
    } catch {
        snackbarText.value = invoiceStore.error || 'Failed to queue invoice email.';
        snackbarColor.value = 'error';
        snackbar.value = true;
    } finally {
        actionLoading.value = false;
    }
};

onMounted(() => {
    invoiceStore.fetchInvoices();
    customerStore.fetchCustomers();
});
</script>
