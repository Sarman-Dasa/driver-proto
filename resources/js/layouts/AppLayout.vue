<template>
    <v-app>
        <!-- Navigation Drawer -->
        <v-navigation-drawer
            v-model="drawer"
            elevation="0"
            class="border-e"
            width="280"
        >
            <!-- Brand Header -->
            <div class="pa-4 d-flex align-center justify-space-between border-b">
                <router-link :to="{ name: 'dashboard' }" class="d-flex align-center gap-3 text-decoration-none text-slate-900">
                    <v-avatar color="primary" rounded="lg" size="38" class="elevation-1">
                        <v-icon icon="mdi-shield-check-outline" color="white" size="22"></v-icon>
                    </v-avatar>
                    <div>
                        <div class="text-subtitle-1 font-weight-bold tracking-tight text-slate-900 leading-tight">
                            BillFlow
                        </div>
                        <div class="text-caption text-medium-emphasis">
                            Financial Platform
                        </div>
                    </div>
                </router-link>
            </div>

            <!-- Navigation Sections -->
            <div class="py-2 px-3 overflow-y-auto" style="height: calc(100% - 73px);">
                <!-- Core Section -->
                <div class="text-caption font-weight-bold text-uppercase text-medium-emphasis px-3 pt-3 pb-1 tracking-wider">
                    Overview
                </div>
                <v-list density="compact" nav class="pa-0 mb-3">
                    <v-list-item
                        prepend-icon="mdi-view-dashboard-outline"
                        title="Dashboard"
                        value="dashboard"
                        :to="{ name: 'dashboard' }"
                        exact
                        rounded="lg"
                        active-class="nav-item-active"
                    ></v-list-item>
                </v-list>

                <!-- Billing & Operations Section -->
                <div v-if="companyStore.currentCompany" class="text-caption font-weight-bold text-uppercase text-medium-emphasis px-3 pt-2 pb-1 tracking-wider">
                    Billing & Operations
                </div>
                <v-list v-if="companyStore.currentCompany" density="compact" nav class="pa-0 mb-3">
                    <v-list-item
                        v-if="permissionsStore.can('invoices.view')"
                        prepend-icon="mdi-file-document-outline"
                        title="Invoices"
                        value="invoices"
                        :to="{ name: 'invoices' }"
                        rounded="lg"
                        active-class="nav-item-active"
                    ></v-list-item>

                    <v-list-item
                        prepend-icon="mdi-account-group-outline"
                        title="Customers"
                        value="customers"
                        :to="{ name: 'customers' }"
                        rounded="lg"
                        active-class="nav-item-active"
                    ></v-list-item>

                    <v-list-item
                        v-if="permissionsStore.can('products.view')"
                        prepend-icon="mdi-package-variant-closed"
                        title="Products & Services"
                        value="products"
                        :to="{ name: 'products' }"
                        rounded="lg"
                        active-class="nav-item-active"
                    ></v-list-item>

                    <v-list-item
                        v-if="permissionsStore.can('payments.view') || permissionsStore.isOwner"
                        prepend-icon="mdi-cash-multiple"
                        title="Payments"
                        value="payments"
                        :to="{ name: 'payments' }"
                        rounded="lg"
                        active-class="nav-item-active"
                    ></v-list-item>
                </v-list>

                <!-- Workspaces Section -->
                <div class="text-caption font-weight-bold text-uppercase text-medium-emphasis px-3 pt-2 pb-1 tracking-wider">
                    Workspaces
                </div>
                <v-list density="compact" nav class="pa-0 mb-3">
                    <v-list-item
                        prepend-icon="mdi-domain"
                        title="All Workspaces"
                        value="companies"
                        :to="{ name: 'companies' }"
                        exact
                        rounded="lg"
                        active-class="nav-item-active"
                    ></v-list-item>

                    <v-list-item
                        v-if="companyStore.currentCompany"
                        prepend-icon="mdi-cog-outline"
                        title="Workspace Settings"
                        value="workspace-settings"
                        :to="{ name: 'companies.settings', params: { company: companyStore.currentCompany.id } }"
                        rounded="lg"
                        active-class="nav-item-active"
                    ></v-list-item>

                    <v-list-item
                        prepend-icon="mdi-office-building-plus-outline"
                        title="New Workspace"
                        value="companies-create"
                        :to="{ name: 'companies.create' }"
                        rounded="lg"
                        active-class="nav-item-active"
                    ></v-list-item>
                </v-list>

                <!-- Settings & Subscription Section -->
                <div class="text-caption font-weight-bold text-uppercase text-medium-emphasis px-3 pt-2 pb-1 tracking-wider">
                    Settings & Billing
                </div>
                <v-list density="compact" nav class="pa-0 mb-3">
                    <v-list-item
                        v-if="companyStore.currentCompany && permissionsStore.can('billing.view')"
                        prepend-icon="mdi-receipt-text-clock-outline"
                        title="Subscription & Billing"
                        value="subscription"
                        :to="{ name: 'settings.subscription' }"
                        rounded="lg"
                        active-class="nav-item-active"
                    ></v-list-item>

                    <v-list-item
                        v-if="companyStore.currentCompany && (permissionsStore.can('stripe.view') || permissionsStore.isOwner)"
                        prepend-icon="mdi-credit-card-wireless-outline"
                        title="Stripe Connect"
                        value="stripe-connect"
                        :to="{ name: 'settings.stripe-connect' }"
                        rounded="lg"
                        active-class="nav-item-active"
                    ></v-list-item>

                    <v-list-item
                        prepend-icon="mdi-tag-outline"
                        title="Plans & Pricing"
                        value="pricing"
                        :to="{ name: 'pricing' }"
                        rounded="lg"
                        active-class="nav-item-active"
                    ></v-list-item>

                    <v-list-item
                        prepend-icon="mdi-account-cog-outline"
                        title="Profile Settings"
                        value="profile"
                        :to="{ name: 'profile' }"
                        rounded="lg"
                        active-class="nav-item-active"
                    ></v-list-item>
                </v-list>
            </div>
        </v-navigation-drawer>

        <!-- Top App Bar -->
        <v-app-bar elevation="0" class="glass-header px-2 px-sm-4" height="64">
            <v-app-bar-nav-icon
                variant="text"
                rounded="lg"
                @click="drawer = !drawer"
            ></v-app-bar-nav-icon>

            <!-- Workspace Switcher Menu -->
            <v-menu location="bottom start" :close-on-content-click="false">
                <template #activator="{ props }">
                    <v-btn
                        v-bind="props"
                        variant="outlined"
                        class="text-none ml-2 border-subtle px-3"
                        rounded="lg"
                        style="border-color: #e2e8f0; background: #ffffff;"
                    >
                        <v-avatar color="primary" size="24" class="mr-2" rounded="sm">
                            <span class="text-caption text-white font-weight-bold">
                                {{ companyStore.currentCompany?.name?.charAt(0) || 'W' }}
                            </span>
                        </v-avatar>
                        <span class="font-weight-bold text-truncate max-w-160">
                            {{ companyStore.currentCompany?.name || 'Select Workspace' }}
                        </span>
                        <v-icon icon="mdi-unfold-more-horizontal" size="small" class="ml-1 text-medium-emphasis"></v-icon>
                    </v-btn>
                </template>

                <v-card min-width="280" class="pa-2 rounded-xl border elevation-3">
                    <div class="px-3 py-2 text-caption font-weight-bold text-uppercase text-medium-emphasis">
                        Switch Workspace
                    </div>
                    <v-divider class="mb-2"></v-divider>

                    <v-list density="compact" nav class="pa-0">
                        <v-list-item
                            v-for="company in companyStore.companies"
                            :key="company.id"
                            rounded="lg"
                            :active="company.id === companyStore.currentCompany?.id"
                            active-class="nav-item-active"
                            class="mb-1"
                            @click="handleSwitchCompany(company.id)"
                        >
                            <template #prepend>
                                <v-avatar color="primary" size="28" rounded="sm" class="mr-2">
                                    <span class="text-caption text-white font-weight-bold">
                                        {{ company.name.charAt(0) }}
                                    </span>
                                </v-avatar>
                            </template>
                            <v-list-item-title class="font-weight-medium">
                                {{ company.name }}
                            </v-list-item-title>
                            <v-list-item-subtitle class="text-caption">
                                {{ company.slug }}
                            </v-list-item-subtitle>
                            <template #append>
                                <v-icon
                                    v-if="company.id === companyStore.currentCompany?.id"
                                    icon="mdi-check"
                                    size="small"
                                    color="primary"
                                ></v-icon>
                            </template>
                        </v-list-item>
                    </v-list>

                    <v-divider class="my-2"></v-divider>

                    <v-list density="compact" nav class="pa-0">
                        <v-list-item
                            v-if="companyStore.currentCompany"
                            prepend-icon="mdi-cog-outline"
                            title="Workspace Settings"
                            :to="{ name: 'companies.settings', params: { company: companyStore.currentCompany.id } }"
                            rounded="lg"
                        ></v-list-item>

                        <v-list-item
                            prepend-icon="mdi-plus"
                            title="Create Workspace"
                            color="primary"
                            :to="{ name: 'companies.create' }"
                            rounded="lg"
                        ></v-list-item>
                    </v-list>
                </v-card>
            </v-menu>

            <!-- Current Workspace Plan Badge -->
            <v-chip
                v-if="subscriptionStore.currentPlan"
                size="small"
                :color="subscriptionStore.currentPlan.slug === 'pro' ? 'primary' : (subscriptionStore.currentPlan.slug === 'business' ? 'purple' : 'default')"
                variant="tonal"
                class="ml-2 font-weight-bold d-none d-sm-inline-flex"
                :to="{ name: 'pricing' }"
            >
                <v-icon icon="mdi-shield-star-outline" start size="14"></v-icon>
                {{ subscriptionStore.currentPlan.name }}
            </v-chip>

            <v-spacer></v-spacer>

            <!-- Quick Action: Create Invoice Button -->
            <v-btn
                v-if="companyStore.currentCompany && permissionsStore.can('invoices.create')"
                color="primary"
                variant="flat"
                prepend-icon="mdi-plus"
                size="small"
                class="text-none font-weight-bold rounded-lg mr-2 d-none d-md-inline-flex"
                :to="{ name: 'invoices.create' }"
            >
                New Invoice
            </v-btn>

            <!-- User Menu -->
            <v-menu location="bottom end">
                <template #activator="{ props }">
                    <v-btn v-bind="props" variant="text" class="text-none pa-1 px-sm-2" rounded="lg">
                        <v-avatar color="primary" size="32" class="mr-sm-2">
                            <span class="text-caption text-white font-weight-bold">
                                {{ authStore.user?.name?.charAt(0) || 'U' }}
                            </span>
                        </v-avatar>
                        <span class="d-none d-sm-inline font-weight-medium text-slate-800">{{ authStore.user?.name }}</span>
                        <v-icon icon="mdi-chevron-down" size="small" class="ml-1 text-medium-emphasis"></v-icon>
                    </v-btn>
                </template>

                <v-card min-width="240" class="pa-2 rounded-xl border elevation-3">
                    <div class="px-3 py-3">
                        <div class="font-weight-bold text-subtitle-2 text-slate-900">{{ authStore.user?.name }}</div>
                        <div class="text-caption text-medium-emphasis text-truncate">{{ authStore.user?.email }}</div>
                    </div>
                    <v-divider class="my-1"></v-divider>
                    <v-list density="compact" nav class="pa-0">
                        <v-list-item
                            prepend-icon="mdi-account-cog-outline"
                            title="Profile Settings"
                            :to="{ name: 'profile' }"
                            rounded="lg"
                        ></v-list-item>
                        <v-list-item
                            prepend-icon="mdi-tag-outline"
                            title="Pricing & Upgrade"
                            :to="{ name: 'pricing' }"
                            rounded="lg"
                        ></v-list-item>
                        <v-divider class="my-1"></v-divider>
                        <v-list-item
                            prepend-icon="mdi-logout"
                            title="Sign out"
                            color="error"
                            rounded="lg"
                            @click="handleLogout"
                        ></v-list-item>
                    </v-list>
                </v-card>
            </v-menu>
        </v-app-bar>

        <!-- Main View Area -->
        <v-main class="bg-background">
            <v-container fluid class="pa-4 pa-sm-6 pa-lg-8" style="max-width: 1440px;">
                <slot />
            </v-container>
        </v-main>
    </v-app>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useCompanyStore } from '@/stores/company';
import { useSubscriptionStore } from '@/stores/subscription';
import { usePermissionsStore } from '@/stores/permissions';

const drawer = ref(true);
const router = useRouter();
const authStore = useAuthStore();
const companyStore = useCompanyStore();
const subscriptionStore = useSubscriptionStore();
const permissionsStore = usePermissionsStore();

const handleSwitchCompany = async (companyId: number) => {
    try {
        await companyStore.switchCompany(companyId);
    } catch (error) {
        console.error('Failed to switch company:', error);
    }
};

const handleLogout = async () => {
    await authStore.logout();
    router.push({ name: 'login' });
};
</script>

<style scoped>
.max-w-160 {
    max-width: 160px;
}
.leading-tight {
    line-height: 1.25;
}
.gap-3 {
    gap: 12px;
}
</style>
