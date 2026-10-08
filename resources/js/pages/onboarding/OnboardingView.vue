<template>
    <v-container style="max-width: 640px" class="py-12">
        <div class="text-center mb-8">
            <v-avatar color="primary" size="48" class="mb-3">
                <v-icon icon="mdi-office-building-plus" size="28" color="white"></v-icon>
            </v-avatar>
            <h1 class="text-h4 font-weight-bold tracking-tight">Create Workspace</h1>
            <p class="text-subtitle-1 text-medium-emphasis mt-1">
                Set up a new company workspace to manage your financial operations
            </p>
        </div>

        <v-card class="pa-6 pa-sm-8 rounded-xl elevation-2 border">
            <v-alert
                v-if="errorMessage"
                type="error"
                variant="tonal"
                density="compact"
                class="mb-4 rounded-lg"
                closable
                @click:close="errorMessage = ''"
            >
                {{ errorMessage }}
            </v-alert>

            <v-form @submit.prevent="handleSubmit">
                <v-text-field
                    v-model="form.name"
                    label="Workspace / Company Name"
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="mdi-domain"
                    :error-messages="errors.name"
                    required
                    placeholder="e.g. Apex Global Services"
                    class="mb-3"
                ></v-text-field>

                <v-text-field
                    v-model="form.email"
                    label="Company Email Address (Optional)"
                    type="email"
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="mdi-email-outline"
                    :error-messages="errors.email"
                    class="mb-3"
                ></v-text-field>

                <v-text-field
                    v-model="form.phone"
                    label="Phone Number (Optional)"
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="mdi-phone-outline"
                    class="mb-6"
                ></v-text-field>

                <div class="d-flex align-center justify-space-between gap-4">
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
    </v-container>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue';
import { useRouter } from 'vue-router';
import { useCompanyStore } from '@/stores/company';
import type { ApiError } from '@/types';

const router = useRouter();
const companyStore = useCompanyStore();

const loading = ref(false);
const errorMessage = ref('');
const errors = reactive<Record<string, string[]>>({});

const form = reactive({
    name: '',
    email: '',
    phone: '',
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
