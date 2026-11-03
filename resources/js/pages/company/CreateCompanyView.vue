<template>
    <div style="max-width: 720px" class="py-4 mx-auto">
        <div class="text-center mb-8">
            <v-avatar color="primary" size="56" class="mb-3 elevation-1" rounded="xl">
                <v-icon icon="mdi-office-building-plus" size="30" color="white"></v-icon>
            </v-avatar>
            <h1 class="text-h4 font-weight-bold tracking-tight text-slate-900">Create Workspace</h1>
            <p class="text-subtitle-1 text-medium-emphasis mt-1">
                Configure your new company workspace to manage billing and invoices
            </p>
        </div>

        <v-card class="pa-6 pa-sm-8 rounded-xl elevation-2 border">
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

            <v-form @submit.prevent="handleSubmit">
                <div class="text-subtitle-2 font-weight-bold mb-3 text-medium-emphasis text-uppercase">
                    Workspace Information
                </div>

                <v-text-field
                    v-model="form.name"
                    label="Workspace / Company Name *"
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="mdi-domain"
                    :error-messages="errors.name"
                    required
                    placeholder="e.g. Apex Global Services"
                    class="mb-3"
                ></v-text-field>

                <v-row>
                    <v-col cols="12" sm="6" class="py-1">
                        <v-text-field
                            v-model="form.email"
                            label="Business Email (Optional)"
                            type="email"
                            variant="outlined"
                            density="comfortable"
                            prepend-inner-icon="mdi-email-outline"
                            :error-messages="errors.email"
                            placeholder="billing@company.com"
                        ></v-text-field>
                    </v-col>
                    <v-col cols="12" sm="6" class="py-1">
                        <v-text-field
                            v-model="form.phone"
                            label="Phone Number (Optional)"
                            variant="outlined"
                            density="comfortable"
                            prepend-inner-icon="mdi-phone-outline"
                            :error-messages="errors.phone"
                            placeholder="+1 555-0100"
                        ></v-text-field>
                    </v-col>
                </v-row>

                <v-text-field
                    v-model="form.website"
                    label="Website URL (Optional)"
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="mdi-web"
                    :error-messages="errors.website"
                    placeholder="https://company.com"
                    class="mt-2 mb-3"
                ></v-text-field>

                <v-divider class="my-4"></v-divider>

                <div class="text-subtitle-2 font-weight-bold mb-3 text-medium-emphasis text-uppercase">
                    Address & Regional Details
                </div>

                <v-text-field
                    v-model="form.address"
                    label="Street Address (Optional)"
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="mdi-map-marker-outline"
                    placeholder="123 Business Boulevard"
                    class="mb-3"
                ></v-text-field>

                <v-row>
                    <v-col cols="12" sm="6" class="py-1">
                        <v-text-field
                            v-model="form.city"
                            label="City (Optional)"
                            variant="outlined"
                            density="comfortable"
                            placeholder="San Francisco"
                        ></v-text-field>
                    </v-col>
                    <v-col cols="12" sm="6" class="py-1">
                        <v-text-field
                            v-model="form.state"
                            label="State / Province (Optional)"
                            variant="outlined"
                            density="comfortable"
                            placeholder="CA"
                        ></v-text-field>
                    </v-col>
                </v-row>

                <v-row>
                    <v-col cols="12" sm="6" class="py-1">
                        <v-text-field
                            v-model="form.country"
                            label="Country (Optional)"
                            variant="outlined"
                            density="comfortable"
                            placeholder="United States"
                        ></v-text-field>
                    </v-col>
                    <v-col cols="12" sm="6" class="py-1">
                        <v-select
                            v-model="form.currency"
                            label="Currency"
                            :items="currencies"
                            variant="outlined"
                            density="comfortable"
                            prepend-inner-icon="mdi-currency-usd"
                        ></v-select>
                    </v-col>
                </v-row>

                <div class="d-flex align-center justify-space-between gap-4 mt-6">
                    <v-btn
                        v-if="companyStore.companies.length > 0"
                        variant="text"
                        :to="{ name: 'dashboard' }"
                        class="text-none font-weight-medium rounded-lg"
                    >
                        Back to Dashboard
                    </v-btn>
                    <v-spacer v-else></v-spacer>

                    <v-btn
                        type="submit"
                        color="primary"
                        size="large"
                        elevation="2"
                        :loading="loading"
                        class="text-none font-weight-bold rounded-lg px-8"
                    >
                        Create Workspace
                    </v-btn>
                </div>
            </v-form>
        </v-card>
    </div>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue';
import { useRouter } from 'vue-router';
import { useCompanyStore } from '@/stores/company';
import type { ApiError, CreateCompanyPayload } from '@/types';

const router = useRouter();
const companyStore = useCompanyStore();

const loading = ref(false);
const errorMessage = ref('');
const errors = reactive<Record<string, string[]>>({});

const currencies = ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'INR', 'JPY'];

const form = reactive<CreateCompanyPayload>({
    name: '',
    email: '',
    phone: '',
    website: '',
    address: '',
    city: '',
    state: '',
    country: '',
    currency: 'USD',
    timezone: 'UTC',
});

const handleSubmit = async () => {
    loading.value = true;
    errorMessage.value = '';
    Object.keys(errors).forEach((key) => delete errors[key]);

    try {
        await companyStore.createCompany(form);
        router.push({ name: 'dashboard' });
    } catch (err: unknown) {
        const apiError = err as ApiError;
        errorMessage.value = apiError.message || 'Failed to create workspace.';
        if (apiError.errors) {
            Object.assign(errors, apiError.errors);
        }
    } finally {
        loading.value = false;
    }
};
</script>
