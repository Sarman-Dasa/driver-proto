<template>
    <div>
        <div class="mb-6">
            <h2 class="text-h5 font-weight-bold tracking-tight text-slate-900 mb-1">Create an account</h2>
            <p class="text-body-2 text-medium-emphasis">Start managing your business billing with BillFlow</p>
        </div>

        <v-alert
            v-if="errorMessage"
            type="error"
            variant="tonal"
            density="comfortable"
            class="mb-4 rounded-lg"
            closable
            @click:close="errorMessage = ''"
        >
            {{ errorMessage }}
        </v-alert>

        <v-form @submit.prevent="handleSubmit">
            <div class="mb-3">
                <v-text-field
                    v-model="form.name"
                    label="Full Name"
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="mdi-account-outline"
                    :error-messages="errors.name"
                    required
                    class="rounded-lg"
                ></v-text-field>
            </div>

            <div class="mb-3">
                <v-text-field
                    v-model="form.email"
                    label="Email Address"
                    type="email"
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="mdi-email-outline"
                    :error-messages="errors.email"
                    required
                    class="rounded-lg"
                ></v-text-field>
            </div>

            <div class="mb-3">
                <v-text-field
                    v-model="form.company_name"
                    label="Company / Workspace Name (Optional)"
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="mdi-domain"
                    :error-messages="errors.company_name"
                    placeholder="e.g. Acme Studio"
                    class="rounded-lg"
                ></v-text-field>
            </div>

            <div class="mb-3">
                <v-text-field
                    v-model="form.password"
                    label="Password"
                    :type="showPassword ? 'text' : 'password'"
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="mdi-lock-outline"
                    :append-inner-icon="showPassword ? 'mdi-eye-off' : 'mdi-eye'"
                    :error-messages="errors.password"
                    required
                    class="rounded-lg"
                    @click:append-inner="showPassword = !showPassword"
                ></v-text-field>
            </div>

            <div class="mb-4">
                <v-text-field
                    v-model="form.password_confirmation"
                    label="Confirm Password"
                    :type="showPassword ? 'text' : 'password'"
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="mdi-lock-check-outline"
                    required
                    class="rounded-lg"
                ></v-text-field>
            </div>

            <v-btn
                type="submit"
                color="primary"
                block
                size="large"
                elevation="0"
                :loading="loading"
                class="text-none font-weight-bold rounded-lg mb-5"
            >
                Create Account
            </v-btn>

            <div class="text-center text-body-2 text-medium-emphasis">
                Already have an account?
                <router-link :to="{ name: 'login' }" class="text-primary font-weight-semibold text-decoration-none ml-1">
                    Sign in
                </router-link>
            </div>
        </v-form>
    </div>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import type { ApiError } from '@/types';

const router = useRouter();
const authStore = useAuthStore();

const showPassword = ref(false);
const loading = ref(false);
const errorMessage = ref('');
const errors = reactive<Record<string, string[]>>({});

const form = reactive({
    name: '',
    email: '',
    company_name: '',
    password: '',
    password_confirmation: '',
});

const handleSubmit = async () => {
    loading.value = true;
    errorMessage.value = '';
    Object.keys(errors).forEach((key) => delete errors[key]);

    try {
        await authStore.register(form);
        router.push({ name: 'verify-email' });
    } catch (err: unknown) {
        const apiError = err as ApiError;
        errorMessage.value = apiError.message || 'Registration failed.';
        if (apiError.errors) {
            Object.assign(errors, apiError.errors);
        }
    } finally {
        loading.value = false;
    }
};
</script>
