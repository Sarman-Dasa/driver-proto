<template>
    <v-dialog
        :model-value="modelValue"
        max-width="680"
        persistent
        scrollable
        @update:model-value="$emit('update:modelValue', $event)"
    >
        <v-card class="rounded-xl overflow-hidden">
            <!-- Dialog Header -->
            <v-card-item class="py-4 border-b">
                <template #prepend>
                    <v-avatar color="primary" variant="tonal" size="40" class="mr-3">
                        <v-icon :icon="form.type === 'service' ? 'mdi-briefcase-outline' : 'mdi-package-variant-closed'" color="primary"></v-icon>
                    </v-avatar>
                </template>
                <v-card-title class="text-h6 font-weight-bold">
                    {{ isEdit ? 'Edit ' + (form.type === 'service' ? 'Service' : 'Product') : 'Add ' + (form.type === 'service' ? 'Service' : 'Product') }}
                </v-card-title>
                <v-card-subtitle class="text-caption text-medium-emphasis">
                    {{ isEdit ? 'Update item details, pricing, and tax settings' : 'Create a reusable catalog item for client billing and invoices' }}
                </v-card-subtitle>
                <template #append>
                    <v-btn
                        icon="mdi-close"
                        variant="text"
                        size="small"
                        density="comfortable"
                        @click="closeDialog"
                    ></v-btn>
                </template>
            </v-card-item>

            <!-- Dialog Body -->
            <v-card-text class="pa-6" style="max-height: 70vh;">
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

                <v-form ref="formRef" @submit.prevent="handleSubmit">
                    <!-- Type Selector -->
                    <div class="mb-5">
                        <label class="text-caption font-weight-bold text-uppercase text-medium-emphasis d-block mb-2">
                            Item Classification
                        </label>
                        <v-btn-toggle
                            v-model="form.type"
                            mandatory
                            density="comfortable"
                            color="primary"
                            variant="outlined"
                            divided
                            class="rounded-lg w-100"
                        >
                            <v-btn value="product" class="flex-grow-1 text-none font-weight-bold">
                                <v-icon icon="mdi-package-variant-closed" size="18" class="mr-2"></v-icon>
                                Product (Physical / Tangible)
                            </v-btn>
                            <v-btn value="service" class="flex-grow-1 text-none font-weight-bold">
                                <v-icon icon="mdi-briefcase-outline" size="18" class="mr-2"></v-icon>
                                Service (Hourly / Consulting / Digital)
                            </v-btn>
                        </v-btn-toggle>
                    </div>

                    <!-- Primary Details -->
                    <v-row dense>
                        <v-col cols="12" sm="8">
                            <v-text-field
                                v-model="form.name"
                                :label="form.type === 'service' ? 'Service Name *' : 'Product Name *'"
                                :placeholder="form.type === 'service' ? 'e.g. Website Consulting' : 'e.g. Standard Wireless Keyboard'"
                                variant="outlined"
                                density="comfortable"
                                :rules="[rules.required]"
                                class="mb-2"
                            ></v-text-field>
                        </v-col>

                        <v-col cols="12" sm="4">
                            <v-text-field
                                v-model="form.sku"
                                label="SKU / Identifier"
                                placeholder="e.g. SRV-001"
                                variant="outlined"
                                density="comfortable"
                                class="mb-2"
                            ></v-text-field>
                        </v-col>

                        <v-col cols="12">
                            <v-textarea
                                v-model="form.description"
                                label="Description (Optional)"
                                placeholder="Detailed description to populate by default on invoice line items..."
                                variant="outlined"
                                density="comfortable"
                                rows="2"
                                auto-grow
                                class="mb-2"
                            ></v-textarea>
                        </v-col>
                    </v-row>

                    <v-divider class="my-4"></v-divider>

                    <!-- Pricing & Tax Section -->
                    <div class="text-subtitle-2 font-weight-bold mb-3 d-flex align-center gap-2 text-primary">
                        <v-icon icon="mdi-currency-usd" size="18"></v-icon>
                        Pricing & Tax Configuration
                    </div>

                    <v-row dense>
                        <v-col cols="12" sm="6">
                            <v-text-field
                                v-model="form.priceInput"
                                label="Unit Price *"
                                placeholder="0.00"
                                type="number"
                                step="0.01"
                                min="0"
                                prefix="$"
                                variant="outlined"
                                density="comfortable"
                                :rules="[rules.requiredPrice, rules.positivePrice]"
                                class="mb-2"
                            ></v-text-field>
                        </v-col>

                        <v-col cols="12" sm="6">
                            <v-text-field
                                v-model="form.currency"
                                label="Currency"
                                placeholder="USD"
                                variant="outlined"
                                density="comfortable"
                                maxlength="3"
                                class="mb-2 text-uppercase"
                            ></v-text-field>
                        </v-col>

                        <v-col cols="12" sm="6">
                            <v-text-field
                                v-model="form.tax_rate"
                                label="Tax Rate (%)"
                                placeholder="0"
                                type="number"
                                step="0.1"
                                min="0"
                                max="100"
                                suffix="%"
                                variant="outlined"
                                density="comfortable"
                                :rules="[rules.validTaxRate]"
                                class="mb-2"
                            ></v-text-field>
                        </v-col>

                        <v-col cols="12" sm="6">
                            <v-combobox
                                v-model="form.unit"
                                label="Billing Unit"
                                :items="commonUnits"
                                placeholder="e.g. hour, project, piece"
                                variant="outlined"
                                density="comfortable"
                                class="mb-2"
                            ></v-combobox>
                        </v-col>
                    </v-row>

                    <v-divider class="my-4"></v-divider>

                    <!-- Availability Status -->
                    <div class="d-flex align-center justify-space-between">
                        <div>
                            <div class="text-subtitle-2 font-weight-bold">Catalog Availability</div>
                            <div class="text-caption text-medium-emphasis">
                                Inactive items are hidden from new invoice creation while preserving historical records.
                            </div>
                        </div>

                        <v-switch
                            v-model="form.isActive"
                            color="primary"
                            density="compact"
                            hide-details
                            :label="form.isActive ? 'Active' : 'Inactive'"
                        ></v-switch>
                    </div>
                </v-form>
            </v-card-text>

            <!-- Dialog Actions -->
            <v-card-actions class="pa-4 bg-grey-lighten-4 border-t d-flex justify-end gap-2">
                <v-btn
                    variant="text"
                    color="medium-emphasis"
                    class="text-none font-weight-medium rounded-lg px-4"
                    :disabled="saving"
                    @click="closeDialog"
                >
                    Cancel
                </v-btn>

                <v-btn
                    color="primary"
                    variant="flat"
                    class="text-none font-weight-bold rounded-lg px-6"
                    :loading="saving"
                    @click="handleSubmit"
                >
                    {{ isEdit ? 'Save Changes' : 'Create Item' }}
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch } from 'vue';
import { useProductStore } from '@/stores/products';
import { useCompanyStore } from '@/stores/company';
import type { Product, ProductType, ProductStatus, CreateProductPayload, UpdateProductPayload } from '@/types';

const props = defineProps<{
    modelValue: boolean;
    product?: Product | null;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: boolean): void;
    (e: 'saved', product: Product): void;
}>();

const productStore = useProductStore();
const companyStore = useCompanyStore();
const formRef = ref<any>(null);
const saving = ref(false);
const errorMessage = ref('');

const isEdit = computed(() => Boolean(props.product && props.product.id));

const commonUnits = [
    'hour',
    'day',
    'month',
    'project',
    'piece',
    'license',
    'service',
    'unit',
];

const form = reactive({
    type: 'service' as ProductType,
    name: '',
    description: '',
    sku: '',
    priceInput: '',
    currency: 'USD',
    tax_rate: '0',
    unit: 'hour',
    isActive: true,
});

const rules = {
    required: (v: string) => !!v?.trim() || 'This field is required.',
    requiredPrice: (v: string) => (v !== '' && v !== null && v !== undefined) || 'Price is required.',
    positivePrice: (v: string) => Number(v) >= 0 || 'Price must be greater than or equal to 0.',
    validTaxRate: (v: string) => {
        if (!v) return true;
        const num = Number(v);
        return (num >= 0 && num <= 100) || 'Tax rate must be between 0% and 100%.';
    },
};

const resetForm = () => {
    form.type = 'service';
    form.name = '';
    form.description = '';
    form.sku = '';
    form.priceInput = '';
    form.currency = companyStore.currentCompany?.currency || 'USD';
    form.tax_rate = '0';
    form.unit = 'hour';
    form.isActive = true;
    errorMessage.value = '';
};

watch(
    () => form.type,
    (newType, oldType) => {
        // If user changed type and hasn't customized unit yet, adjust unit sensibly
        if (!isEdit.value && oldType) {
            if (newType === 'product' && form.unit === 'hour') {
                form.unit = 'piece';
            } else if (newType === 'service' && form.unit === 'piece') {
                form.unit = 'hour';
            }
        }
    }
);

watch(
    () => props.modelValue,
    (open) => {
        if (!open) {
            return;
        }

        errorMessage.value = '';
        if (props.product) {
            form.type = props.product.type || 'service';
            form.name = props.product.name || '';
            form.description = props.product.description || '';
            form.sku = props.product.sku || '';
            form.priceInput = String(props.product.price_decimal ?? (props.product.price / 100));
            form.currency = props.product.currency || companyStore.currentCompany?.currency || 'USD';
            form.tax_rate = String(props.product.tax_rate ?? 0);
            form.unit = props.product.unit || '';
            form.isActive = props.product.status === 'active';
        } else {
            resetForm();
        }
    },
    { immediate: true }
);

const closeDialog = () => {
    emit('update:modelValue', false);
};

const handleSubmit = async () => {
    if (formRef.value) {
        const { valid } = await formRef.value.validate();
        if (!valid) {
            return;
        }
    }

    saving.value = true;
    errorMessage.value = '';

    try {
        const parsedPrice = parseFloat(form.priceInput);
        const priceMinor = Math.round(parsedPrice * 100);

        const payload: CreateProductPayload | UpdateProductPayload = {
            type: form.type,
            name: form.name.trim(),
            description: form.description.trim() || null,
            sku: form.sku.trim() || null,
            price: priceMinor,
            currency: form.currency.trim() ? form.currency.trim().toUpperCase() : 'USD',
            tax_rate: form.tax_rate !== '' ? parseFloat(form.tax_rate) : 0,
            unit: form.unit?.trim() || null,
            status: (form.isActive ? 'active' : 'inactive') as ProductStatus,
        };

        let result: Product;
        if (isEdit.value && props.product) {
            result = await productStore.updateProduct(props.product.id, payload);
        } else {
            result = await productStore.createProduct(payload as CreateProductPayload);
        }

        emit('saved', result);
        closeDialog();
    } catch (err: any) {
        if (err.response?.status === 403) {
            errorMessage.value = err.response?.data?.message || 'Plan limit reached. Upgrade your plan to add more products and services.';
        } else if (err.response?.data?.message) {
            errorMessage.value = err.response.data.message;
        } else {
            errorMessage.value = 'Failed to save item. Please review the form and try again.';
        }
    } finally {
        saving.value = false;
    }
};
</script>
