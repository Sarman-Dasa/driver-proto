<template>
    <div>
        <h2 class="text-h5 font-weight-bold mb-2">Welcome back</h2>
        <p class="text-body-2 text-medium-emphasis mb-6">Enter your credentials to access your account</p>

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
                :error-messages="errors.email"
                required
                class="mb-2"
            ></v-text-field>

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
                class="mb-1"
                @click:append-inner="showPassword = !showPassword"
            ></v-text-field>

            <div class="d-flex justify-end mb-4">
                <router-link :to="{ name: 'forgot-password' }" class="text-caption text-primary text-decoration-none">
                    Forgot password?
                </router-link>
            </div>

            <v-btn
                type="submit"
                color="primary"
                block
                size="large"
                elevation="2"
                :loading="loading"
                class="text-none font-weight-bold rounded-lg mb-4"
            >
                Sign In
            </v-btn>

            <div class="text-center text-body-2 text-medium-emphasis">
                Don't have an account?
                <router-link :to="{ name: 'register' }" class="text-primary font-weight-medium text-decoration-none ml-1">
                    Sign up
                </router-link>
            </div>
        </v-form>
    </div>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import type { ApiError } from '@/types';

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();

const showPassword = ref(false);
const loading = ref(false);
const errorMessage = ref('');
const errors = reactive<Record<string, string[]>>({});

const form = reactive({
    email: '',
    password: '',
});

const handleSubmit = async () => {
    loading.value = true;
    errorMessage.value = '';
    Object.keys(errors).forEach((key) => delete errors[key]);

    try {
        await authStore.login(form);
        const redirect = (route.query.redirect as string) || '/dashboard';
        router.push(redirect);
    } catch (err: unknown) {
        const apiError = err as ApiError;
        errorMessage.value = apiError.message || 'Login failed.';
        if (apiError.errors) {
            Object.assign(errors, apiError.errors);
        }
    } finally {
        loading.value = false;
    }
};
</script>
