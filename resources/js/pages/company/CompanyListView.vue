<template>
    <v-container style="max-width: 960px" class="py-6">
        <div class="d-flex flex-column flex-sm-row align-sm-center justify-space-between mb-6 gap-4">
            <div>
                <h1 class="text-h4 font-weight-bold tracking-tight">Workspaces</h1>
                <p class="text-subtitle-1 text-medium-emphasis mt-1">
                    Manage your active company workspaces and switch between business contexts
                </p>
            </div>
            <v-btn
                color="primary"
                prepend-icon="mdi-plus"
                class="text-none font-weight-bold rounded-lg"
                size="large"
                :to="{ name: 'companies.create' }"
            >
                Create Workspace
            </v-btn>
        </div>

        <v-alert
            v-if="errorMessage"
            type="error"
            variant="tonal"
            density="comfortable"
            class="mb-6 rounded-lg"
            closable
            @click:close="errorMessage = ''"
        >
            {{ errorMessage }}
        </v-alert>

        <v-alert
            v-if="successMessage"
            type="success"
            variant="tonal"
            density="comfortable"
            class="mb-6 rounded-lg"
            closable
            @click:close="successMessage = ''"
        >
            {{ successMessage }}
        </v-alert>

        <v-row v-if="companyStore.companies.length > 0">
            <v-col
                v-for="company in companyStore.companies"
                :key="company.id"
                cols="12"
                md="6"
            >
                <v-card
                    class="rounded-xl border pa-5 h-100 d-flex flex-column transition-swing"
                    :elevation="companyStore.currentCompany?.id === company.id ? 3 : 1"
                    :style="companyStore.currentCompany?.id === company.id ? 'border-color: rgb(var(--v-theme-primary)) !important; border-width: 2px !important;' : ''"
                >
                    <div class="d-flex align-start justify-space-between mb-3">
                        <div class="d-flex align-center gap-3">
                            <v-avatar color="primary" variant="tonal" size="44" rounded="lg">
                                <v-icon icon="mdi-domain" size="24" color="primary"></v-icon>
                            </v-avatar>
                            <div class="ml-3">
                                <h3 class="text-h6 font-weight-bold">{{ company.name }}</h3>
                                <div class="text-caption text-medium-emphasis text-truncate" style="max-width: 220px">
                                    {{ company.email || company.slug }}
                                </div>
                            </div>
                        </div>

                        <div class="d-flex flex-column align-end gap-1">
                            <v-chip
                                :color="company.is_active ? 'success' : 'error'"
                                size="small"
                                variant="flat"
                                class="font-weight-medium"
                            >
                                {{ company.status }}
                            </v-chip>
                            <v-chip
                                v-if="company.is_owner"
                                color="primary"
                                size="x-small"
                                variant="tonal"
                                class="mt-1"
                            >
                                Owner
                            </v-chip>
                        </div>
                    </div>

                    <div class="text-body-2 text-medium-emphasis mb-4 flex-grow-1">
                        <div v-if="company.phone" class="d-flex align-center gap-2 mb-1">
                            <v-icon icon="mdi-phone-outline" size="small" class="mr-1"></v-icon>
                            <span>{{ company.phone }}</span>
                        </div>
                        <div v-if="company.city || company.country" class="d-flex align-center gap-2">
                            <v-icon icon="mdi-map-marker-outline" size="small" class="mr-1"></v-icon>
                            <span>{{ [company.city, company.country].filter(Boolean).join(', ') }}</span>
                        </div>
                    </div>

                    <v-divider class="mb-4"></v-divider>

                    <div class="d-flex align-center justify-space-between gap-2">
                        <v-btn
                            variant="outlined"
                            size="small"
                            prepend-icon="mdi-cog-outline"
                            class="text-none font-weight-medium rounded-lg"
                            :to="{ name: 'companies.settings', params: { company: company.id } }"
                        >
                            Settings
                        </v-btn>

                        <div>
                            <v-chip
                                v-if="companyStore.currentCompany?.id === company.id"
                                color="primary"
                                variant="flat"
                                size="small"
                                prepend-icon="mdi-check"
                                class="font-weight-bold px-3"
                            >
                                Current Workspace
                            </v-chip>

                            <v-btn
                                v-else-if="company.is_active"
                                color="primary"
                                variant="tonal"
                                size="small"
                                class="text-none font-weight-bold rounded-lg"
                                :loading="switchingId === company.id"
                                @click="handleSwitch(company.id)"
                            >
                                Switch
                            </v-btn>

                            <v-chip
                                v-else
                                color="grey"
                                variant="outlined"
                                size="small"
                            >
                                Inactive
                            </v-chip>
                        </div>
                    </div>
                </v-card>
            </v-col>
        </v-row>

        <v-card v-else class="text-center pa-12 rounded-xl border">
            <v-avatar color="primary" variant="tonal" size="64" class="mb-4">
                <v-icon icon="mdi-office-building-outline" size="36" color="primary"></v-icon>
            </v-avatar>
            <h2 class="text-h5 font-weight-bold">No Workspaces Found</h2>
            <p class="text-subtitle-1 text-medium-emphasis mt-2 mb-6" style="max-width: 480px; margin: auto;">
                You are not currently associated with any company workspace. Create your first workspace to begin.
            </p>
            <v-btn
                color="primary"
                size="large"
                prepend-icon="mdi-plus"
                class="text-none font-weight-bold rounded-lg px-8"
                :to="{ name: 'companies.create' }"
            >
                Create Workspace
            </v-btn>
        </v-card>
    </v-container>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useCompanyStore } from '@/stores/company';
import type { ApiError } from '@/types';

const companyStore = useCompanyStore();
const switchingId = ref<number | null>(null);
const errorMessage = ref('');
const successMessage = ref('');

onMounted(async () => {
    try {
        await companyStore.fetchCompanies();
    } catch (err: unknown) {
        const apiError = err as ApiError;
        errorMessage.value = apiError.message || 'Failed to load workspaces.';
    }
});

const handleSwitch = async (companyId: number) => {
    switchingId.value = companyId;
    errorMessage.value = '';
    successMessage.value = '';

    try {
        const switched = await companyStore.switchCompany(companyId);
        successMessage.value = `Switched to ${switched.name}.`;
    } catch (err: unknown) {
        const apiError = err as ApiError;
        errorMessage.value = apiError.message || 'Failed to switch workspace.';
    } finally {
        switchingId.value = null;
    }
};
</script>
