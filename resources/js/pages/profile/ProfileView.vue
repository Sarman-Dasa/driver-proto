<template>
    <div class="mx-auto" style="max-width: 1200px">
        <!-- Page Header -->
        <div class="mb-8">
            <h1 class="text-h4 font-weight-bold tracking-tight text-slate-900">Account Settings</h1>
            <p class="text-subtitle-1 text-medium-emphasis mt-1">
                Manage your personal profile, email address, and security credentials
            </p>
        </div>

        <!-- Global Status Messages -->
        <v-alert
            v-if="statusMessage"
            type="success"
            variant="tonal"
            density="comfortable"
            class="mb-6 rounded-xl"
            closable
            @click:close="statusMessage = ''"
        >
            {{ statusMessage }}
        </v-alert>

        <v-alert
            v-if="errorMessage"
            type="error"
            variant="tonal"
            density="comfortable"
            class="mb-6 rounded-xl"
            closable
            @click:close="errorMessage = ''"
        >
            {{ errorMessage }}
        </v-alert>

        <v-row>
            <!-- Personal Information & Email Section -->
            <v-col cols="12" md="6">
                <v-card class="pa-6 rounded-xl border elevation-1 h-100">
                    <div class="d-flex align-center gap-3 mb-4">
                        <v-avatar color="primary" rounded="lg" size="40">
                            <v-icon icon="mdi-account-outline" color="white"></v-icon>
                        </v-avatar>
                        <div>
                            <div class="text-h6 font-weight-bold">Personal Information</div>
                            <div class="text-caption text-medium-emphasis">Update your display name and email address</div>
                        </div>
                    </div>

                    <v-divider class="my-4"></v-divider>

                    <!-- Pending Email Notice -->
                    <div v-if="authStore.user?.pending_email" class="mb-5 pa-4 rounded-lg bg-amber-lighten-5 border border-amber-lighten-3">
                        <div class="d-flex align-start justify-space-between">
                            <div class="d-flex align-start gap-2">
                                <v-icon icon="mdi-email-sync-outline" color="amber-darken-3" class="mt-1"></v-icon>
                                <div>
                                    <div class="text-subtitle-2 font-weight-bold text-amber-darken-4">Email Change Pending</div>
                                    <div class="text-caption text-amber-darken-3 mt-1">
                                        Verification sent to <strong>{{ authStore.user.pending_email }}</strong>.<br />
                                        Your current email (<strong>{{ authStore.user.email }}</strong>) remains active until verified.
                                    </div>
                                </div>
                            </div>
                            <v-btn
                                variant="text"
                                color="error"
                                size="small"
                                class="text-none font-weight-bold"
                                :loading="cancellingPendingEmail"
                                @click="handleCancelPendingEmail"
                            >
                                Cancel
                            </v-btn>
                        </div>
                    </div>

                    <v-form @submit.prevent="handleUpdateProfile">
                        <v-text-field
                            v-model="profileForm.name"
                            label="Full Name"
                            variant="outlined"
                            density="comfortable"
                            prepend-inner-icon="mdi-account-outline"
                            :error-messages="profileErrors.name"
                            required
                            class="mb-3"
                        ></v-text-field>

                        <v-text-field
                            v-model="profileForm.email"
                            label="Email Address"
                            type="email"
                            variant="outlined"
                            density="comfortable"
                            prepend-inner-icon="mdi-email-outline"
                            :error-messages="profileErrors.email"
                            required
                            hint="Changing email will trigger a verification link to the new address before taking effect"
                            persistent-hint
                            class="mb-6"
                        ></v-text-field>

                        <v-btn
                            type="submit"
                            color="primary"
                            size="large"
                            elevation="2"
                            :loading="updatingProfile"
                            :disabled="updatingProfile"
                            class="text-none font-weight-bold rounded-lg px-6"
                        >
                            Save Profile
                        </v-btn>
                    </v-form>
                </v-card>
            </v-col>

            <!-- Password Change Section -->
            <v-col cols="12" md="6">
                <v-card class="pa-6 rounded-xl border elevation-1 h-100">
                    <div class="d-flex align-center gap-3 mb-4">
                        <v-avatar color="primary" rounded="lg" size="40">
                            <v-icon icon="mdi-lock-reset" color="white"></v-icon>
                        </v-avatar>
                        <div>
                            <div class="text-h6 font-weight-bold">Change Password</div>
                            <div class="text-caption text-medium-emphasis">Ensure your account uses a strong, unique password</div>
                        </div>
                    </div>

                    <v-divider class="my-4"></v-divider>

                    <v-form @submit.prevent="handleChangePassword">
                        <v-text-field
                            v-model="passwordForm.current_password"
                            label="Current Password"
                            :type="showCurrentPassword ? 'text' : 'password'"
                            variant="outlined"
                            density="comfortable"
                            prepend-inner-icon="mdi-lock-outline"
                            :append-inner-icon="showCurrentPassword ? 'mdi-eye-off' : 'mdi-eye'"
                            :error-messages="passwordErrors.current_password"
                            required
                            class="mb-3"
                            @click:append-inner="showCurrentPassword = !showCurrentPassword"
                        ></v-text-field>

                        <v-text-field
                            v-model="passwordForm.password"
                            label="New Password"
                            :type="showNewPassword ? 'text' : 'password'"
                            variant="outlined"
                            density="comfortable"
                            prepend-inner-icon="mdi-lock-plus-outline"
                            :append-inner-icon="showNewPassword ? 'mdi-eye-off' : 'mdi-eye'"
                            :error-messages="passwordErrors.password"
                            required
                            hint="Minimum 8 characters with letters, numbers, and symbols"
                            persistent-hint
                            class="mb-3"
                            @click:append-inner="showNewPassword = !showNewPassword"
                        ></v-text-field>

                        <v-text-field
                            v-model="passwordForm.password_confirmation"
                            label="Confirm New Password"
                            :type="showNewPassword ? 'text' : 'password'"
                            variant="outlined"
                            density="comfortable"
                            prepend-inner-icon="mdi-lock-check-outline"
                            required
                            class="mb-6"
                        ></v-text-field>

                        <v-btn
                            type="submit"
                            color="primary"
                            size="large"
                            elevation="2"
                            :loading="changingPassword"
                            :disabled="changingPassword"
                            class="text-none font-weight-bold rounded-lg px-6"
                        >
                            Change Password
                        </v-btn>
                    </v-form>
                </v-card>
            </v-col>
        </v-row>
    </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import type { ApiError } from '@/types';

const route = useRoute();
const authStore = useAuthStore();

const statusMessage = ref('');
const errorMessage = ref('');

const updatingProfile = ref(false);
const changingPassword = ref(false);
const cancellingPendingEmail = ref(false);

const showCurrentPassword = ref(false);
const showNewPassword = ref(false);

const profileForm = reactive({
    name: authStore.user?.name || '',
    email: authStore.user?.email || '',
});

const profileErrors = reactive<Record<string, string[]>>({});

const passwordForm = reactive({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const passwordErrors = reactive<Record<string, string[]>>({});

onMounted(async () => {
    if (authStore.user) {
        profileForm.name = authStore.user.name;
        profileForm.email = authStore.user.email;
    }

    // Handle email change verification if accessed from verification email link
    if (route.query.verify_email_change === '1' && route.query.hash) {
        try {
            const msg = await authStore.verifyEmailChange({
                hash: String(route.query.hash),
                expires: route.query.expires ? Number(route.query.expires) : undefined,
                signature: route.query.signature ? String(route.query.signature) : undefined,
            });
            statusMessage.value = msg || 'Your new email address has been successfully verified!';
            if (authStore.user) {
                profileForm.email = authStore.user.email;
            }
        } catch (err: unknown) {
            const apiError = err as ApiError;
            errorMessage.value = apiError.message || 'Email change verification failed.';
        }
    }
});

const handleUpdateProfile = async () => {
    updatingProfile.value = true;
    statusMessage.value = '';
    errorMessage.value = '';
    Object.keys(profileErrors).forEach((key) => delete profileErrors[key]);

    try {
        const msg = await authStore.updateProfile(profileForm);
        statusMessage.value = msg;
    } catch (err: unknown) {
        const apiError = err as ApiError;
        errorMessage.value = apiError.message || 'Failed to update profile.';
        if (apiError.errors) {
            Object.assign(profileErrors, apiError.errors);
        }
    } finally {
        updatingProfile.value = false;
    }
};

const handleCancelPendingEmail = async () => {
    cancellingPendingEmail.value = true;
    statusMessage.value = '';
    errorMessage.value = '';

    try {
        const msg = await authStore.cancelPendingEmail();
        statusMessage.value = msg;
        if (authStore.user) {
            profileForm.email = authStore.user.email;
        }
    } catch (err: unknown) {
        const apiError = err as ApiError;
        errorMessage.value = apiError.message || 'Failed to cancel pending email.';
    } finally {
        cancellingPendingEmail.value = false;
    }
};

const handleChangePassword = async () => {
    changingPassword.value = true;
    statusMessage.value = '';
    errorMessage.value = '';
    Object.keys(passwordErrors).forEach((key) => delete passwordErrors[key]);

    try {
        const msg = await authStore.changePassword(passwordForm);
        statusMessage.value = msg;
        passwordForm.current_password = '';
        passwordForm.password = '';
        passwordForm.password_confirmation = '';
    } catch (err: unknown) {
        const apiError = err as ApiError;
        errorMessage.value = apiError.message || 'Failed to change password.';
        if (apiError.errors) {
            Object.assign(passwordErrors, apiError.errors);
        }
    } finally {
        changingPassword.value = false;
    }
};
</script>
