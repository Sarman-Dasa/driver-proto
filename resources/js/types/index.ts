export type CompanyStatus = 'active' | 'inactive';

export interface Company {
    id: number;
    name: string;
    slug: string;
    email: string | null;
    phone: string | null;
    logo: string | null;
    website?: string | null;
    address?: string | null;
    city?: string | null;
    state?: string | null;
    postal_code?: string | null;
    country?: string | null;
    tax_number?: string | null;
    currency?: string | null;
    timezone?: string | null;
    status: CompanyStatus;
    is_active: boolean;
    owner_id?: number | null;
    is_owner?: boolean;
    created_at: string;
    updated_at: string;
}

export interface User {
    id: number;
    name: string;
    email: string;
    pending_email?: string | null;
    email_verified_at: string | null;
    is_email_verified: boolean;
    companies?: Company[];
    created_at: string;
    updated_at: string;
}

export interface ApiResponse<T = unknown> {
    success: boolean;
    message: string;
    data: T;
    errors?: Record<string, string[]>;
}

export interface ApiError {
    success: boolean;
    message: string;
    errors: Record<string, string[]>;
}

export interface Pagination<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

export interface LoginPayload {
    email: string;
    password: string;
    device_name?: string;
}

export interface RegisterPayload {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
    company_name?: string;
}

export interface ForgotPasswordPayload {
    email: string;
}

export interface ResetPasswordPayload {
    token: string;
    email: string;
    password: string;
    password_confirmation: string;
}

export interface VerifyEmailPayload {
    id: number;
    hash: string;
    expires?: number;
    signature?: string;
}

export interface ChangePasswordPayload {
    current_password: string;
    password: string;
    password_confirmation: string;
}

export interface UpdateProfilePayload {
    name: string;
    email: string;
}

export interface VerifyEmailChangePayload {
    hash: string;
    expires?: number;
    signature?: string;
}

export interface CreateCompanyPayload {
    name: string;
    email?: string | null;
    phone?: string | null;
    website?: string | null;
    address?: string | null;
    city?: string | null;
    state?: string | null;
    postal_code?: string | null;
    country?: string | null;
    tax_number?: string | null;
    currency?: string | null;
    timezone?: string | null;
}

export interface UpdateCompanyPayload {
    name: string;
    email?: string | null;
    phone?: string | null;
    website?: string | null;
    address?: string | null;
    city?: string | null;
    state?: string | null;
    postal_code?: string | null;
    country?: string | null;
    tax_number?: string | null;
    currency?: string | null;
    timezone?: string | null;
}

export interface Role {
    id: number;
    name: string;
    slug: string;
    description?: string | null;
    is_system?: boolean;
    permissions?: string[];
}

export interface CompanyAuthorization {
    role: Role | null;
    permissions: string[];
    is_owner: boolean;
}

export interface CompanyMember {
    id: number;
    name: string;
    email: string;
    is_owner: boolean;
    role: Role | null;
    joined_at?: string | null;
}

export interface PlanFeatureConfig {
    id: number;
    key: string;
    name: string;
    description: string | null;
    type: 'boolean' | 'limit';
    limit_period: 'none' | 'total' | 'monthly';
    is_enabled: boolean;
    limit_value: number | null;
    is_unlimited: boolean;
}

export interface Plan {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    price_monthly: number;
    price_yearly: number;
    formatted_price_monthly: string;
    formatted_price_yearly: string;
    currency: string;
    is_default: boolean;
    is_active: boolean;
    sort_order: number;
    features?: PlanFeatureConfig[];
}

export type SubscriptionStatus = 'trialing' | 'active' | 'past_due' | 'cancelled' | 'expired';

export interface Subscription {
    id: number;
    plan_id: number;
    status: SubscriptionStatus;
    status_label: string;
    is_active: boolean;
    starts_at: string | null;
    ends_at: string | null;
    trial_ends_at: string | null;
    current_period_start: string | null;
    current_period_end: string | null;
    cancel_at_period_end: boolean;
    plan?: Plan;
}

export interface CompanyFeatureStatus {
    key: string;
    name: string;
    description?: string | null;
    type: 'boolean' | 'limit';
    limit_period: 'none' | 'total' | 'monthly';
    enabled: boolean;
    limit: number | null;
    is_unlimited: boolean;
    current_usage?: number;
    remaining?: number | null;
    has_reached_limit?: boolean;
}

export interface CompanyPlanResponse {
    subscription: Subscription | null;
    plan: Plan | null;
    features: Record<string, CompanyFeatureStatus>;
}

export type CustomerStatusType = 'active' | 'archived';

export interface Customer {
    id: number;
    company_id: number;
    name: string;
    email: string | null;
    phone: string | null;
    company_name: string | null;
    tax_number: string | null;
    billing_address: string | null;
    billing_city: string | null;
    billing_state: string | null;
    billing_postal_code: string | null;
    billing_country: string | null;
    shipping_same_as_billing: boolean;
    shipping_address: string | null;
    shipping_city: string | null;
    shipping_state: string | null;
    shipping_postal_code: string | null;
    shipping_country: string | null;
    notes: string | null;
    status: CustomerStatusType;
    status_label?: string;
    is_archived: boolean;
    created_at: string;
    updated_at: string;
    deleted_at?: string | null;
}

export interface CreateCustomerPayload {
    name: string;
    email?: string | null;
    phone?: string | null;
    company_name?: string | null;
    tax_number?: string | null;
    billing_address?: string | null;
    billing_city?: string | null;
    billing_state?: string | null;
    billing_postal_code?: string | null;
    billing_country?: string | null;
    shipping_same_as_billing?: boolean;
    shipping_address?: string | null;
    shipping_city?: string | null;
    shipping_state?: string | null;
    shipping_postal_code?: string | null;
    shipping_country?: string | null;
    notes?: string | null;
}

export interface UpdateCustomerPayload {
    name?: string;
    email?: string | null;
    phone?: string | null;
    company_name?: string | null;
    tax_number?: string | null;
    billing_address?: string | null;
    billing_city?: string | null;
    billing_state?: string | null;
    billing_postal_code?: string | null;
    billing_country?: string | null;
    shipping_same_as_billing?: boolean;
    shipping_address?: string | null;
    shipping_city?: string | null;
    shipping_state?: string | null;
    shipping_postal_code?: string | null;
    shipping_country?: string | null;
    notes?: string | null;
}

export interface CustomerFilterParams {
    status?: 'active' | 'archived' | 'all';
    search?: string;
    sort?: string;
    page?: number;
    per_page?: number;
}

export type ProductType = 'product' | 'service';
export type ProductStatus = 'active' | 'inactive';

export interface Product {
    id: number;
    company_id: number;
    type: ProductType;
    type_label?: string;
    name: string;
    description: string | null;
    sku: string | null;
    price: number;
    price_decimal?: number;
    formatted_price?: string;
    currency: string;
    tax_rate: number;
    unit: string | null;
    status: ProductStatus;
    status_label?: string;
    is_active: boolean;
    created_at: string;
    updated_at: string;
    deleted_at?: string | null;
}

export interface CreateProductPayload {
    type: ProductType;
    name: string;
    description?: string | null;
    sku?: string | null;
    price: number;
    currency?: string;
    tax_rate?: number;
    unit?: string | null;
    status?: ProductStatus;
}

export interface UpdateProductPayload {
    type?: ProductType;
    name?: string;
    description?: string | null;
    sku?: string | null;
    price?: number;
    currency?: string;
    tax_rate?: number;
    unit?: string | null;
    status?: ProductStatus;
}

export interface ProductFilterParams {
    type?: ProductType | 'all';
    status?: ProductStatus | 'all' | 'archived';
    search?: string;
    sort?: string;
    page?: number;
    per_page?: number;
}

// Phase 7: Invoices Types
export type InvoiceStatus = 'draft' | 'sent' | 'partially_paid' | 'paid' | 'overdue' | 'cancelled';
export type DiscountType = 'none' | 'percentage' | 'fixed';

export interface InvoiceItem {
    id?: number;
    invoice_id?: number;
    product_id?: number | null;
    product_name: string;
    description?: string | null;
    quantity: number;
    unit?: string | null;
    unit_price: number;
    unit_price_decimal?: number;
    formatted_unit_price?: string;
    tax_rate: number;
    tax_amount?: number;
    tax_amount_decimal?: number;
    formatted_tax_amount?: string;
    subtotal?: number;
    subtotal_decimal?: number;
    formatted_subtotal?: string;
    total?: number;
    total_decimal?: number;
    formatted_total?: string;
    sort_order?: number;
}

export interface Invoice {
    id: number;
    company_id: number;
    customer_id: number;
    invoice_number: string;
    status: InvoiceStatus;
    status_label?: string;
    is_editable: boolean;
    can_be_sent: boolean;
    can_be_cancelled: boolean;
    issue_date: string;
    due_date: string;
    currency: string;
    subtotal: number;
    subtotal_decimal: number;
    formatted_subtotal: string;
    discount_type: DiscountType;
    discount_value: number;
    discount_amount: number;
    discount_amount_decimal: number;
    formatted_discount_amount: string;
    tax_amount: number;
    tax_amount_decimal: number;
    formatted_tax_amount: string;
    total_amount: number;
    total_amount_decimal: number;
    formatted_total_amount: string;
    amount_paid: number;
    amount_paid_decimal: number;
    balance_due: number;
    balance_due_decimal: number;
    formatted_balance_due: string;
    customer_snapshot?: Record<string, any> | null;
    notes?: string | null;
    terms?: string | null;
    sent_at?: string | null;
    cancelled_at?: string | null;
    created_at: string;
    updated_at: string;
    deleted_at?: string | null;
    customer?: Customer;
    items?: InvoiceItem[];
}

export interface InvoiceItemPayload {
    product_id?: number | null;
    product_name: string;
    description?: string | null;
    quantity: number;
    unit?: string | null;
    unit_price: number; // in cents or dollars (normalized)
    tax_rate?: number;
}

export interface CreateInvoicePayload {
    customer_id: number;
    issue_date: string;
    due_date: string;
    currency?: string;
    discount_type?: DiscountType;
    discount_value?: number;
    notes?: string | null;
    terms?: string | null;
    items: InvoiceItemPayload[];
}

export interface UpdateInvoicePayload {
    customer_id: number;
    issue_date: string;
    due_date: string;
    currency?: string;
    discount_type?: DiscountType;
    discount_value?: number;
    notes?: string | null;
    terms?: string | null;
    items: InvoiceItemPayload[];
}

export interface InvoiceFilterParams {
    status?: InvoiceStatus | 'all' | 'archived';
    customer_id?: number | null;
    search?: string;
    from_date?: string | null;
    to_date?: string | null;
    sort?: string;
    page?: number;
    per_page?: number;
}




