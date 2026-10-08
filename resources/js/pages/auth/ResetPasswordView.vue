<template>
    <div>
        <h2 class="text-h5 font-weight-bold mb-2">Set new password</h2>
        <p class="text-body-2 text-medium-emphasis mb-6">Create a strong password for your BillFlow account</p>

        <v-alert
            v-if="statusMessage"
            type="success"
            variant="tonal"
            density="compact"
            class="mb-4 rounded-lg"
        >
            {{ statusMessage }}
        </v-alert>

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
                v-model="form.email"
                label="Email Address"
                type="email"
                variant="outlined"
                density="comfortable"
                prepend-inner-icon="mdi-email-outline"
                required
                class="mb-2"
            ></v-text-field>

            <v-text-field
                v-model="form.password"
                label="New Password"
                :type="showPassword ? 'text' : 'password'"
                variant="outlined"
                density="comfortable"
                prepend-inner-icon="mdi-lock-outline"
                :append-inner-icon="showPassword ? 'mdi-eye-off' : 'mdi-eye'"
                required
                class="mb-2"
                @click:append-inner="showPassword = !showPassword"
            ></v-text-field>

            <v-text-field
                v-model="form.password_confirmation"
                label="Confirm New Password"
                :type="showPassword ? 'text' : 'password'"
                variant="outlined"
                density="comfortable"
                prepend-inner-icon="mdi-lock-check-outline"
                required
                class="mb-4"
            ></v-text-field>

            <v-btn
                type="submit"
                color="primary"
                block
                size="large"
                elevation="2"
                :loading="loading"
                class="text-none font-weight-bold rounded-lg mb-4"
            >
                Reset Password
            </v-btn>

            <div class="text-center text-body-2 text-medium-emphasis">
                <router-link :to="{ name: 'login' }" class="text-primary font-weight-medium text-decoration-none">
                    Back to sign in
                </router-link>
            </div>
        </v-form>
    </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import type { ApiError } from '@/types';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const showPassword = ref(false);
const loading = ref(false);
const statusMessage = ref('');
const errorMessage = ref('');

const form = reactive({
    token: '',
    email: '',
    password: '',
    password_confirmation: '',
});

onMounted(() => {
    form.token = (route.query.token as string) || '';
    form.email = (route.query.email as string) || '';
});

const handleSubmit = async () => {
    loading.value = true;
    statusMessage.value = '';
    errorMessage.value = '';

    try {
        const msg = await authStore.resetPassword(form);
        statusMessage.value = msg || 'Password reset successfully!';
        setTimeout(() => {
            router.push({ name: 'login' });
        }, 2000);
    } catch (err: unknown) {
        const apiError = err as ApiError;
        errorMessage.value = apiError.message || 'Unable to reset password.';
    } finally {
        loading.value = false;
    }
};
</script>
