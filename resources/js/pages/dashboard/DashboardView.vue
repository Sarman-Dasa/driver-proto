<template>
    <div>
        <!-- Welcome Header -->
        <div class="d-flex flex-column flex-sm-row align-start align-sm-center justify-space-between mb-8 gap-4">
            <div>
                <h1 class="text-h4 font-weight-bold tracking-tight text-slate-900">
                    Welcome back, {{ authStore.user?.name }}
                </h1>
                <p class="text-subtitle-1 text-medium-emphasis mt-1">
                    Financial Operations Overview &bull; Active Workspace:
                    <span class="font-weight-semibold text-primary">
                        {{ companyStore.currentCompany?.name }}
                    </span>
                </p>
            </div>

            <div class="d-flex align-center gap-2 flex-wrap">
                <v-chip
                    color="success"
                    variant="tonal"
                    prepend-icon="mdi-check-decagram-outline"
                    size="large"
                    class="font-weight-bold"
                >
                    System Operational
                </v-chip>
            </div>
        </div>

        <!-- KPI Quick Metric Cards -->
        <v-row class="mb-6">
            <!-- Invoices Metric -->
            <v-col cols="12" sm="6" lg="3">
                <v-card
                    class="pa-5 rounded-xl border elevation-0 h-100 cursor-pointer"
                    :to="{ name: 'invoices' }"
                >
                    <div class="d-flex align-center justify-space-between mb-3">
                        <span class="text-caption font-weight-bold text-uppercase text-medium-emphasis tracking-wider">
                            Invoices
                        </span>
                        <v-avatar color="indigo-lighten-5" rounded="lg" size="40">
                            <v-icon icon="mdi-file-document-outline" color="primary" size="22"></v-icon>
                        </v-avatar>
                    </div>
                    <div class="text-h5 font-weight-bold text-slate-900 mb-1">Billing Hub</div>
                    <div class="d-flex align-center text-caption text-primary font-weight-medium">
                        <span>Manage & Create</span>
                        <v-icon icon="mdi-arrow-right" size="14" class="ml-1"></v-icon>
                    </div>
                </v-card>
            </v-col>

            <!-- Customers Metric -->
            <v-col cols="12" sm="6" lg="3">
                <v-card
                    class="pa-5 rounded-xl border elevation-0 h-100 cursor-pointer"
                    :to="{ name: 'customers' }"
                >
                    <div class="d-flex align-center justify-space-between mb-3">
                        <span class="text-caption font-weight-bold text-uppercase text-medium-emphasis tracking-wider">
                            Customers
                        </span>
                        <v-avatar color="sky-lighten-5" rounded="lg" size="40">
                            <v-icon icon="mdi-account-group-outline" color="secondary" size="22"></v-icon>
                        </v-avatar>
                    </div>
                    <div class="text-h5 font-weight-bold text-slate-900 mb-1">Client Directory</div>
                    <div class="d-flex align-center text-caption text-secondary font-weight-medium">
                        <span>View Profiles</span>
                        <v-icon icon="mdi-arrow-right" size="14" class="ml-1"></v-icon>
                    </div>
                </v-card>
            </v-col>

            <!-- Products Metric -->
            <v-col cols="12" sm="6" lg="3">
                <v-card
                    class="pa-5 rounded-xl border elevation-0 h-100 cursor-pointer"
                    :to="{ name: 'products' }"
                >
                    <div class="d-flex align-center justify-space-between mb-3">
                        <span class="text-caption font-weight-bold text-uppercase text-medium-emphasis tracking-wider">
                            Catalog
                        </span>
                        <v-avatar color="purple-lighten-5" rounded="lg" size="40">
                            <v-icon icon="mdi-package-variant-closed" color="accent" size="22"></v-icon>
                        </v-avatar>
                    </div>
                    <div class="text-h5 font-weight-bold text-slate-900 mb-1">Products &amp; Rates</div>
                    <div class="d-flex align-center text-caption text-accent font-weight-medium">
                        <span>Items & Services</span>
                        <v-icon icon="mdi-arrow-right" size="14" class="ml-1"></v-icon>
                    </div>
                </v-card>
            </v-col>

            <!-- Payments Metric -->
            <v-col cols="12" sm="6" lg="3">
                <v-card
                    class="pa-5 rounded-xl border elevation-0 h-100 cursor-pointer"
                    :to="{ name: 'payments' }"
                >
                    <div class="d-flex align-center justify-space-between mb-3">
                        <span class="text-caption font-weight-bold text-uppercase text-medium-emphasis tracking-wider">
                            Revenue
                        </span>
                        <v-avatar color="emerald-lighten-5" rounded="lg" size="40">
                            <v-icon icon="mdi-cash-multiple" color="success" size="22"></v-icon>
                        </v-avatar>
                    </div>
                    <div class="text-h5 font-weight-bold text-slate-900 mb-1">Transactions</div>
                    <div class="d-flex align-center text-caption text-success font-weight-medium">
                        <span>Settlements & Receipts</span>
                        <v-icon icon="mdi-arrow-right" size="14" class="ml-1"></v-icon>
                    </div>
                </v-card>
            </v-col>
        </v-row>

        <!-- Quick Actions Row -->
        <v-card class="pa-4 rounded-xl border elevation-0 mb-6 bg-surface">
            <div class="d-flex flex-column flex-sm-row align-start align-sm-center justify-space-between gap-3">
                <div class="d-flex align-center gap-2">
                    <v-icon icon="mdi-lightning-bolt" color="amber-darken-2" size="20"></v-icon>
                    <span class="text-subtitle-2 font-weight-bold text-slate-900">Quick Actions</span>
                </div>
                <div class="d-flex align-center gap-2 flex-wrap">
                    <v-btn
                        v-if="permissionsStore.can('invoices.create')"
                        color="primary"
                        variant="flat"
                        prepend-icon="mdi-plus"
                        size="small"
                        class="text-none font-weight-bold"
                        :to="{ name: 'invoices.create' }"
                    >
                        Create Invoice
                    </v-btn>
                    <v-btn
                        v-if="permissionsStore.can('customers.create')"
                        variant="outlined"
                        prepend-icon="mdi-account-plus-outline"
                        size="small"
                        class="text-none font-weight-bold"
                        :to="{ name: 'customers' }"
                    >
                        Add Customer
                    </v-btn>
                    <v-btn
                        variant="outlined"
                        prepend-icon="mdi-credit-card-wireless-outline"
                        size="small"
                        class="text-none font-weight-bold"
                        :to="{ name: 'settings.stripe-connect' }"
                    >
                        Stripe Connect
                    </v-btn>
                    <v-btn
                        variant="outlined"
                        prepend-icon="mdi-tag-outline"
                        size="small"
                        class="text-none font-weight-bold"
                        :to="{ name: 'pricing' }"
                    >
                        Upgrade Plan
                    </v-btn>
                </div>
            </div>
        </v-card>

        <v-row class="mb-6">
            <!-- Active Workspace Card -->
            <v-col cols="12" md="6">
                <v-card class="pa-6 rounded-xl border elevation-0 h-100 bg-surface">
                    <div class="d-flex align-center justify-space-between mb-4">
                        <div class="d-flex align-center gap-3">
                            <v-avatar color="primary" rounded="lg" size="44" class="elevation-1">
                                <v-icon icon="mdi-domain" color="white" size="24"></v-icon>
                            </v-avatar>
                            <div>
                                <div class="text-h6 font-weight-bold text-slate-900">
                                    {{ companyStore.currentCompany?.name }}
                                </div>
                                <div class="text-caption text-medium-emphasis">
                                    Workspace Identifier: <span class="font-weight-mono font-weight-bold">{{ companyStore.currentCompany?.slug }}</span>
                                </div>
                            </div>
                        </div>

                        <v-chip
                            :color="companyStore.currentCompany?.is_active ? 'success' : 'error'"
                            variant="tonal"
                            size="small"
                            class="text-uppercase font-weight-bold"
                        >
                            {{ companyStore.currentCompany?.status }}
                        </v-chip>
                    </div>

                    <v-divider class="my-4"></v-divider>

                    <v-list density="compact" class="pa-0">
                        <v-list-item class="px-0 py-1">
                            <template #prepend>
                                <v-icon icon="mdi-identifier" size="small" class="mr-2 text-medium-emphasis"></v-icon>
                            </template>
                            <v-list-item-title class="text-body-2 text-medium-emphasis">Internal ID</v-list-item-title>
                            <template #append>
                                <span class="font-weight-mono text-caption font-weight-bold text-slate-800">
                                    #{{ companyStore.currentCompany?.id }}
                                </span>
                            </template>
                        </v-list-item>

                        <v-list-item class="px-0 py-1">
                            <template #prepend>
                                <v-icon icon="mdi-email-outline" size="small" class="mr-2 text-medium-emphasis"></v-icon>
                            </template>
                            <v-list-item-title class="text-body-2 text-medium-emphasis">Billing Email</v-list-item-title>
                            <template #append>
                                <span class="text-caption font-weight-medium text-slate-800">
                                    {{ companyStore.currentCompany?.email || 'None configured' }}
                                </span>
                            </template>
                        </v-list-item>

                        <v-list-item class="px-0 py-1">
                            <template #prepend>
                                <v-icon icon="mdi-shield-account-outline" size="small" class="mr-2 text-medium-emphasis"></v-icon>
                            </template>
                            <v-list-item-title class="text-body-2 text-medium-emphasis">Tenant Isolation</v-list-item-title>
                            <template #append>
                                <v-chip size="x-small" color="primary" variant="tonal" class="font-weight-bold">
                                    Enforced via Company Middleware
                                </v-chip>
                            </template>
                        </v-list-item>
                    </v-list>

                    <div class="mt-4 pt-3 border-t d-flex justify-end">
                        <v-btn
                            v-if="companyStore.currentCompany"
                            variant="text"
                            size="small"
                            color="primary"
                            prepend-icon="mdi-cog-outline"
                            class="text-none font-weight-bold px-0"
                            :to="{ name: 'companies.settings', params: { company: companyStore.currentCompany.id } }"
                        >
                            Configure Workspace Settings
                        </v-btn>
                    </div>
                </v-card>
            </v-col>

            <!-- Foundation Architecture Status -->
            <v-col cols="12" md="6">
                <v-card class="pa-6 rounded-xl border elevation-0 h-100 bg-surface">
                    <div class="d-flex align-center justify-space-between mb-1">
                        <div class="text-h6 font-weight-bold text-slate-900">Platform Health &amp; Security</div>
                        <v-icon icon="mdi-shield-check" color="success" size="24"></v-icon>
                    </div>
                    <div class="text-caption text-medium-emphasis mb-4">
                        Enterprise multi-tenant isolation, queue reliability, and billing engine
                    </div>

                    <v-row dense>
                        <v-col cols="6">
                            <div class="pa-3 rounded-lg border bg-slate-50 h-100">
                                <div class="text-caption text-medium-emphasis">Backend Runtime</div>
                                <div class="text-body-2 font-weight-bold text-slate-900 mt-1 d-flex align-center">
                                    <v-icon icon="mdi-check-circle" color="success" size="16" class="mr-1"></v-icon>
                                    Laravel 13 &bull; PHP 8.3
                                </div>
                            </div>
                        </v-col>
                        <v-col cols="6">
                            <div class="pa-3 rounded-lg border bg-slate-50 h-100">
                                <div class="text-caption text-medium-emphasis">Client Framework</div>
                                <div class="text-body-2 font-weight-bold text-slate-900 mt-1 d-flex align-center">
                                    <v-icon icon="mdi-check-circle" color="success" size="16" class="mr-1"></v-icon>
                                    Vue 3 + Vuetify 3
                                </div>
                            </div>
                        </v-col>
                        <v-col cols="6">
                            <div class="pa-3 rounded-lg border bg-slate-50 h-100">
                                <div class="text-caption text-medium-emphasis">Authentication</div>
                                <div class="text-body-2 font-weight-bold text-slate-900 mt-1 d-flex align-center">
                                    <v-icon icon="mdi-check-circle" color="success" size="16" class="mr-1"></v-icon>
                                    Laravel Sanctum
                                </div>
                            </div>
                        </v-col>
                        <v-col cols="6">
                            <div class="pa-3 rounded-lg border bg-slate-50 h-100">
                                <div class="text-caption text-medium-emphasis">Queue Worker</div>
                                <div class="text-body-2 font-weight-bold text-slate-900 mt-1 d-flex align-center">
                                    <v-icon icon="mdi-check-circle" color="success" size="16" class="mr-1"></v-icon>
                                    Supervisor Managed
                                </div>
                            </div>
                        </v-col>
                    </v-row>

                    <div class="mt-4 pa-3 rounded-lg bg-indigo-50 border border-indigo-100">
                        <div class="d-flex align-center gap-2 text-indigo-900 text-caption font-weight-medium">
                            <v-icon icon="mdi-information-outline" size="small" color="primary"></v-icon>
                            <span>All core billing, invoicing, payments, and multi-tenant security layers active.</span>
                        </div>
                    </div>
                </v-card>
            </v-col>
        </v-row>
    </div>
</template>

<script setup lang="ts">
import { useAuthStore } from '@/stores/auth';
import { useCompanyStore } from '@/stores/company';
import { usePermissionsStore } from '@/stores/permissions';

const authStore = useAuthStore();
const companyStore = useCompanyStore();
const permissionsStore = usePermissionsStore();
</script>

<style scoped>
.gap-2 {
    gap: 8px;
}
.gap-3 {
    gap: 12px;
}
.gap-4 {
    gap: 16px;
}
</style>
