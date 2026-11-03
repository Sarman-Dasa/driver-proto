<template>
    <div>
        <div class="mb-6">
            <h2 class="text-h5 font-weight-bold tracking-tight text-slate-900 mb-1">Reset password</h2>
            <p class="text-body-2 text-medium-emphasis">
                Enter your account email and we'll send you a password reset link
            </p>
        </div>

        <v-alert
            v-if="statusMessage"
            type="success"
            variant="tonal"
            density="comfortable"
            class="mb-4 rounded-lg"
        >
            {{ statusMessage }}
        </v-alert>

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
            <div class="mb-4">
                <v-text-field
                    v-model="email"
                    label="Email Address"
                    type="email"
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="mdi-email-outline"
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
                Send Reset Link
            </v-btn>

            <div class="text-center text-body-2 text-medium-emphasis">
                Remember your password?
                <router-link :to="{ name: 'login' }" class="text-primary font-weight-semibold text-decoration-none ml-1">
                    Sign in
                </router-link>
            </div>
        </v-form>
    </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { useAuthStore } from '@/stores/auth';
import type { ApiError } from '@/types';

const authStore = useAuthStore();
const email = ref('');
const loading = ref(false);
const statusMessage = ref('');
const errorMessage = ref('');

const handleSubmit = async () => {
    loading.value = true;
    statusMessage.value = '';
    errorMessage.value = '';

    try {
        const msg = await authStore.forgotPassword({ email: email.value });
        statusMessage.value = msg || 'Password reset link sent to your email!';
    } catch (err: unknown) {
        const apiError = err as ApiError;
        errorMessage.value = apiError.message || 'Unable to send password reset link.';
    } finally {
        loading.value = false;
    }
};
</script>
