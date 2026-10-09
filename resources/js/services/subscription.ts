import apiClient from './api';
import type { ApiResponse, Subscription, BillingHistory, BillingHistoryPagination } from '@/types';

export interface SubscriptionSummary {
    subscription: Subscription | null;
    has_stripe_customer: boolean;
    currency: string;
}

export interface CheckoutSessionResponse {
    checkout_url: string;
    session_id: string;
}

export interface PortalSessionResponse {
    portal_url: string;
}

export interface BillingHistoryResponse {
    data: BillingHistory[];
    pagination: BillingHistoryPagination;
}

export const subscriptionService = {
    /**
     * Get current subscription and billing summary for the company.
     */
    async getSubscription(companyId: number): Promise<SubscriptionSummary> {
        const response = await apiClient.get<ApiResponse<SubscriptionSummary>>(
            `/companies/${companyId}/subscription`
        );
        return response.data.data;
    },

    /**
     * Verify and reconcile a completed Stripe checkout session.
     */
    async verifySession(companyId: number, sessionId: string): Promise<SubscriptionSummary> {
        const response = await apiClient.post<ApiResponse<SubscriptionSummary>>(
            `/companies/${companyId}/subscription/verify-session`,
            { session_id: sessionId }
        );
        return response.data.data;
    },

    /**
     * Reconcile/sync company subscription directly from Stripe.
     */
    async syncSubscription(companyId: number): Promise<SubscriptionSummary> {
        const response = await apiClient.post<ApiResponse<SubscriptionSummary>>(
            `/companies/${companyId}/subscription/sync`
        );
        return response.data.data;
    },

    /**
     * Create a Stripe Checkout session for subscription upgrade or renewal.
     */
    async createCheckoutSession(
        companyId: number,
        payload: {
            plan_id: number;
            interval: 'monthly' | 'yearly';
            success_url?: string;
            cancel_url?: string;
        }
    ): Promise<CheckoutSessionResponse> {
        const response = await apiClient.post<ApiResponse<CheckoutSessionResponse>>(
            `/companies/${companyId}/subscription/checkout`,
            payload
        );
        return response.data.data;
    },

    /**
     * Create a Stripe Customer Portal session.
     */
    async createPortalSession(companyId: number, returnUrl?: string): Promise<PortalSessionResponse> {
        const response = await apiClient.post<ApiResponse<PortalSessionResponse>>(
            `/companies/${companyId}/subscription/portal`,
            { return_url: returnUrl }
        );
        return response.data.data;
    },

    /**
     * Cancel the active Stripe subscription.
     */
    async cancelSubscription(
        companyId: number,
        immediately: boolean = false
    ): Promise<{ message: string; subscription: Subscription }> {
        const response = await apiClient.post<ApiResponse<{ message: string; subscription: Subscription }>>(
            `/companies/${companyId}/subscription/cancel`,
            { immediately }
        );
        return response.data.data;
    },

    /**
     * Resume a subscription currently marked to cancel at period end.
     */
    async resumeSubscription(companyId: number): Promise<{ message: string; subscription: Subscription }> {
        const response = await apiClient.post<ApiResponse<{ message: string; subscription: Subscription }>>(
            `/companies/${companyId}/subscription/resume`
        );
        return response.data.data;
    },

    /**
     * Get billing history for the company.
     */
    async getBillingHistory(
        companyId: number,
        page: number = 1,
        perPage: number = 15
    ): Promise<BillingHistoryResponse> {
        const response = await apiClient.get<ApiResponse<BillingHistoryResponse>>(
            `/companies/${companyId}/subscription/history`,
            {
                params: {
                    page,
                    per_page: perPage,
                },
            }
        );
        return response.data.data;
    },
};

export default subscriptionService;
