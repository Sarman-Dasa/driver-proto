<template>
    <div class="text-center">
        <div class="d-inline-flex pa-4 rounded-xl bg-indigo-50 border border-indigo-100 mb-4">
            <v-icon icon="mdi-email-check-outline" size="44" color="primary"></v-icon>
        </div>

        <h2 class="text-h5 font-weight-bold tracking-tight text-slate-900 mb-2">Verify your email address</h2>
        <p class="text-body-2 text-medium-emphasis mb-6">
            We've sent a verification link to
            <span class="font-weight-bold text-slate-900">{{ authStore.user?.email }}</span>.
            Please click the link in your email to activate your account.
        </p>

        <v-alert
            v-if="statusMessage"
            type="success"
            variant="tonal"
            density="comfortable"
            class="mb-4 text-start rounded-lg"
        >
            {{ statusMessage }}
        </v-alert>

        <v-alert
            v-if="errorMessage"
            type="error"
            variant="tonal"
            density="comfortable"
            class="mb-4 text-start rounded-lg"
        >
            {{ errorMessage }}
        </v-alert>

        <v-btn
            color="primary"
            block
            size="large"
            elevation="0"
            :loading="loading"
            class="text-none font-weight-bold rounded-lg mb-3"
            @click="handleResend"
        >
            Resend Verification Email
        </v-btn>

        <v-btn
            variant="text"
            block
            class="text-none font-weight-medium rounded-lg text-medium-emphasis"
            @click="handleLogout"
        >
            Sign out
        </v-btn>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import apiClient from '@/services/api';
import type { ApiError, ApiResponse } from '@/types';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const loading = ref(false);
const statusMessage = ref('');
const errorMessage = ref('');

onMounted(async () => {
    const id = route.query.id;
    const hash = route.query.hash;

    if (id && hash) {
        loading.value = true;
        try {
            const msg = await authStore.verifyEmail({
                id: Number(id),
                hash: String(hash),
            });
            statusMessage.value = msg || 'Email verified successfully!';
            setTimeout(() => {
                router.push({ name: 'dashboard' });
            }, 1500);
        } catch (err: unknown) {
            const apiError = err as ApiError;
            errorMessage.value = apiError.message || 'Verification link is invalid or expired.';
        } finally {
            loading.value = false;
        }
    }
});

const handleResend = async () => {
    loading.value = true;
    statusMessage.value = '';
    errorMessage.value = '';

    try {
        const response = await apiClient.post<ApiResponse<null>>('/auth/email/resend');
        statusMessage.value = response.data.message || 'Verification link sent!';
    } catch (err: unknown) {
        const apiError = err as ApiError;
        errorMessage.value = apiError.message || 'Failed to resend verification link.';
    } finally {
        loading.value = false;
    }
};

const handleLogout = async () => {
    await authStore.logout();
    router.push({ name: 'login' });
};
</script>
