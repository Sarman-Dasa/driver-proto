<template>
    <div>
        <!-- Welcome Header -->
        <div class="d-flex flex-column flex-sm-row align-start align-sm-center justify-space-between mb-8">
            <div>
                <h1 class="text-h4 font-weight-bold tracking-tight">
                    Welcome back, {{ authStore.user?.name }}
                </h1>
                <p class="text-subtitle-1 text-medium-emphasis mt-1">
                    BillFlow Phase 0 Foundation Overview &bull; Active Workspace:
                    <span class="font-weight-semibold text-primary">
                        {{ companyStore.currentCompany?.name }}
                    </span>
                </p>
            </div>

            <v-chip
                color="success"
                variant="flat"
                prepend-icon="mdi-check-decagram"
                size="large"
                class="mt-4 mt-sm-0 font-weight-bold"
            >
                System Ready &bull; Phase 0
            </v-chip>
        </div>

        <v-row class="mb-6">
            <!-- Active Workspace Card -->
            <v-col cols="12" md="6">
                <v-card class="pa-6 rounded-xl border elevation-1 h-100">
                    <div class="d-flex align-center justify-space-between mb-4">
                        <div class="d-flex align-center gap-3">
                            <v-avatar color="primary" rounded="lg" size="44">
                                <v-icon icon="mdi-domain" color="white" size="24"></v-icon>
                            </v-avatar>
                            <div>
                                <div class="text-h6 font-weight-bold">
                                    {{ companyStore.currentCompany?.name }}
                                </div>
                                <div class="text-caption text-medium-emphasis">
                                    Slug: {{ companyStore.currentCompany?.slug }}
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
                        <v-list-item class="px-0">
                            <template #prepend>
                                <v-icon icon="mdi-identifier" size="small" class="mr-2 text-medium-emphasis"></v-icon>
                            </template>
                            <v-list-item-title class="text-body-2">Workspace ID</v-list-item-title>
                            <template #append>
                                <span class="font-weight-mono text-caption font-weight-bold">
                                    #{{ companyStore.currentCompany?.id }}
                                </span>
                            </template>
                        </v-list-item>

                        <v-list-item class="px-0">
                            <template #prepend>
                                <v-icon icon="mdi-email-outline" size="small" class="mr-2 text-medium-emphasis"></v-icon>
                            </template>
                            <v-list-item-title class="text-body-2">Workspace Email</v-list-item-title>
                            <template #append>
                                <span class="text-caption text-medium-emphasis">
                                    {{ companyStore.currentCompany?.email || 'None configured' }}
                                </span>
                            </template>
                        </v-list-item>

                        <v-list-item class="px-0">
                            <template #prepend>
                                <v-icon icon="mdi-shield-account-outline" size="small" class="mr-2 text-medium-emphasis"></v-icon>
                            </template>
                            <v-list-item-title class="text-body-2">Company Context Resolution</v-list-item-title>
                            <template #append>
                                <v-chip size="x-small" color="primary" variant="flat">
                                    Enforced via Middleware
                                </v-chip>
                            </template>
                        </v-list-item>
                    </v-list>
                </v-card>
            </v-col>

            <!-- Foundation Architecture Status -->
            <v-col cols="12" md="6">
                <v-card class="pa-6 rounded-xl border elevation-1 h-100">
                    <div class="text-h6 font-weight-bold mb-1">Architecture Foundation Status</div>
                    <div class="text-caption text-medium-emphasis mb-4">
                        Core capabilities configured and ready for Phase 1 modules
                    </div>

                    <v-row dense>
                        <v-col cols="6">
                            <div class="pa-3 rounded-lg border bg-slate-50">
                                <div class="text-caption text-medium-emphasis">Backend Core</div>
                                <div class="text-body-2 font-weight-bold text-success">Laravel 13 &bull; PHP 8.3</div>
                            </div>
                        </v-col>
                        <v-col cols="6">
                            <div class="pa-3 rounded-lg border bg-slate-50">
                                <div class="text-caption text-medium-emphasis">Frontend Core</div>
                                <div class="text-body-2 font-weight-bold text-success">Vue 3 + Vuetify 3</div>
                            </div>
                        </v-col>
                        <v-col cols="6">
                            <div class="pa-3 rounded-lg border bg-slate-50">
                                <div class="text-caption text-medium-emphasis">Authentication</div>
                                <div class="text-body-2 font-weight-bold text-success">Laravel Sanctum</div>
                            </div>
                        </v-col>
                        <v-col cols="6">
                            <div class="pa-3 rounded-lg border bg-slate-50">
                                <div class="text-caption text-medium-emphasis">Company Data Isolation</div>
                                <div class="text-body-2 font-weight-bold text-success">Active &bull; company_id</div>
                            </div>
                        </v-col>
                    </v-row>

                    <div class="mt-4 pa-3 rounded-lg bg-indigo-50 border border-indigo-100">
                        <div class="d-flex align-center gap-2 text-indigo-900 text-caption font-weight-medium">
                            <v-icon icon="mdi-information-outline" size="small" color="primary"></v-icon>
                            <span>Phase 0 complete. Subsequent modules will build securely on this foundation.</span>
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

const authStore = useAuthStore();
const companyStore = useCompanyStore();
</script>
