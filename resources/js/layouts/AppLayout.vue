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
                    prepend-icon="mdi-office-building-plus-outline"
                    title="New Workspace"
                    value="onboarding"
                    :to="{ name: 'onboarding' }"
                    color="primary"
                ></v-list-item>
            </v-list>
        </v-navigation-drawer>

        <!-- Top Navigation Bar -->
        <v-app-bar elevation="1" color="surface">
            <v-app-bar-nav-icon @click="drawer = !drawer"></v-app-bar-nav-icon>

            <!-- Company Context Switcher -->
            <v-menu v-if="companyStore.companies.length > 0" location="bottom start" :close-on-content-click="false">
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

                <v-card min-width="260" class="pa-2 rounded-xl">
                    <div class="text-caption font-weight-bold px-3 py-2 text-uppercase text-medium-emphasis">
                        Workspaces
                    </div>

                    <v-list density="compact">
                        <v-list-item
                            v-for="company in companyStore.companies"
                            :key="company.id"
                            :value="company.id"
                            :active="companyStore.currentCompany?.id === company.id"
                            color="primary"
                            rounded="lg"
                            @click="handleSwitchCompany(company.id)"
                        >
                            <template #prepend>
                                <v-icon
                                    :icon="companyStore.currentCompany?.id === company.id ? 'mdi-check-circle' : 'mdi-domain'"
                                    :color="companyStore.currentCompany?.id === company.id ? 'primary' : 'grey'"
                                ></v-icon>
                            </template>
                            <v-list-item-title class="font-weight-medium">{{ company.name }}</v-list-item-title>
                            <v-list-item-subtitle class="text-caption">
                                <span :class="company.is_active ? 'text-success' : 'text-error'">
                                    {{ company.status }}
                                </span>
                            </v-list-item-subtitle>
                        </v-list-item>
                    </v-list>

                    <v-divider class="my-2"></v-divider>

                    <v-btn
                        block
                        variant="text"
                        color="primary"
                        prepend-icon="mdi-plus"
                        class="text-none"
                        :to="{ name: 'onboarding' }"
                    >
                        Add Workspace
                    </v-btn>
                </v-card>
            </v-menu>

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

const drawer = ref(true);
const router = useRouter();
const authStore = useAuthStore();
const companyStore = useCompanyStore();

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
