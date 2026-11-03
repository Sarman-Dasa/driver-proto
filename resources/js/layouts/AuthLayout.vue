<template>
    <v-app class="auth-layout">
        <v-main class="d-flex align-center justify-center py-10 px-4">
            <v-container style="max-width: 460px" class="pa-0">
                <!-- Branding -->
                <div class="text-center mb-6">
                    <div class="d-inline-flex align-center justify-center pa-3 rounded-xl bg-primary elevation-2 mb-3">
                        <v-icon icon="mdi-shield-check-outline" size="32" color="white"></v-icon>
                    </div>
                    <h1 class="text-h4 font-weight-bold text-slate-900 tracking-tight">BillFlow</h1>
                    <p class="text-subtitle-2 text-medium-emphasis mt-1">Financial Operations &amp; Billing Platform</p>
                </div>

                <!-- Form Card -->
                <v-card class="pa-6 pa-sm-8 rounded-xl border elevation-1">
                    <slot />
                </v-card>

                <!-- Footer -->
                <div class="text-center mt-6 text-caption text-medium-emphasis">
                    &copy; {{ new Date().getFullYear() }} BillFlow SaaS. All rights reserved.
                </div>
            </v-container>
        </v-main>
    </v-app>
</template>

<script setup lang="ts"></script>

<style scoped>
.auth-layout {
    background: radial-gradient(circle at 50% 15%, rgba(79, 70, 229, 0.06) 0%, rgba(248, 250, 252, 1) 70%);
    min-height: 100vh;
}
</style>
