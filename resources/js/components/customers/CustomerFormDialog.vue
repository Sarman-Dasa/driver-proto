<template>
    <v-dialog
        :model-value="modelValue"
        max-width="720"
        persistent
        scrollable
        @update:model-value="$emit('update:modelValue', $event)"
    >
        <v-card class="rounded-xl overflow-hidden">
            <!-- Dialog Header -->
            <v-card-item class="py-4 border-b">
                <template #prepend>
                    <v-avatar color="primary" variant="tonal" size="40" class="mr-3">
                        <v-icon :icon="isEdit ? 'mdi-account-edit' : 'mdi-account-plus'" color="primary"></v-icon>
                    </v-avatar>
                </template>
                <v-card-title class="text-h6 font-weight-bold">
                    {{ isEdit ? 'Edit Customer' : 'Add New Customer' }}
                </v-card-title>
                <v-card-subtitle class="text-caption text-medium-emphasis">
                    {{ isEdit ? 'Update customer details and billing information' : 'Create a new customer profile for your invoices' }}
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
                    <!-- General Details Section -->
                    <div class="text-subtitle-2 font-weight-bold mb-3 d-flex align-center gap-2 text-primary">
                        <v-icon icon="mdi-badge-account-outline" size="18"></v-icon>
                        General Information
                    </div>

                    <v-row dense>
                        <v-col cols="12" sm="6">
                            <v-text-field
                                v-model="form.name"
                                label="Customer / Contact Name *"
                                placeholder="e.g. Jane Doe"
                                variant="outlined"
                                density="comfortable"
                                :rules="[rules.required]"
                                class="mb-2"
                            ></v-text-field>
                        </v-col>

                        <v-col cols="12" sm="6">
                            <v-text-field
                                v-model="form.company_name"
                                label="Company Name"
                                placeholder="e.g. Acme Corporation"
                                variant="outlined"
                                density="comfortable"
                                class="mb-2"
                            ></v-text-field>
                        </v-col>

                        <v-col cols="12" sm="6">
                            <v-text-field
                                v-model="form.email"
                                label="Email Address"
                                placeholder="billing@example.com"
                                type="email"
                                variant="outlined"
                                density="comfortable"
                                :rules="[rules.email]"
                                class="mb-2"
                            ></v-text-field>
                        </v-col>

                        <v-col cols="12" sm="6">
                            <v-text-field
                                v-model="form.phone"
                                label="Phone Number"
                                placeholder="+1 (555) 000-0000"
                                variant="outlined"
                                density="comfortable"
                                class="mb-2"
                            ></v-text-field>
                        </v-col>

                        <v-col cols="12">
                            <v-text-field
                                v-model="form.tax_number"
                                label="Tax ID / VAT Number"
                                placeholder="e.g. EU123456789 or EIN-123456"
                                variant="outlined"
                                density="comfortable"
                                class="mb-4"
                            ></v-text-field>
                        </v-col>
                    </v-row>

                    <v-divider class="my-4"></v-divider>

                    <!-- Billing Address Section -->
                    <div class="text-subtitle-2 font-weight-bold mb-3 d-flex align-center gap-2 text-primary">
                        <v-icon icon="mdi-map-marker-outline" size="18"></v-icon>
                        Billing Address
                    </div>

                    <v-row dense>
                        <v-col cols="12">
                            <v-textarea
                                v-model="form.billing_address"
                                label="Street Address"
                                placeholder="123 Business Parkway, Suite 400"
                                variant="outlined"
                                density="comfortable"
                                rows="2"
                                auto-grow
                                class="mb-2"
                            ></v-textarea>
                        </v-col>

                        <v-col cols="12" sm="6">
                            <v-text-field
                                v-model="form.billing_city"
                                label="City"
                                placeholder="San Francisco"
                                variant="outlined"
                                density="comfortable"
                                class="mb-2"
                            ></v-text-field>
                        </v-col>

                        <v-col cols="12" sm="6">
                            <v-text-field
                                v-model="form.billing_state"
                                label="State / Province / Region"
                                placeholder="California"
                                variant="outlined"
                                density="comfortable"
                                class="mb-2"
                            ></v-text-field>
                        </v-col>

                        <v-col cols="12" sm="6">
                            <v-text-field
                                v-model="form.billing_postal_code"
                                label="Postal / ZIP Code"
                                placeholder="94107"
                                variant="outlined"
                                density="comfortable"
                                class="mb-2"
                            ></v-text-field>
                        </v-col>

                        <v-col cols="12" sm="6">
                            <v-text-field
                                v-model="form.billing_country"
                                label="Country (e.g. US, UK, DE)"
                                placeholder="US"
                                variant="outlined"
                                density="comfortable"
                                maxlength="3"
                                class="mb-2"
                            ></v-text-field>
                        </v-col>
                    </v-row>

                    <v-divider class="my-4"></v-divider>

                    <!-- Shipping Address Section -->
                    <div class="d-flex align-center justify-space-between mb-2">
                        <div class="text-subtitle-2 font-weight-bold d-flex align-center gap-2 text-primary">
                            <v-icon icon="mdi-truck-delivery-outline" size="18"></v-icon>
                            Shipping Address
                        </div>
                    </div>

                    <v-checkbox
                        v-model="form.shipping_same_as_billing"
                        label="Shipping address is the same as billing address"
                        density="compact"
                        color="primary"
                        hide-details
                        class="mb-3"
                    ></v-checkbox>

                    <v-expand-transition>
                        <v-row v-if="!form.shipping_same_as_billing" dense>
                            <v-col cols="12">
                                <v-textarea
                                    v-model="form.shipping_address"
                                    label="Shipping Street Address"
                                    placeholder="Warehouse 4, Dock 2"
                                    variant="outlined"
                                    density="comfortable"
                                    rows="2"
                                    auto-grow
                                    class="mb-2"
                                ></v-textarea>
                            </v-col>

                            <v-col cols="12" sm="6">
                                <v-text-field
                                    v-model="form.shipping_city"
                                    label="Shipping City"
                                    variant="outlined"
                                    density="comfortable"
                                    class="mb-2"
                                ></v-text-field>
                            </v-col>

                            <v-col cols="12" sm="6">
                                <v-text-field
                                    v-model="form.shipping_state"
                                    label="Shipping State / Province"
                                    variant="outlined"
                                    density="comfortable"
                                    class="mb-2"
                                ></v-text-field>
                            </v-col>

                            <v-col cols="12" sm="6">
                                <v-text-field
                                    v-model="form.shipping_postal_code"
                                    label="Shipping Postal / ZIP Code"
                                    variant="outlined"
                                    density="comfortable"
                                    class="mb-2"
                                ></v-text-field>
                            </v-col>

                            <v-col cols="12" sm="6">
                                <v-text-field
                                    v-model="form.shipping_country"
                                    label="Shipping Country (e.g. US)"
                                    variant="outlined"
                                    density="comfortable"
                                    maxlength="3"
                                    class="mb-2"
                                ></v-text-field>
                            </v-col>
                        </v-row>
                    </v-expand-transition>

                    <v-divider class="my-4"></v-divider>

                    <!-- Internal Notes -->
                    <div class="text-subtitle-2 font-weight-bold mb-3 d-flex align-center gap-2 text-primary">
                        <v-icon icon="mdi-note-text-outline" size="18"></v-icon>
                        Internal Notes
                    </div>

                    <v-textarea
                        v-model="form.notes"
                        label="Notes (Optional)"
                        placeholder="Internal billing preferences, contact instructions, or account details..."
                        variant="outlined"
                        density="comfortable"
                        rows="3"
                        auto-grow
                    ></v-textarea>
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
                    {{ isEdit ? 'Save Changes' : 'Create Customer' }}
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch } from 'vue';
import { useCustomerStore } from '@/stores/customers';
import type { Customer, CreateCustomerPayload, UpdateCustomerPayload } from '@/types';

const props = defineProps<{
    modelValue: boolean;
    customer?: Customer | null;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: boolean): void;
    (e: 'saved', customer: Customer): void;
}>();

const customerStore = useCustomerStore();
const formRef = ref<any>(null);
const saving = ref(false);
const errorMessage = ref('');

const isEdit = computed(() => Boolean(props.customer && props.customer.id));

const form = reactive({
    name: '',
    company_name: '',
    email: '',
    phone: '',
    tax_number: '',
    billing_address: '',
    billing_city: '',
    billing_state: '',
    billing_postal_code: '',
    billing_country: '',
    shipping_same_as_billing: true,
    shipping_address: '',
    shipping_city: '',
    shipping_state: '',
    shipping_postal_code: '',
    shipping_country: '',
    notes: '',
});

const rules = {
    required: (v: string) => !!v?.trim() || 'This field is required.',
    email: (v: string) => !v || /.+@.+\..+/.test(v) || 'Must be a valid email address.',
};

const resetForm = () => {
    form.name = '';
    form.company_name = '';
    form.email = '';
    form.phone = '';
    form.tax_number = '';
    form.billing_address = '';
    form.billing_city = '';
    form.billing_state = '';
    form.billing_postal_code = '';
    form.billing_country = '';
    form.shipping_same_as_billing = true;
    form.shipping_address = '';
    form.shipping_city = '';
    form.shipping_state = '';
    form.shipping_postal_code = '';
    form.shipping_country = '';
    form.notes = '';
    errorMessage.value = '';
};

watch(
    () => props.modelValue,
    (open) => {
        if (!open) {
            return;
        }

        errorMessage.value = '';
        if (props.customer) {
            form.name = props.customer.name || '';
            form.company_name = props.customer.company_name || '';
            form.email = props.customer.email || '';
            form.phone = props.customer.phone || '';
            form.tax_number = props.customer.tax_number || '';
            form.billing_address = props.customer.billing_address || '';
            form.billing_city = props.customer.billing_city || '';
            form.billing_state = props.customer.billing_state || '';
            form.billing_postal_code = props.customer.billing_postal_code || '';
            form.billing_country = props.customer.billing_country || '';
            form.shipping_same_as_billing = props.customer.shipping_same_as_billing ?? true;
            form.shipping_address = props.customer.shipping_address || '';
            form.shipping_city = props.customer.shipping_city || '';
            form.shipping_state = props.customer.shipping_state || '';
            form.shipping_postal_code = props.customer.shipping_postal_code || '';
            form.shipping_country = props.customer.shipping_country || '';
            form.notes = props.customer.notes || '';
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
        const payload: CreateCustomerPayload | UpdateCustomerPayload = {
            name: form.name.trim(),
            company_name: form.company_name.trim() || null,
            email: form.email.trim() || null,
            phone: form.phone.trim() || null,
            tax_number: form.tax_number.trim() || null,
            billing_address: form.billing_address.trim() || null,
            billing_city: form.billing_city.trim() || null,
            billing_state: form.billing_state.trim() || null,
            billing_postal_code: form.billing_postal_code.trim() || null,
            billing_country: form.billing_country.trim() ? form.billing_country.trim().toUpperCase() : null,
            shipping_same_as_billing: form.shipping_same_as_billing,
            shipping_address: form.shipping_same_as_billing ? null : form.shipping_address.trim() || null,
            shipping_city: form.shipping_same_as_billing ? null : form.shipping_city.trim() || null,
            shipping_state: form.shipping_same_as_billing ? null : form.shipping_state.trim() || null,
            shipping_postal_code: form.shipping_same_as_billing ? null : form.shipping_postal_code.trim() || null,
            shipping_country: form.shipping_same_as_billing ? null : form.shipping_country.trim() ? form.shipping_country.trim().toUpperCase() : null,
            notes: form.notes.trim() || null,
        };

        let result: Customer;
        if (isEdit.value && props.customer) {
            result = await customerStore.updateCustomer(props.customer.id, payload);
        } else {
            result = await customerStore.createCustomer(payload as CreateCustomerPayload);
        }

        emit('saved', result);
        closeDialog();
    } catch (err: any) {
        if (err.response?.status === 403) {
            errorMessage.value = err.response?.data?.message || 'Plan limit reached. Upgrade your plan to add more customers.';
        } else if (err.response?.data?.message) {
            errorMessage.value = err.response.data.message;
        } else {
            errorMessage.value = 'Failed to save customer. Please try again.';
        }
    } finally {
        saving.value = false;
    }
};
</script>
