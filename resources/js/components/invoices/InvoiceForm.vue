<template>
    <v-form ref="formRef" @submit.prevent="handleSubmit">
        <!-- Error Alert -->
        <v-alert
            v-if="errorMessage"
            type="error"
            variant="tonal"
            density="comfortable"
            class="mb-6 rounded-lg"
            closable
            @click:close="errorMessage = ''"
        >
            {{ errorMessage }}
        </v-alert>

        <v-row>
            <!-- Left Column: Primary Invoice Info -->
            <v-col cols="12" md="8">
                <!-- Customer & Dates Card -->
                <v-card class="rounded-xl pa-6 mb-6 border" elevation="0">
                    <div class="text-subtitle-1 font-weight-bold mb-4 d-flex align-center">
                        <v-icon icon="mdi-account-outline" class="mr-2" color="primary"></v-icon>
                        Customer & Invoice Details
                    </div>

                    <v-row dense>
                        <!-- Customer Selection -->
                        <v-col cols="12">
                            <label class="text-caption font-weight-bold text-medium-emphasis mb-1 d-block">
                                Customer <span class="text-error">*</span>
                            </label>
                            <v-autocomplete
                                v-model="form.customer_id"
                                :items="customerOptions"
                                placeholder="Select a customer"
                                variant="outlined"
                                density="comfortable"
                                :loading="loadingCustomers"
                                :rules="[rules.requiredCustomer]"
                                class="rounded-lg mb-2"
                                clearable
                            ></v-autocomplete>
                        </v-col>

                        <!-- Issue Date -->
                        <v-col cols="12" sm="6">
                            <label class="text-caption font-weight-bold text-medium-emphasis mb-1 d-block">
                                Issue Date <span class="text-error">*</span>
                            </label>
                            <v-text-field
                                v-model="form.issue_date"
                                type="date"
                                variant="outlined"
                                density="comfortable"
                                :rules="[rules.required]"
                                class="rounded-lg mb-2"
                            ></v-text-field>
                        </v-col>

                        <!-- Due Date -->
                        <v-col cols="12" sm="6">
                            <label class="text-caption font-weight-bold text-medium-emphasis mb-1 d-block">
                                Due Date <span class="text-error">*</span>
                            </label>
                            <v-text-field
                                v-model="form.due_date"
                                type="date"
                                variant="outlined"
                                density="comfortable"
                                :rules="[rules.required, rules.dueDateAfterIssue]"
                                class="rounded-lg mb-2"
                            ></v-text-field>
                        </v-col>
                    </v-row>
                </v-card>

                <!-- Line Items Card -->
                <v-card class="rounded-xl pa-6 mb-6 border" elevation="0">
                    <div class="d-flex justify-space-between align-center mb-4">
                        <div class="text-subtitle-1 font-weight-bold d-flex align-center">
                            <v-icon icon="mdi-format-list-bulleted" class="mr-2" color="primary"></v-icon>
                            Line Items <span class="text-error ml-1">*</span>
                        </div>
                        <v-btn
                            color="primary"
                            variant="tonal"
                            size="small"
                            prepend-icon="mdi-plus"
                            @click="addItemRow"
                        >
                            Add Item
                        </v-btn>
                    </div>

                    <!-- Line Items Table / List -->
                    <div v-for="(item, index) in form.items" :key="index" class="line-item-row pa-4 mb-4 rounded-lg border bg-surface">
                        <div class="d-flex justify-space-between align-center mb-2">
                            <span class="text-caption font-weight-bold text-medium-emphasis">
                                Item #{{ index + 1 }}
                            </span>
                            <v-btn
                                v-if="form.items.length > 1"
                                icon="mdi-delete-outline"
                                variant="text"
                                size="x-small"
                                color="error"
                                title="Remove line item"
                                @click="removeItemRow(index)"
                            ></v-btn>
                        </div>

                        <v-row dense>
                            <!-- Catalog Autofill Selector -->
                            <v-col cols="12" sm="5">
                                <label class="text-caption font-weight-medium text-medium-emphasis mb-1 d-block">
                                    Catalog Item (Optional)
                                </label>
                                <v-autocomplete
                                    v-model="item.product_id"
                                    :items="productOptions"
                                    placeholder="Autofill from catalog"
                                    variant="outlined"
                                    density="compact"
                                    clearable
                                    class="mb-2"
                                    @update:model-value="(val) => onProductSelect(index, val)"
                                ></v-autocomplete>
                            </v-col>

                            <!-- Item Name -->
                            <v-col cols="12" sm="7">
                                <label class="text-caption font-weight-medium text-medium-emphasis mb-1 d-block">
                                    Item Name <span class="text-error">*</span>
                                </label>
                                <v-text-field
                                    v-model="item.product_name"
                                    placeholder="e.g. Website Development"
                                    variant="outlined"
                                    density="compact"
                                    :rules="[rules.required]"
                                    class="mb-2"
                                ></v-text-field>
                            </v-col>

                            <!-- Description -->
                            <v-col cols="12">
                                <label class="text-caption font-weight-medium text-medium-emphasis mb-1 d-block">
                                    Description
                                </label>
                                <v-text-field
                                    v-model="item.description"
                                    placeholder="Additional item specifications or deliverables"
                                    variant="outlined"
                                    density="compact"
                                    class="mb-2"
                                ></v-text-field>
                            </v-col>

                            <!-- Quantity -->
                            <v-col cols="6" sm="3">
                                <label class="text-caption font-weight-medium text-medium-emphasis mb-1 d-block">
                                    Quantity <span class="text-error">*</span>
                                </label>
                                <v-text-field
                                    v-model.number="item.quantity"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    variant="outlined"
                                    density="compact"
                                    :rules="[rules.requiredPositive]"
                                    class="mb-2"
                                ></v-text-field>
                            </v-col>

                            <!-- Unit -->
                            <v-col cols="6" sm="3">
                                <label class="text-caption font-weight-medium text-medium-emphasis mb-1 d-block">
                                    Unit
                                </label>
                                <v-text-field
                                    v-model="item.unit"
                                    placeholder="e.g. hour, pcs"
                                    variant="outlined"
                                    density="compact"
                                    class="mb-2"
                                ></v-text-field>
                            </v-col>

                            <!-- Unit Price (in Dollars) -->
                            <v-col cols="6" sm="3">
                                <label class="text-caption font-weight-medium text-medium-emphasis mb-1 d-block">
                                    Unit Price ($) <span class="text-error">*</span>
                                </label>
                                <v-text-field
                                    v-model.number="item.unit_price_dollars"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    variant="outlined"
                                    density="compact"
                                    :rules="[rules.requiredNonNegative]"
                                    class="mb-2"
                                ></v-text-field>
                            </v-col>

                            <!-- Tax Rate (%) -->
                            <v-col cols="6" sm="3">
                                <label class="text-caption font-weight-medium text-medium-emphasis mb-1 d-block">
                                    Tax Rate (%)
                                </label>
                                <v-text-field
                                    v-model.number="item.tax_rate"
                                    type="number"
                                    step="0.1"
                                    min="0"
                                    max="100"
                                    variant="outlined"
                                    density="compact"
                                    :rules="[rules.taxRange]"
                                    class="mb-2"
                                ></v-text-field>
                            </v-col>
                        </v-row>

                        <!-- Line Calculation Preview -->
                        <div class="d-flex justify-end align-center mt-1 text-caption text-medium-emphasis">
                            <span>Subtotal: <strong>${{ calculateLineSubtotal(item).toFixed(2) }}</strong></span>
                            <span class="mx-2">•</span>
                            <span>Tax: <strong>${{ calculateLineTax(item).toFixed(2) }}</strong></span>
                            <span class="mx-2">•</span>
                            <span>Total: <strong class="text-primary">${{ calculateLineTotal(item).toFixed(2) }}</strong></span>
                        </div>
                    </div>

                    <div v-if="form.items.length === 0" class="text-center py-6 text-medium-emphasis border rounded-lg">
                        <v-icon icon="mdi-alert-circle-outline" class="mb-2" size="28"></v-icon>
                        <p class="text-body-2">Please add at least one line item to the invoice.</p>
                        <v-btn color="primary" variant="outlined" size="small" @click="addItemRow">
                            Add Line Item
                        </v-btn>
                    </div>
                </v-card>

                <!-- Notes & Terms Card -->
                <v-card class="rounded-xl pa-6 mb-6 border" elevation="0">
                    <div class="text-subtitle-1 font-weight-bold mb-4 d-flex align-center">
                        <v-icon icon="mdi-note-text-outline" class="mr-2" color="primary"></v-icon>
                        Notes & Terms
                    </div>

                    <v-row dense>
                        <v-col cols="12">
                            <label class="text-caption font-weight-medium text-medium-emphasis mb-1 d-block">
                                Notes (Visible to Client)
                            </label>
                            <v-textarea
                                v-model="form.notes"
                                placeholder="Thank you for your business!"
                                variant="outlined"
                                density="comfortable"
                                rows="2"
                                auto-grow
                                class="mb-2"
                            ></v-textarea>
                        </v-col>

                        <v-col cols="12">
                            <label class="text-caption font-weight-medium text-medium-emphasis mb-1 d-block">
                                Payment Terms
                            </label>
                            <v-textarea
                                v-model="form.terms"
                                placeholder="Payment due within 30 days of issue date."
                                variant="outlined"
                                density="comfortable"
                                rows="2"
                                auto-grow
                            ></v-textarea>
                        </v-col>
                    </v-row>
                </v-card>
            </v-col>

            <!-- Right Column: Discounts & Financial Summary -->
            <v-col cols="12" md="4">
                <!-- Summary Card -->
                <v-card class="rounded-xl pa-6 mb-6 border bg-surface" elevation="0" style="position: sticky; top: 80px;">
                    <div class="text-subtitle-1 font-weight-bold mb-4 d-flex align-center">
                        <v-icon icon="mdi-calculator-variant-outline" class="mr-2" color="primary"></v-icon>
                        Invoice Summary
                    </div>

                    <!-- Discount Options -->
                    <div class="mb-5">
                        <label class="text-caption font-weight-bold text-medium-emphasis mb-2 d-block">
                            Discount
                        </label>
                        <v-btn-toggle
                            v-model="form.discount_type"
                            mandatory
                            density="compact"
                            color="primary"
                            variant="outlined"
                            class="mb-3 w-100"
                        >
                            <v-btn value="none" class="flex-grow-1" size="small">None</v-btn>
                            <v-btn value="percentage" class="flex-grow-1" size="small">% Off</v-btn>
                            <v-btn value="fixed" class="flex-grow-1" size="small">$ Off</v-btn>
                        </v-btn-toggle>

                        <v-text-field
                            v-if="form.discount_type !== 'none'"
                            v-model.number="form.discount_value"
                            type="number"
                            step="0.01"
                            min="0"
                            :max="form.discount_type === 'percentage' ? 100 : undefined"
                            :label="form.discount_type === 'percentage' ? 'Discount Percentage (%)' : 'Discount Amount ($)'"
                            variant="outlined"
                            density="compact"
                            :rules="[rules.requiredNonNegative]"
                            class="mb-2"
                        ></v-text-field>
                    </div>

                    <v-divider class="my-4"></v-divider>

                    <!-- Breakdown Table -->
                    <div class="summary-breakdown text-body-2">
                        <div class="d-flex justify-space-between mb-2">
                            <span class="text-medium-emphasis">Subtotal</span>
                            <span class="font-weight-medium">${{ previewTotals.subtotal.toFixed(2) }}</span>
                        </div>

                        <div v-if="previewTotals.discount > 0" class="d-flex justify-space-between mb-2 text-success">
                            <span>Discount</span>
                            <span class="font-weight-medium">-${{ previewTotals.discount.toFixed(2) }}</span>
                        </div>

                        <div class="d-flex justify-space-between mb-2">
                            <span class="text-medium-emphasis">Total Tax</span>
                            <span class="font-weight-medium">${{ previewTotals.tax.toFixed(2) }}</span>
                        </div>

                        <v-divider class="my-3"></v-divider>

                        <div class="d-flex justify-space-between align-center mb-4">
                            <span class="text-h6 font-weight-bold">Total Due</span>
                            <span class="text-h6 font-weight-bold text-primary">
                                ${{ previewTotals.total.toFixed(2) }}
                            </span>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="d-flex flex-column gap-2 mt-4">
                        <v-btn
                            color="primary"
                            size="large"
                            rounded="lg"
                            type="submit"
                            :loading="submitting"
                            class="font-weight-bold text-none mb-2"
                            prepend-icon="mdi-content-save-outline"
                        >
                            {{ isEdit ? 'Update Draft' : 'Save as Draft' }}
                        </v-btn>

                        <v-btn
                            variant="text"
                            rounded="lg"
                            :to="{ name: 'invoices' }"
                            class="text-none"
                        >
                            Cancel
                        </v-btn>
                    </div>
                </v-card>
            </v-col>
        </v-row>
    </v-form>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useCustomerStore } from '@/stores/customers';
import { useProductStore } from '@/stores/products';
import { useInvoiceStore } from '@/stores/invoices';
import { useCompanyStore } from '@/stores/company';
import type { Invoice, Customer, Product, DiscountType, CreateInvoicePayload } from '@/types';

interface Props {
    initialInvoice?: Invoice | null;
    isEdit?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    initialInvoice: null,
    isEdit: false,
});

const router = useRouter();
const customerStore = useCustomerStore();
const productStore = useProductStore();
const invoiceStore = useInvoiceStore();
const companyStore = useCompanyStore();

const formRef = ref();
const submitting = ref(false);
const errorMessage = ref('');
const loadingCustomers = ref(false);

const customers = computed(() => customerStore.customers);
const products = computed(() => productStore.products);

const customerOptions = computed(() => {
    return customers.value.map((c) => ({
        title: c.name,
        value: c.id,
        subtitle: c.company_name || c.email || undefined,
    }));
});

const productOptions = computed(() => {
    return products.value.map((p) => ({
        title: p.name,
        value: p.id,
        subtitle: `${p.formatted_price || ('$' + (p.price / 100).toFixed(2))} • ${p.type === 'service' ? 'Service' : 'Product'}`,
    }));
});

interface FormItem {
    product_id: number | null;
    product_name: string;
    description: string;
    quantity: number;
    unit: string;
    unit_price_dollars: number;
    tax_rate: number;
}

const defaultIssueDate = new Date().toISOString().substring(0, 10);
const defaultDueDate = new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().substring(0, 10);

const form = reactive({
    customer_id: null as number | null,
    issue_date: defaultIssueDate,
    due_date: defaultDueDate,
    currency: 'USD',
    discount_type: 'none' as DiscountType,
    discount_value: 0,
    notes: '',
    terms: 'Payment due within 30 days of invoice date.',
    items: [
        {
            product_id: null,
            product_name: '',
            description: '',
            quantity: 1,
            unit: 'piece',
            unit_price_dollars: 0,
            tax_rate: 0,
        },
    ] as FormItem[],
});

// Initialize form from props if edit mode
onMounted(async () => {
    loadingCustomers.value = true;
    try {
        await Promise.all([
            customerStore.fetchCustomers(),
            productStore.fetchProducts(),
        ]);
    } finally {
        loadingCustomers.value = false;
    }

    if (props.initialInvoice) {
        form.customer_id = props.initialInvoice.customer_id;
        form.issue_date = props.initialInvoice.issue_date;
        form.due_date = props.initialInvoice.due_date;
        form.currency = props.initialInvoice.currency || 'USD';
        form.discount_type = props.initialInvoice.discount_type || 'none';
        form.discount_value = props.initialInvoice.discount_value || 0;
        form.notes = props.initialInvoice.notes || '';
        form.terms = props.initialInvoice.terms || '';

        if (props.initialInvoice.items && props.initialInvoice.items.length > 0) {
            form.items = props.initialInvoice.items.map((it) => ({
                product_id: it.product_id ?? null,
                product_name: it.product_name,
                description: it.description || '',
                quantity: it.quantity,
                unit: it.unit || '',
                unit_price_dollars: it.unit_price_decimal ?? roundDollars(it.unit_price / 100),
                tax_rate: it.tax_rate,
            }));
        }
    } else if (companyStore.currentCompany) {
        form.currency = companyStore.currentCompany.currency || 'USD';
    }
});

const roundDollars = (amount: number): number => {
    return Math.round((amount + Number.EPSILON) * 100) / 100;
};

const addItemRow = () => {
    form.items.push({
        product_id: null,
        product_name: '',
        description: '',
        quantity: 1,
        unit: 'piece',
        unit_price_dollars: 0,
        tax_rate: 0,
    });
};

const removeItemRow = (index: number) => {
    if (form.items.length > 1) {
        form.items.splice(index, 1);
    }
};

const onProductSelect = (index: number, productId: number | null) => {
    if (!productId) return;
    const selected = products.value.find((p) => p.id === productId);
    if (selected) {
        const item = form.items[index];
        item.product_name = selected.name;
        item.description = selected.description || '';
        item.unit = selected.unit || (selected.type === 'service' ? 'hour' : 'piece');
        item.unit_price_dollars = selected.price_decimal ?? (selected.price / 100);
        item.tax_rate = selected.tax_rate ?? 0;
    }
};

// Client-side calculations for preview
const calculateLineSubtotal = (item: FormItem): number => {
    const qty = Math.max(0, item.quantity || 0);
    const price = Math.max(0, item.unit_price_dollars || 0);
    return roundDollars(qty * price);
};

const calculateLineTax = (item: FormItem): number => {
    const subtotal = calculateLineSubtotal(item);
    const rate = Math.max(0, Math.min(100, item.tax_rate || 0));
    return roundDollars(subtotal * (rate / 100));
};

const calculateLineTotal = (item: FormItem): number => {
    return roundDollars(calculateLineSubtotal(item) + calculateLineTax(item));
};

const previewTotals = computed(() => {
    let subtotal = 0;
    let tax = 0;

    for (const item of form.items) {
        subtotal += calculateLineSubtotal(item);
        tax += calculateLineTax(item);
    }

    subtotal = roundDollars(subtotal);
    tax = roundDollars(tax);

    let discount = 0;
    if (form.discount_type === 'percentage') {
        const pct = Math.max(0, Math.min(100, form.discount_value || 0));
        discount = roundDollars(subtotal * (pct / 100));
    } else if (form.discount_type === 'fixed') {
        discount = Math.min(subtotal, Math.max(0, form.discount_value || 0));
    }

    const total = Math.max(0, roundDollars(subtotal - discount + tax));

    return {
        subtotal,
        discount,
        tax,
        total,
    };
});

const rules = {
    required: (val: any) => (val !== null && val !== undefined && val !== '') || 'This field is required',
    requiredCustomer: (val: any) => !!val || 'Please select a customer',
    requiredPositive: (val: number) => (val > 0) || 'Must be greater than 0',
    requiredNonNegative: (val: number) => (val >= 0) || 'Cannot be negative',
    taxRange: (val: number) => (val >= 0 && val <= 100) || 'Tax rate must be between 0% and 100%',
    dueDateAfterIssue: (val: string) => {
        if (!val || !form.issue_date) return true;
        return val >= form.issue_date || 'Due date must be on or after issue date';
    },
};

const handleSubmit = async () => {
    errorMessage.value = '';

    const { valid } = await formRef.value.validate();
    if (!valid) {
        errorMessage.value = 'Please review and fix validation errors before saving.';
        return;
    }

    if (form.items.length === 0) {
        errorMessage.value = 'At least one line item is required.';
        return;
    }

    submitting.value = true;
    try {
        const payload: CreateInvoicePayload = {
            customer_id: Number(form.customer_id),
            issue_date: form.issue_date,
            due_date: form.due_date,
            currency: form.currency,
            discount_type: form.discount_type,
            discount_value: form.discount_type !== 'none' ? Number(form.discount_value) : 0,
            notes: form.notes || null,
            terms: form.terms || null,
            items: form.items.map((it) => ({
                product_id: it.product_id || null,
                product_name: it.product_name,
                description: it.description || null,
                quantity: Number(it.quantity),
                unit: it.unit || null,
                unit_price: Math.round(it.unit_price_dollars * 100), // convert to cents
                tax_rate: Number(it.tax_rate || 0),
            })),
        };

        if (props.isEdit && props.initialInvoice) {
            const updated = await invoiceStore.updateInvoice(props.initialInvoice.id, payload);
            router.push({ name: 'invoices.detail', params: { invoice: updated.id } });
        } else {
            const created = await invoiceStore.createInvoice(payload);
            router.push({ name: 'invoices.detail', params: { invoice: created.id } });
        }
    } catch (err: any) {
        const responseData = err?.response?.data;
        if (responseData?.errors) {
            const firstErrorKey = Object.keys(responseData.errors)[0];
            errorMessage.value = responseData.errors[firstErrorKey][0];
        } else {
            errorMessage.value = responseData?.message || 'Failed to save invoice.';
        }
    } finally {
        submitting.value = false;
    }
};
</script>

<style scoped>
.line-item-row {
    transition: background-color 0.15s ease;
}
.line-item-row:hover {
    background-color: rgba(var(--v-theme-primary), 0.02);
}
</style>
