<template>
    <v-container class="py-8 px-4 px-md-6" style="max-width: 1240px;">
        <!-- Compact & Professional Page Header -->
        <div class="text-center mb-8">
            <v-chip
                color="primary"
                variant="tonal"
                size="small"
                class="font-weight-bold mb-3 px-3 text-uppercase"
                style="letter-spacing: 0.05em;"
            >
                BillFlow Pricing
            </v-chip>
            <h1 class="text-h4 text-md-h3 font-weight-bold text-slate-900 mb-2 tracking-tight">
                Simple, transparent pricing
            </h1>
            <p class="text-body-1 text-slate-600 mx-auto" style="max-width: 540px;">
                Choose the plan that fits your business.
            </p>

            <!-- Polished Segmented Billing Cycle Toggle -->
            <div class="d-flex align-center justify-center mt-6">
                <div class="billing-toggle-container d-inline-flex align-center pa-1 rounded-pill border">
                    <button
                        type="button"
                        class="billing-toggle-btn rounded-pill px-5 py-2 text-body-2 font-weight-bold"
                        :class="{ 'active': billingCycle === 'monthly' }"
                        @click="billingCycle = 'monthly'"
                    >
                        Monthly Billing
                    </button>
                    <button
                        type="button"
                        class="billing-toggle-btn rounded-pill px-5 py-2 text-body-2 font-weight-bold d-inline-flex align-center"
                        :class="{ 'active': billingCycle === 'yearly' }"
                        @click="billingCycle = 'yearly'"
                    >
                        <span>Annual Billing</span>
                        <span class="save-badge px-2 py-0-5 rounded-pill text-caption font-weight-bold ml-2">
                            Save 17%
                        </span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Loading Skeleton -->
        <v-row v-if="loading" class="mt-4 justify-center">
            <v-col v-for="i in 3" :key="i" cols="12" md="4">
                <v-skeleton-loader
                    type="article, heading, actions, list-item-two-line"
                    class="rounded-2xl border pa-6"
                ></v-skeleton-loader>
            </v-col>
        </v-row>

        <!-- Pricing Cards Grid -->
        <v-row v-else class="justify-center align-stretch">
            <v-col
                v-for="plan in plans"
                :key="plan.id"
                cols="12"
                md="4"
                class="d-flex"
            >
                <div
                    class="pricing-card w-100 d-flex flex-column rounded-2xl bg-white border pa-6 pa-md-7 position-relative"
                    :class="{
                        'pricing-card-pro border-primary-emphasis': plan.slug === 'pro',
                        'pricing-card-standard': plan.slug !== 'pro',
                    }"
                >
                    <!-- Most Popular Floating Badge for Pro -->
                    <div v-if="plan.slug === 'pro'" class="popular-badge position-absolute">
                        <span class="popular-badge-inner px-3 py-1 rounded-pill text-caption font-weight-bold text-uppercase">
                            Most Popular
                        </span>
                    </div>

                    <!-- Plan Header -->
                    <div class="d-flex align-center justify-space-between mb-2">
                        <h2 class="text-h5 font-weight-bold text-slate-900 tracking-tight">
                            {{ plan.name }}
                        </h2>
                        <v-chip
                            v-if="currentPlanSlug === plan.slug"
                            color="success"
                            size="small"
                            variant="flat"
                            class="font-weight-bold"
                        >
                            Current Plan
                        </v-chip>
                    </div>

                    <!-- Description -->
                    <p class="text-body-2 text-slate-600 mb-5 plan-desc">
                        {{ plan.description }}
                    </p>

                    <!-- Price Section -->
                    <div class="mb-5">
                        <div class="d-flex align-baseline">
                            <span class="text-h3 font-weight-black text-slate-900 tracking-tight">
                                {{ getDisplayPrice(plan) }}
                            </span>
                            <span class="text-body-2 font-weight-medium text-slate-500 ml-2">
                                / {{ billingCycle === 'yearly' ? 'year' : 'month' }}
                            </span>
                        </div>
                        <div v-if="billingCycle === 'yearly' && plan.price_yearly > 0" class="text-caption text-slate-500 mt-1">
                            Billed annually (${{ Math.round(plan.price_yearly / 1200) }}/mo equivalent)
                        </div>
                        <div v-else class="text-caption text-transparent mt-1 select-none" aria-hidden="true">
                            &nbsp;
                        </div>
                    </div>

                    <!-- Action Button -->
                    <div class="mb-6">
                        <v-btn
                            v-if="currentPlanSlug === plan.slug"
                            disabled
                            variant="tonal"
                            color="success"
                            block
                            size="large"
                            class="rounded-lg font-weight-bold text-none current-plan-btn"
                        >
                            <v-icon icon="mdi-check" size="18" class="mr-1"></v-icon>
                            Current Plan
                        </v-btn>

                        <v-btn
                            v-else-if="plan.price_monthly === 0"
                            disabled
                            variant="outlined"
                            color="primary"
                            block
                            size="large"
                            class="rounded-lg font-weight-bold text-none free-cta-btn"
                        >
                            Free Starter
                        </v-btn>

                        <v-btn
                            v-else-if="plan.slug === 'pro'"
                            color="primary"
                            variant="flat"
                            block
                            size="large"
                            class="rounded-lg font-weight-bold text-none text-white pro-cta-btn elevation-2"
                            :loading="checkoutLoading === plan.id"
                            @click="selectPlan(plan)"
                        >
                            Choose Pro
                        </v-btn>

                        <v-btn
                            v-else
                            color="#0F172A"
                            variant="flat"
                            block
                            size="large"
                            class="rounded-lg font-weight-bold text-none text-white biz-cta-btn"
                            :loading="checkoutLoading === plan.id"
                            @click="selectPlan(plan)"
                        >
                            Choose Business
                        </v-btn>
                    </div>

                    <v-divider class="mb-5 border-slate-200"></v-divider>

                    <!-- Features Section -->
                    <div class="text-caption font-weight-bold text-uppercase text-slate-500 tracking-wider mb-3">
                        What's Included
                    </div>

                    <ul class="feature-list pa-0 ma-0 flex-grow-1">
                        <li
                            v-for="feat in plan.features"
                            :key="feat.id"
                            class="feature-item d-flex align-center py-1-5"
                            :class="{ 'feature-disabled': !feat.is_enabled }"
                        >
                            <v-icon
                                :icon="feat.is_enabled ? 'mdi-check-circle' : 'mdi-close'"
                                :color="feat.is_enabled ? '#10B981' : '#94A3B8'"
                                size="18"
                                class="mr-2 flex-shrink-0"
                            ></v-icon>
                            <span
                                class="text-body-2"
                                :class="feat.is_enabled ? 'text-slate-800 font-weight-medium' : 'text-slate-400'"
                            >
                                {{ formatFeatureText(feat) }}
                            </span>
                        </li>
                    </ul>
                </div>
            </v-col>
        </v-row>

        <!-- Trust & Reassurance Footer -->
        <div class="mt-12 pt-6 border-t d-flex flex-wrap align-center justify-center gap-6 text-center text-slate-500 text-body-2">
            <div class="d-flex align-center">
                <v-icon icon="mdi-shield-check-outline" color="primary" size="20" class="mr-2"></v-icon>
                <span>30-day money-back guarantee</span>
            </div>
            <div class="d-flex align-center">
                <v-icon icon="mdi-swap-horizontal-bold" color="primary" size="20" class="mr-2"></v-icon>
                <span>Switch or cancel anytime</span>
            </div>
            <div class="d-flex align-center">
                <v-icon icon="mdi-lock-check-outline" color="primary" size="20" class="mr-2"></v-icon>
                <span>Enterprise-grade data encryption</span>
            </div>
        </div>
    </v-container>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useCompanyStore } from '@/stores/company';
import { useSubscriptionStore } from '@/stores/subscription';
import type { Plan, PlanFeatureConfig } from '@/types';

const router = useRouter();
const authStore = useAuthStore();
const companyStore = useCompanyStore();
const subscriptionStore = useSubscriptionStore();
const plans = computed(() => subscriptionStore.availablePlans);
const loading = ref(true);
const checkoutLoading = ref<number | null>(null);
const billingCycle = ref<'monthly' | 'yearly'>('monthly');

const currentPlanSlug = computed(() => subscriptionStore.currentPlan?.slug || null);

const selectPlan = async (plan: Plan) => {
    if (!authStore.isAuthenticated) {
        router.push({ name: 'register', query: { plan: plan.slug } });
        return;
    }

    const companyId = companyStore.currentCompany?.id || Number(localStorage.getItem('billflow_current_company_id'));
    if (!companyId) {
        alert('Please select or create a workspace first.');
        return;
    }

    checkoutLoading.value = plan.id;
    try {
        const checkoutUrl = await subscriptionStore.checkout(plan.id, billingCycle.value, companyId);
        window.location.href = checkoutUrl;
    } catch (e: any) {
        alert(e.message || 'Unable to initiate checkout session.');
    } finally {
        checkoutLoading.value = null;
    }
};

onMounted(async () => {
    loading.value = true;
    try {
        await subscriptionStore.fetchPlans();
        if (authStore.isAuthenticated) {
            if (!companyStore.currentCompany) {
                await companyStore.fetchCompanies();
            }
            if (companyStore.currentCompany) {
                await subscriptionStore.fetchPlan(companyStore.currentCompany.id);
            }
        }
    } finally {
        loading.value = false;
    }
});

const getDisplayPrice = (plan: Plan): string => {
    if (plan.price_monthly === 0) {
        return '$0';
    }
    if (billingCycle.value === 'yearly') {
        return '$' + Math.round(plan.price_yearly / 100);
    }
    return '$' + Math.round(plan.price_monthly / 100);
};

const formatFeatureText = (feat: PlanFeatureConfig): string => {
    if (feat.key === 'customers') {
        if (!feat.is_enabled) {
            return 'Customers';
        }
        return feat.limit_value ? `${feat.limit_value} Customers` : 'Unlimited Customers';
    }

    if (feat.key === 'invoices') {
        if (!feat.is_enabled) {
            return 'Invoices';
        }
        return feat.limit_value ? `${feat.limit_value} Invoices / month` : 'Unlimited Invoices';
    }

    if (feat.key === 'team_members') {
        if (!feat.is_enabled) {
            return 'Team Members';
        }
        return feat.limit_value ? `${feat.limit_value} Team Member${feat.limit_value > 1 ? 's' : ''}` : 'Unlimited Team Members';
    }

    return feat.name;
};
</script>

<style scoped>
/* High-contrast typography helper classes */
.text-slate-900 {
    color: #0f172a !important;
}

.text-slate-800 {
    color: #1e293b !important;
}

.text-slate-600 {
    color: #475569 !important;
}

.text-slate-500 {
    color: #64748b !important;
}

.text-slate-400 {
    color: #94a3b8 !important;
}

.border-slate-200 {
    border-color: #e2e8f0 !important;
}

/* Polished segmented billing toggle */
.billing-toggle-container {
    background-color: #f1f5f9;
    border-color: #e2e8f0 !important;
}

.billing-toggle-btn {
    border: none;
    background: transparent;
    color: #64748b;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    outline: none;
}

.billing-toggle-btn:hover {
    color: #0f172a;
}

.billing-toggle-btn.active {
    background-color: #ffffff;
    color: #0f172a;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.1), 0 1px 2px rgba(15, 23, 42, 0.06);
}

.save-badge {
    background-color: #dcfce7;
    color: #15803d;
    font-size: 0.725rem;
    line-height: 1rem;
    letter-spacing: 0.02em;
}

/* Card aesthetics */
.pricing-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    border-color: #e2e8f0 !important;
}

.pricing-card-standard {
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05), 0 1px 2px rgba(15, 23, 42, 0.03);
}

.pricing-card-standard:hover {
    box-shadow: 0 10px 15px -3px rgba(15, 23, 42, 0.08), 0 4px 6px -2px rgba(15, 23, 42, 0.04);
}

.pricing-card-pro {
    border-color: #4f46e5 !important;
    border-width: 2px !important;
    box-shadow: 0 10px 25px -5px rgba(79, 70, 229, 0.12), 0 8px 10px -6px rgba(79, 70, 229, 0.08);
}

.pricing-card-pro:hover {
    box-shadow: 0 20px 25px -5px rgba(79, 70, 229, 0.18), 0 10px 10px -5px rgba(79, 70, 229, 0.08);
}

.popular-badge {
    top: -13px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 2;
}

.popular-badge-inner {
    background-color: #4f46e5;
    color: #ffffff;
    letter-spacing: 0.06em;
    font-size: 0.675rem;
    box-shadow: 0 2px 4px rgba(79, 70, 229, 0.25);
}

.plan-desc {
    min-height: 40px;
    line-height: 1.45;
}

/* Feature lists */
.feature-list {
    list-style: none;
}

.feature-item {
    line-height: 1.4;
}

.feature-disabled {
    opacity: 0.75;
}

.pro-cta-btn {
    background-color: #4f46e5 !important;
    transition: filter 0.2s ease;
}

.pro-cta-btn:hover {
    filter: brightness(1.08);
}

.biz-cta-btn {
    background-color: #0f172a !important;
}

.biz-cta-btn:hover {
    background-color: #1e293b !important;
}

.current-plan-btn {
    opacity: 0.9 !important;
}
</style>
