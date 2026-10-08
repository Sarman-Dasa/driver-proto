<template>
    <v-container style="max-width: 800px" class="py-6">
        <div class="d-flex align-center justify-space-between mb-6">
            <div class="d-flex align-center gap-3">
                <v-btn
                    icon="mdi-arrow-left"
                    variant="text"
                    :to="{ name: 'companies' }"
                    class="mr-2"
                ></v-btn>
                <div>
                    <h1 class="text-h4 font-weight-bold tracking-tight">Workspace Settings</h1>
                    <p class="text-subtitle-1 text-medium-emphasis mt-1">
                        {{ company?.name || 'Workspace Configuration' }}
                    </p>
                </div>
            </div>

            <v-chip
                v-if="company"
                :color="company.is_active ? 'success' : 'error'"
                class="font-weight-bold"
                size="small"
                variant="flat"
            >
                {{ company.status }}
            </v-chip>
        </div>

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

        <v-alert
            v-if="successMessage"
            type="success"
            variant="tonal"
            density="comfortable"
            class="mb-6 rounded-lg"
            closable
            @click:close="successMessage = ''"
        >
            {{ successMessage }}
        </v-alert>

        <v-alert
            v-if="company && !company.is_owner"
            type="info"
            variant="tonal"
            density="comfortable"
            class="mb-6 rounded-lg"
            prepend-icon="mdi-shield-lock-outline"
        >
            You are viewing this workspace as a member. Only the company owner can modify workspace profile and status settings.
        </v-alert>

        <v-card v-if="company" class="pa-6 pa-sm-8 rounded-xl elevation-2 border mb-6">
            <v-form @submit.prevent="handleSave">
                <div class="d-flex align-center justify-space-between mb-4">
                    <div class="text-subtitle-2 font-weight-bold text-medium-emphasis text-uppercase">
                        Workspace Profile
                    </div>
                    <div class="text-caption text-medium-emphasis">
                        Created: {{ new Date(company.created_at).toLocaleDateString() }}
                    </div>
                </div>

                <v-row>
                    <v-col cols="12" sm="6" class="py-1">
                        <v-text-field
                            v-model="form.name"
                            label="Company Name *"
                            variant="outlined"
                            density="comfortable"
                            :disabled="!company.is_owner"
                            :error-messages="errors.name"
                            required
                        ></v-text-field>
                    </v-col>
                    <v-col cols="12" sm="6" class="py-1">
                        <v-text-field
                            :model-value="company.slug"
                            label="Company Identifier (Slug)"
                            variant="outlined"
                            density="comfortable"
                            disabled
                            hint="Company slugs remain stable permalinks"
                            persistent-hint
                        ></v-text-field>
                    </v-col>
                </v-row>

                <v-row class="mt-2">
                    <v-col cols="12" sm="6" class="py-1">
                        <v-text-field
                            v-model="form.email"
                            label="Business Email"
                            type="email"
                            variant="outlined"
                            density="comfortable"
                            :disabled="!company.is_owner"
                            :error-messages="errors.email"
                        ></v-text-field>
                    </v-col>
                    <v-col cols="12" sm="6" class="py-1">
                        <v-text-field
                            v-model="form.phone"
                            label="Phone Number"
                            variant="outlined"
                            density="comfortable"
                            :disabled="!company.is_owner"
                            :error-messages="errors.phone"
                        ></v-text-field>
                    </v-col>
                </v-row>

                <v-text-field
                    v-model="form.website"
                    label="Website"
                    variant="outlined"
                    density="comfortable"
                    :disabled="!company.is_owner"
                    :error-messages="errors.website"
                    class="mt-2 mb-2"
                ></v-text-field>

                <v-divider class="my-4"></v-divider>

                <div class="text-subtitle-2 font-weight-bold mb-4 text-medium-emphasis text-uppercase">
                    Address & Regional Details
                </div>

                <v-text-field
                    v-model="form.address"
                    label="Street Address"
                    variant="outlined"
                    density="comfortable"
                    :disabled="!company.is_owner"
                    class="mb-2"
                ></v-text-field>

                <v-row>
                    <v-col cols="12" sm="4" class="py-1">
                        <v-text-field
                            v-model="form.city"
                            label="City"
                            variant="outlined"
                            density="comfortable"
                            :disabled="!company.is_owner"
                        ></v-text-field>
                    </v-col>
                    <v-col cols="12" sm="4" class="py-1">
                        <v-text-field
                            v-model="form.state"
                            label="State / Province"
                            variant="outlined"
                            density="comfortable"
                            :disabled="!company.is_owner"
                        ></v-text-field>
                    </v-col>
                    <v-col cols="12" sm="4" class="py-1">
                        <v-text-field
                            v-model="form.postal_code"
                            label="Postal Code"
                            variant="outlined"
                            density="comfortable"
                            :disabled="!company.is_owner"
                        ></v-text-field>
                    </v-col>
                </v-row>

                <v-row class="mt-2">
                    <v-col cols="12" sm="4" class="py-1">
                        <v-text-field
                            v-model="form.country"
                            label="Country"
                            variant="outlined"
                            density="comfortable"
                            :disabled="!company.is_owner"
                        ></v-text-field>
                    </v-col>
                    <v-col cols="12" sm="4" class="py-1">
                        <v-select
                            v-model="form.currency"
                            label="Currency"
                            :items="currencies"
                            variant="outlined"
                            density="comfortable"
                            :disabled="!company.is_owner"
                        ></v-select>
                    </v-col>
                    <v-col cols="12" sm="4" class="py-1">
                        <v-text-field
                            v-model="form.tax_number"
                            label="Tax Number / VAT ID"
                            variant="outlined"
                            density="comfortable"
                            :disabled="!company.is_owner"
                        ></v-text-field>
                    </v-col>
                </v-row>

                <div v-if="company.is_owner" class="d-flex justify-end mt-6">
                    <v-btn
                        type="submit"
                        color="primary"
                        size="large"
                        elevation="2"
                        :loading="saving"
                        class="text-none font-weight-bold rounded-lg px-8"
                    >
                        Save Changes
                    </v-btn>
                </div>
            </v-form>
        </v-card>

        <!-- Danger Zone / Status Management -->
        <v-card v-if="company && company.is_owner" class="pa-6 rounded-xl border border-error bg-surface mb-6">
            <div class="text-subtitle-1 font-weight-bold text-error mb-1 d-flex align-center gap-2">
                <v-icon icon="mdi-alert-octagon-outline" color="error" class="mr-2"></v-icon>
                Workspace Lifecycle & Status
            </div>
            <p class="text-body-2 text-medium-emphasis mb-4">
                {{ company.is_active
                    ? 'Deactivating this workspace will block all member access until reactivated. Existing data and financial records are fully preserved.'
                    : 'This workspace is currently inactive. Reactivate it to restore member access and operations.'
                }}
            </p>

            <div class="d-flex align-center justify-space-between">
                <div>
                    <span class="text-body-2 font-weight-medium">Current Status: </span>
                    <v-chip
                        :color="company.is_active ? 'success' : 'error'"
                        size="small"
                        variant="tonal"
                        class="ml-2 font-weight-bold"
                    >
                        {{ company.status.toUpperCase() }}
                    </v-chip>
                </div>

                <v-btn
                    v-if="company.is_active"
                    color="error"
                    variant="tonal"
                    prepend-icon="mdi-power-standby"
                    class="text-none font-weight-bold rounded-lg"
                    @click="showDeactivateDialog = true"
                >
                    Deactivate Workspace
                </v-btn>

                <v-btn
                    v-else
                    color="success"
                    variant="flat"
                    prepend-icon="mdi-check-circle-outline"
                    class="text-none font-weight-bold rounded-lg"
                    :loading="statusLoading"
                    @click="handleReactivate"
                >
                    Reactivate Workspace
                </v-btn>
            </div>
        </v-card>

        <!-- Deactivation Confirmation Dialog -->
        <v-dialog v-model="showDeactivateDialog" max-width="480">
            <v-card class="pa-4 rounded-xl">
                <v-card-title class="d-flex align-center text-h6 font-weight-bold text-error">
                    <v-icon icon="mdi-alert-circle-outline" class="mr-2"></v-icon>
                    Deactivate Company?
                </v-card-title>
                <v-card-text class="text-body-1 text-medium-emphasis pt-2">
                    This will prevent users from accessing this company until it is reactivated. Existing data will remain preserved.
                </v-card-text>
                <v-card-actions class="d-flex justify-end gap-2 pa-3">
                    <v-btn
                        variant="text"
                        class="text-none rounded-lg"
                        @click="showDeactivateDialog = false"
                    >
                        Cancel
                    </v-btn>
                    <v-btn
                        color="error"
                        variant="flat"
                        class="text-none font-weight-bold rounded-lg px-4"
                        :loading="statusLoading"
                        @click="handleDeactivate"
                    >
                        Confirm Deactivation
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-container>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { useCompanyStore } from '@/stores/company';
import type { Company, UpdateCompanyPayload, ApiError } from '@/types';

const route = useRoute();
const companyStore = useCompanyStore();

const company = ref<Company | null>(null);
const saving = ref(false);
const statusLoading = ref(false);
const showDeactivateDialog = ref(false);
const errorMessage = ref('');
const successMessage = ref('');
const errors = reactive<Record<string, string[]>>({});

const currencies = ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'INR', 'JPY'];

const form = reactive<UpdateCompanyPayload>({
    name: '',
    email: '',
    phone: '',
    website: '',
    address: '',
    city: '',
    state: '',
    postal_code: '',
    country: '',
    tax_number: '',
    currency: 'USD',
    timezone: 'UTC',
});

const loadCompany = async () => {
    const companyId = Number(route.params.company);
    if (!companyId) return;

    try {
        const fetched = await companyStore.fetchCompany(companyId);
        company.value = fetched;
        form.name = fetched.name;
        form.email = fetched.email || '';
        form.phone = fetched.phone || '';
        form.website = fetched.website || '';
        form.address = fetched.address || '';
        form.city = fetched.city || '';
        form.state = fetched.state || '';
        form.postal_code = fetched.postal_code || '';
        form.country = fetched.country || '';
        form.tax_number = fetched.tax_number || '';
        form.currency = fetched.currency || 'USD';
        form.timezone = fetched.timezone || 'UTC';
    } catch (err: unknown) {
        const apiError = err as ApiError;
        errorMessage.value = apiError.message || 'Failed to load workspace settings.';
    }
};

onMounted(() => {
    loadCompany();
});

const handleSave = async () => {
    if (!company.value) return;

    saving.value = true;
    errorMessage.value = '';
    successMessage.value = '';
    Object.keys(errors).forEach((key) => delete errors[key]);

    try {
        const updated = await companyStore.updateCompany(company.value.id, form);
        company.value = updated;
        successMessage.value = 'Workspace profile updated successfully.';
    } catch (err: unknown) {
        const apiError = err as ApiError;
        errorMessage.value = apiError.message || 'Failed to update workspace.';
        if (apiError.errors) {
            Object.assign(errors, apiError.errors);
        }
    } finally {
        saving.value = false;
    }
};

const handleDeactivate = async () => {
    if (!company.value) return;

    statusLoading.value = true;
    errorMessage.value = '';
    successMessage.value = '';

    try {
        const deactivated = await companyStore.deactivateCompany(company.value.id);
        company.value = deactivated;
        showDeactivateDialog.value = false;
        successMessage.value = 'Workspace deactivated. Existing data remains preserved.';
    } catch (err: unknown) {
        const apiError = err as ApiError;
        errorMessage.value = apiError.message || 'Failed to deactivate workspace.';
    } finally {
        statusLoading.value = false;
    }
};

const handleReactivate = async () => {
    if (!company.value) return;

    statusLoading.value = true;
    errorMessage.value = '';
    successMessage.value = '';

    try {
        const reactivated = await companyStore.reactivateCompany(company.value.id);
        company.value = reactivated;
        successMessage.value = 'Workspace reactivated successfully.';
    } catch (err: unknown) {
        const apiError = err as ApiError;
        errorMessage.value = apiError.message || 'Failed to reactivate workspace.';
    } finally {
        statusLoading.value = false;
    }
};
</script>
