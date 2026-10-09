import apiClient from './api';
import type { ApiResponse, StripeConnectStatusResponse, StripeConnectSummary } from '@/types';

export const stripeConnectService = {
    /**
     * Get current Stripe Connect status and details for the company.
     */
    async getStatus(companyId: number): Promise<StripeConnectStatusResponse> {
        const response = await apiClient.get<ApiResponse<StripeConnectStatusResponse>>(
            `/companies/${companyId}/stripe-connect`
        );
        return response.data.data;
    },

    /**
     * Start onboarding and get Stripe Express hosted onboarding URL.
     */
    async startOnboarding(
        companyId: number,
        returnUrl?: string,
        refreshUrl?: string
    ): Promise<{ url: string; account_id: string }> {
        const response = await apiClient.post<ApiResponse<{ url: string; account_id: string }>>(
            `/companies/${companyId}/stripe-connect/onboard`,
            { return_url: returnUrl, refresh_url: refreshUrl }
        );
        return response.data.data;
    },

    /**
     * Synchronize connected account status from Stripe API.
     */
    async syncAccount(companyId: number): Promise<StripeConnectStatusResponse> {
        const response = await apiClient.post<ApiResponse<StripeConnectStatusResponse>>(
            `/companies/${companyId}/stripe-connect/sync`
        );
        return response.data.data;
    },

    /**
     * Generate Express Dashboard login link.
     */
    async getDashboardLink(companyId: number): Promise<{ url: string }> {
        const response = await apiClient.post<ApiResponse<{ url: string }>>(
            `/companies/${companyId}/stripe-connect/dashboard-link`
        );
        return response.data.data;
    },

    /**
     * Disconnect Stripe Connect account.
     */
    async disconnect(companyId: number): Promise<{ summary: StripeConnectSummary }> {
        const response = await apiClient.post<ApiResponse<{ summary: StripeConnectSummary }>>(
            `/companies/${companyId}/stripe-connect/disconnect`
        );
        return response.data.data;
    },
};
