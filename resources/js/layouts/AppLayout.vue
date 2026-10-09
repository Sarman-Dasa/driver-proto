<template>
    <v-app>
        <!-- Navigation Drawer -->
        <v-navigation-drawer v-model="drawer" elevation="1">
            <div class="pa-4 d-flex align-center gap-3 border-b">
                <v-avatar color="primary" rounded="lg" size="36">
                    <v-icon icon="mdi-shield-check-outline" color="white"></v-icon>
                </v-avatar>
                <div class="ml-3">
                    <div class="text-subtitle-1 font-weight-bold">BillFlow</div>
                    <div class="text-caption text-medium-emphasis">Core Platform</div>
                </div>
            </div>

            <v-list density="compact" nav class="mt-3">
                <v-list-item
                    prepend-icon="mdi-view-dashboard-outline"
                    title="Dashboard"
                    value="dashboard"
                    :to="{ name: 'dashboard' }"
                    exact
                    color="primary"
                ></v-list-item>

                <v-list-item
                    v-if="companyStore.currentCompany"
                    prepend-icon="mdi-account-group-outline"
                    title="Customers"
                    value="customers"
                    :to="{ name: 'customers' }"
                    color="primary"
                ></v-list-item>

                <v-list-item
                    v-if="companyStore.currentCompany && permissionsStore.can('products.view')"
                    prepend-icon="mdi-package-variant-closed"
                    title="Products & Services"
                    value="products"
                    :to="{ name: 'products' }"
                    color="primary"
                ></v-list-item>

                <v-list-item
                    v-if="companyStore.currentCompany && permissionsStore.can('invoices.view')"
                    prepend-icon="mdi-file-document-outline"
                    title="Invoices"
                    value="invoices"
                    :to="{ name: 'invoices' }"
                    color="primary"
                ></v-list-item>

                <v-list-item
                    prepend-icon="mdi-domain"
                    title="Workspaces"
                    value="companies"
                    :to="{ name: 'companies' }"
                    exact
                    color="primary"
                ></v-list-item>

                <v-list-item
                    v-if="companyStore.currentCompany"
                    prepend-icon="mdi-cog-outline"
                    title="Workspace Settings"
                    value="workspace-settings"
                    :to="{ name: 'companies.settings', params: { company: companyStore.currentCompany.id } }"
                    color="primary"
                ></v-list-item>

                <v-list-item
                    prepend-icon="mdi-office-building-plus-outline"
                    title="New Workspace"
                    value="companies-create"
                    :to="{ name: 'companies.create' }"
                    color="primary"
                ></v-list-item>

                <v-list-item
                    v-if="companyStore.currentCompany && permissionsStore.can('billing.view')"
                    prepend-icon="mdi-receipt-text-clock-outline"
                    title="Billing & Subscription"
                    value="subscription"
                    :to="{ name: 'settings.subscription' }"
                    color="primary"
                ></v-list-item>

                <v-list-item
                    v-if="companyStore.currentCompany && (permissionsStore.can('stripe.view') || permissionsStore.isOwner)"
                    prepend-icon="mdi-credit-card-wireless-outline"
                    title="Stripe Connect"
                    value="stripe-connect"
                    :to="{ name: 'settings.stripe-connect' }"
                    color="primary"
                ></v-list-item>

                <v-list-item
                    prepend-icon="mdi-credit-card-outline"
                    title="Plans & Pricing"
                    value="pricing"
                    :to="{ name: 'pricing' }"
                    color="primary"
                ></v-list-item>

                <v-list-item
                    prepend-icon="mdi-account-cog-outline"
                    title="Profile Settings"
                    value="profile"
                    :to="{ name: 'profile' }"
                    color="primary"
                ></v-list-item>
            </v-list>
        </v-navigation-drawer>

        <!-- Top Navigation Bar -->
        <v-app-bar elevation="1" color="surface">
            <v-app-bar-nav-icon @click="drawer = !drawer"></v-app-bar-nav-icon>

            <!-- Company Context Switcher -->
            <v-menu v-if="companyStore.companies.length > 0" location="bottom start" :close-on-content-click="true">
                <template #activator="{ props }">
                    <v-btn
                        v-bind="props"
                        variant="tonal"
                        color="primary"
                        class="text-none ml-2 rounded-lg"
                        prepend-icon="mdi-domain"
                        append-icon="mdi-chevron-down"
                    >
                        {{ companyStore.currentCompany?.name || 'Select Workspace' }}
                    </v-btn>
                </template>

                <v-card min-width="280" class="pa-2 rounded-xl">
                    <div class="d-flex align-center justify-space-between px-3 py-2">
                        <span class="text-caption font-weight-bold text-uppercase text-medium-emphasis">
                            Workspaces
                        </span>
                        <v-btn
                            variant="text"
                            size="x-small"
                            color="primary"
                            :to="{ name: 'companies' }"
                            class="text-none"
                        >
                            View All
                        </v-btn>
                    </div>

                    <v-list density="compact">
                        <v-list-item
                            v-for="company in companyStore.companies"
                            :key="company.id"
                            :value="company.id"
                            :active="companyStore.currentCompany?.id === company.id"
                            :disabled="!company.is_active"
                            color="primary"
                            rounded="lg"
                            @click="company.is_active && handleSwitchCompany(company.id)"
                        >
                            <template #prepend>
                                <v-icon
                                    :icon="companyStore.currentCompany?.id === company.id ? 'mdi-check-circle' : 'mdi-domain'"
                                    :color="companyStore.currentCompany?.id === company.id ? 'primary' : (company.is_active ? 'grey' : 'error')"
                                ></v-icon>
                            </template>
                            <v-list-item-title class="font-weight-medium">{{ company.name }}</v-list-item-title>
                            <v-list-item-subtitle class="text-caption">
                                <span :class="company.is_active ? 'text-success' : 'text-error'">
                                    {{ company.is_active ? 'Active' : 'Inactive' }}
                                </span>
                                <span v-if="company.is_owner" class="ml-1 text-primary font-weight-medium">
                                    • Owner
                                </span>
                            </v-list-item-subtitle>
                        </v-list-item>
                    </v-list>

                    <v-divider class="my-2"></v-divider>

                    <v-list density="compact" nav>
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
                variant="flat"
                class="ml-2 font-weight-bold"
                :to="{ name: 'pricing' }"
            >
                {{ subscriptionStore.currentPlan.name }}
            </v-chip>

            <v-spacer></v-spacer>

            <!-- Notification area placeholder -->
            <v-btn icon="mdi-bell-outline" variant="text" class="mr-2">
                <v-badge dot color="primary">
                    <v-icon icon="mdi-bell-outline"></v-icon>
                </v-badge>
            </v-btn>

            <!-- User Menu -->
            <v-menu location="bottom end">
                <template #activator="{ props }">
                    <v-btn v-bind="props" variant="text" class="text-none">
                        <v-avatar color="primary" size="32" class="mr-2">
                            <span class="text-caption text-white font-weight-bold">
                                {{ authStore.user?.name?.charAt(0) || 'U' }}
                            </span>
                        </v-avatar>
                        <span class="d-none d-sm-inline font-weight-medium">{{ authStore.user?.name }}</span>
                        <v-icon icon="mdi-chevron-down" size="small" class="ml-1"></v-icon>
                    </v-btn>
                </template>

                <v-card min-width="220" class="pa-2 rounded-xl">
                    <div class="px-4 py-3">
                        <div class="font-weight-bold text-subtitle-2">{{ authStore.user?.name }}</div>
                        <div class="text-caption text-medium-emphasis text-truncate">{{ authStore.user?.email }}</div>
                    </div>
                    <v-divider class="my-1"></v-divider>
                    <v-list density="compact">
                        <v-list-item
                            prepend-icon="mdi-account-cog-outline"
                            title="Profile Settings"
                            :to="{ name: 'profile' }"
                            rounded="lg"
                        ></v-list-item>
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
            <v-container fluid class="pa-6">
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
