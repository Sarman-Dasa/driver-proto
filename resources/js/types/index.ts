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

