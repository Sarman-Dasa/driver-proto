export type PaymentStatus = 'pending' | 'processing' | 'succeeded' | 'failed' | 'cancelled' | 'refunded';

export interface PaymentRecord {
    id: number;
    company_id: number;
    invoice_id: number;
    invoice_number?: string;
    customer_id?: number | null;
    customer_name?: string | null;
    customer_email?: string | null;
    payment_reference: string;
    stripe_checkout_session_id?: string | null;
    stripe_payment_intent_id?: string | null;
    stripe_account_id: string;
    amount: number;
    amount_decimal: number;
    formatted_amount: string;
    currency: string;
    status: PaymentStatus;
    status_label: string;
    payment_method_type?: string | null;
    receipt_url?: string | null;
    paid_at?: string | null;
    failure_reason?: string | null;
    metadata?: Record<string, any> | null;
    created_at: string;
}

export interface PublicInvoiceItem {
    id: number;
    product_name: string;
    description?: string | null;
    quantity: number;
    unit?: string | null;
    unit_price: number;
    unit_price_decimal: number;
    tax_rate: number;
    tax_amount: number;
    subtotal: number;
    total: number;
    total_decimal: number;
}

export interface PublicInvoiceData {
    token: string;
    is_expired: boolean;
    invoice_id: number;
    invoice_number: string;
    status: string;
    status_label: string;
    is_payable: boolean;
    issue_date: string;
    due_date: string;
    currency: string;
    subtotal: number;
    subtotal_decimal: number;
    discount_type: string;
    discount_value: number;
    discount_amount: number;
    discount_amount_decimal: number;
    tax_amount: number;
    tax_amount_decimal: number;
    total_amount: number;
    total_amount_decimal: number;
    amount_paid: number;
    amount_paid_decimal: number;
    balance_due: number;
    balance_due_decimal: number;
    notes?: string | null;
    terms?: string | null;
    stripe_connect_ready: boolean;
    stripe_publishable_key?: string | null;
    stripe_account_id?: string | null;
    company: {
        name: string;
        email?: string | null;
        country?: string | null;
        logo_url?: string | null;
    };
    customer: {
        name: string;
        email?: string | null;
        company_name?: string | null;
    };
    items: PublicInvoiceItem[];
}

export interface PaymentIntentResponse {
    client_secret: string;
    payment_intent_id: string;
    payment_reference: string;
    publishable_key: string;
    stripe_account_id: string;
}

export interface PublicPaymentStatus {
    invoice_number: string;
    invoice_status: string;
    invoice_status_label: string;
    total_amount: number;
    total_amount_decimal: number;
    amount_paid: number;
    amount_paid_decimal: number;
    balance_due: number;
    balance_due_decimal: number;
    is_paid: boolean;
    payment?: {
        payment_reference: string;
        amount: number;
        amount_decimal: number;
        currency: string;
        status: PaymentStatus;
        status_label: string;
        paid_at?: string | null;
        receipt_url?: string | null;
        failure_reason?: string | null;
    } | null;
}
