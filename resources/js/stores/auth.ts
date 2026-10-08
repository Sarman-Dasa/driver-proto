import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import apiClient from '@/services/api';
import type {
    User,
    LoginPayload,
    RegisterPayload,
    ForgotPasswordPayload,
    ResetPasswordPayload,
    VerifyEmailPayload,
    ChangePasswordPayload,
    UpdateProfilePayload,
    VerifyEmailChangePayload,
    ApiResponse,
} from '@/types';

export const useAuthStore = defineStore('auth', () => {
    const user = ref<User | null>(null);
    const token = ref<string | null>(localStorage.getItem('billflow_token'));
    const loading = ref(false);

    const isAuthenticated = computed(() => !!token.value && !!user.value);
    const isEmailVerified = computed(() => !!user.value?.is_email_verified);

    const setToken = (newToken: string | null) => {
        token.value = newToken;
        if (newToken) {
            localStorage.setItem('billflow_token', newToken);
        } else {
            localStorage.removeItem('billflow_token');
        }
    };

    const fetchUser = async (): Promise<User | null> => {
        if (!token.value) {
            user.value = null;
            return null;
        }

        try {
            loading.value = true;
            const response = await apiClient.get<ApiResponse<User>>('/auth/me');
            user.value = response.data.data;
            return user.value;
        } catch {
            setToken(null);
            user.value = null;
            return null;
        } finally {
            loading.value = false;
        }
    };

    const login = async (payload: LoginPayload): Promise<void> => {
        loading.value = true;
        try {
            const response = await apiClient.post<ApiResponse<{ user: User; token: string }>>('/auth/login', payload);
            setToken(response.data.data.token);
            user.value = response.data.data.user;
        } finally {
            loading.value = false;
        }
    };

    const register = async (payload: RegisterPayload): Promise<void> => {
        loading.value = true;
        try {
            const response = await apiClient.post<ApiResponse<{ user: User; token: string }>>('/auth/register', payload);
            setToken(response.data.data.token);
            user.value = response.data.data.user;
        } finally {
            loading.value = false;
        }
    };

    const logout = async (): Promise<void> => {
        try {
            if (token.value) {
                await apiClient.post('/auth/logout');
            }
        } finally {
            setToken(null);
            user.value = null;
            localStorage.removeItem('billflow_current_company_id');
        }
    };

    const forgotPassword = async (payload: ForgotPasswordPayload): Promise<string> => {
        loading.value = true;
        try {
            const response = await apiClient.post<ApiResponse<null>>('/auth/forgot-password', payload);
            return response.data.message;
        } finally {
            loading.value = false;
        }
    };

    const resetPassword = async (payload: ResetPasswordPayload): Promise<string> => {
        loading.value = true;
        try {
            const response = await apiClient.post<ApiResponse<null>>('/auth/reset-password', payload);
            return response.data.message;
        } finally {
            loading.value = false;
        }
    };

    const verifyEmail = async (payload: VerifyEmailPayload): Promise<string> => {
        loading.value = true;
        try {
            const response = await apiClient.post<ApiResponse<null>>('/auth/verify-email', payload);
            if (user.value) {
                user.value.is_email_verified = true;
            }
            return response.data.message;
        } finally {
            loading.value = false;
        }
    };

    const resendVerification = async (): Promise<string> => {
        loading.value = true;
        try {
            const response = await apiClient.post<ApiResponse<null>>('/auth/email/resend');
            return response.data.message;
        } finally {
            loading.value = false;
        }
    };

    const changePassword = async (payload: ChangePasswordPayload): Promise<string> => {
        loading.value = true;
        try {
            const response = await apiClient.post<ApiResponse<null>>('/auth/change-password', payload);
            return response.data.message;
        } finally {
            loading.value = false;
        }
    };

    const updateProfile = async (payload: UpdateProfilePayload): Promise<string> => {
        loading.value = true;
        try {
            const response = await apiClient.put<ApiResponse<User>>('/profile', payload);
            user.value = response.data.data;
            return response.data.message;
        } finally {
            loading.value = false;
        }
    };

    const verifyEmailChange = async (payload: VerifyEmailChangePayload): Promise<string> => {
        loading.value = true;
        try {
            const response = await apiClient.post<ApiResponse<User>>('/profile/verify-email-change', payload);
            user.value = response.data.data;
            return response.data.message;
        } finally {
            loading.value = false;
        }
    };

    const cancelPendingEmail = async (): Promise<string> => {
        loading.value = true;
        try {
            const response = await apiClient.delete<ApiResponse<User>>('/profile/pending-email');
            user.value = response.data.data;
            return response.data.message;
        } finally {
            loading.value = false;
        }
    };

    return {
        user,
        token,
        loading,
        isAuthenticated,
        isEmailVerified,
        login,
        register,
        logout,
        fetchUser,
        forgotPassword,
        resetPassword,
        verifyEmail,
        resendVerification,
        changePassword,
        updateProfile,
        verifyEmailChange,
        cancelPendingEmail,
    };
});
