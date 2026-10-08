import axios, { type AxiosError, type AxiosInstance, type InternalAxiosRequestConfig } from 'axios';
import type { ApiError } from '@/types';

export const apiClient: AxiosInstance = axios.create({
    baseURL: '/api/v1',
    headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

// Request interceptor to attach Bearer token and current company context
apiClient.interceptors.request.use(
    (config: InternalAxiosRequestConfig) => {
        const token = localStorage.getItem('billflow_token');
        if (token && config.headers) {
            config.headers.Authorization = `Bearer ${token}`;
        }

        const activeCompanyId = localStorage.getItem('billflow_current_company_id');
        if (activeCompanyId && config.headers && !config.headers['X-Company-Id']) {
            config.headers['X-Company-Id'] = activeCompanyId;
        }

        return config;
    },
    (error) => Promise.reject(error)
);

// Response interceptor to handle errors uniformly
apiClient.interceptors.response.use(
    (response) => response,
    (error: AxiosError<ApiError>) => {
        if (error.response?.status === 401) {
            localStorage.removeItem('billflow_token');
            // Only redirect if not already on an auth page
            const currentPath = window.location.pathname;
            if (!currentPath.startsWith('/login') && !currentPath.startsWith('/register')) {
                window.location.href = '/login';
            }
        }

        const apiError: ApiError = {
            success: false,
            message: error.response?.data?.message || error.message || 'An unexpected error occurred.',
            errors: error.response?.data?.errors || {},
        };

        return Promise.reject(apiError);
    }
);

export default apiClient;
