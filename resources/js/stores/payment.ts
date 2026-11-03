import { defineStore } from 'pinia';
import { ref } from 'vue';
import apiClient from '@/services/api';
import type { PaymentRecord, PublicInvoiceData, PublicPaymentStatus } from '@/types/payment';

export const usePaymentStore = defineStore('payment', () => {
    const payments = ref<PaymentRecord[]>([]);
    const totalPayments = ref(0);
    const currentPage = ref(1);
    const lastPage = ref(1);
    const loading = ref(false);
    const error = ref<string | null>(null);

    // Current selected or active payment
    const currentPayment = ref<PaymentRecord | null>(null);

    // Public payment flow state
    const publicInvoice = ref<PublicInvoiceData | null>(null);
    const publicPaymentStatus = ref<PublicPaymentStatus | null>(null);
    const checkoutLoading = ref(false);

    /**
     * Fetch paginated payments for a company.
     */
    async function fetchPayments(companyId: number, params: Record<string, any> = {}) {
        loading.value = true;
        error.value = null;
        try {
            const response = await apiClient.get(`/companies/${companyId}/payments`, { params });
            payments.value = response.data.data;
            totalPayments.value = response.data.meta?.total ?? payments.value.length;
            currentPage.value = response.data.meta?.current_page ?? 1;
            lastPage.value = response.data.meta?.last_page ?? 1;
            return response.data;
        } catch (err: any) {
            error.value = err.message || 'Failed to load payments.';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    /**
     * Fetch a single payment by ID.
     */
    async function fetchPayment(companyId: number, paymentId: number) {
        loading.value = true;
        error.value = null;
        try {
            const response = await apiClient.get(`/companies/${companyId}/payments/${paymentId}`);
            currentPayment.value = response.data.data;
            return response.data.data;
        } catch (err: any) {
            error.value = err.message || 'Failed to load payment details.';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    /**
     * Generate or retrieve a payment link for an invoice.
     */
    async function generatePaymentLink(companyId: number, invoiceId: number, regenerate = false) {
        loading.value = true;
        error.value = null;
        try {
            const response = await apiClient.post(`/companies/${companyId}/invoices/${invoiceId}/payment-link`, {
                regenerate,
            });
            return response.data.data;
        } catch (err: any) {
            error.value = err.message || 'Failed to generate payment link.';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    /**
     * Public flow: Fetch public invoice summary by token.
     */
    async function fetchPublicInvoice(token: string) {
        loading.value = true;
        error.value = null;
        try {
            const response = await apiClient.get(`/public/invoices/${token}`);
            publicInvoice.value = response.data.data;
            return response.data.data;
        } catch (err: any) {
            error.value = err.message || 'Unable to load invoice details.';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    /**
     * Public flow: Create a Stripe PaymentIntent for inline custom Stripe Elements payment.
     */
    async function createPaymentIntent(token: string) {
        checkoutLoading.value = true;
        error.value = null;
        try {
            const response = await apiClient.post(`/public/invoices/${token}/payment-intent`);
            return response.data.data;
        } catch (err: any) {
            error.value = err.message || 'Failed to initiate payment.';
            throw err;
        } finally {
            checkoutLoading.value = false;
        }
    }

    /**
     * Public flow: Create a Stripe Checkout session.
     */
    async function createCheckoutSession(token: string) {
        checkoutLoading.value = true;
        error.value = null;
        try {
            const response = await apiClient.post(`/public/invoices/${token}/checkout`);
            return response.data.data;
        } catch (err: any) {
            error.value = err.message || 'Failed to initiate checkout session.';
            throw err;
        } finally {
            checkoutLoading.value = false;
        }
    }

    /**
     * Public flow: Verify payment status by token and optional session ID or payment intent ID.
     */
    async function fetchPublicPaymentStatus(token: string, identifier?: string) {
        loading.value = true;
        error.value = null;
        try {
            const params: Record<string, string> = {};
            if (identifier) {
                if (identifier.startsWith('cs_')) {
                    params.session_id = identifier;
                } else {
                    params.payment_intent_id = identifier;
                }
            }
            const response = await apiClient.get(`/public/invoices/${token}/payment-status`, { params });
            publicPaymentStatus.value = response.data.data;
            return response.data.data;
        } catch (err: any) {
            error.value = err.message || 'Failed to verify payment status.';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    return {
        payments,
        totalPayments,
        currentPage,
        lastPage,
        loading,
        error,
        currentPayment,
        publicInvoice,
        publicPaymentStatus,
        checkoutLoading,
        fetchPayments,
        fetchPayment,
        generatePaymentLink,
        fetchPublicInvoice,
        createPaymentIntent,
        createCheckoutSession,
        fetchPublicPaymentStatus,
    };
});
