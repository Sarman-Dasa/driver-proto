<template>
    <v-card class="pa-8 text-center rounded-xl border elevation-0 upgrade-card">
        <v-avatar color="primary" variant="tonal" size="64" class="mb-4">
            <v-icon :icon="icon" size="30" color="primary"></v-icon>
        </v-avatar>

        <h3 class="text-h6 font-weight-bold text-slate-900 mb-2">{{ title }}</h3>
        
        <p class="text-body-2 text-medium-emphasis mb-4 mx-auto" style="max-width: 440px;">
            {{ description }}
        </p>

        <v-chip
            v-if="requiredPlan"
            color="primary"
            variant="tonal"
            size="small"
            class="font-weight-bold mb-5"
        >
            <v-icon icon="mdi-shield-star-outline" start size="14"></v-icon>
            Available on {{ requiredPlan }} Plan
        </v-chip>

        <div class="d-flex justify-center gap-2">
            <v-btn
                color="primary"
                variant="flat"
                prepend-icon="mdi-arrow-up-bold-circle-outline"
                class="text-none font-weight-bold rounded-lg px-6"
                :to="{ name: 'pricing' }"
            >
                View Plans & Upgrade
            </v-btn>
        </div>
    </v-card>
</template>

<script setup lang="ts">
withDefaults(
    defineProps<{
        title: string;
        description: string;
        icon?: string;
        requiredPlan?: string;
    }>(),
    {
        icon: 'mdi-lock-outline',
        requiredPlan: 'Pro',
    }
);
</script>

<style scoped>
.upgrade-card {
    background: linear-gradient(180deg, rgba(248, 250, 252, 0.8) 0%, rgba(255, 255, 255, 1) 100%) !important;
}
</style>
