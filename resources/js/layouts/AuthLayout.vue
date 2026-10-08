<template>
    <v-app class="auth-layout">
        <v-main class="d-flex align-center justify-center py-12 px-4">
            <v-container style="max-width: 480px">
                <div class="text-center mb-8">
                    <div class="d-inline-flex align-center justify-center pa-3 rounded-xl bg-primary elevation-3 mb-4">
                        <v-icon icon="mdi-shield-check-outline" size="36" color="white"></v-icon>
                    </div>
                    <h1 class="text-h4 font-weight-bold text-slate-900 tracking-tight">BillFlow</h1>
                    <p class="text-subtitle-1 text-medium-emphasis mt-1">Financial Operations &amp; Billing Platform</p>
                </div>

                <v-card class="pa-6 pa-sm-8 elevation-4 rounded-xl border">
                    <slot />
                </v-card>

                <div class="text-center mt-6 text-caption text-disabled">
                    &copy; {{ new Date().getFullYear() }} BillFlow Inc. All rights reserved.
                </div>
            </v-container>
        </v-main>
    </v-app>
</template>

<script setup lang="ts"></script>

<style scoped>
.auth-layout {
    background: radial-gradient(circle at 10% 20%, rgba(79, 70, 229, 0.05) 0%, rgba(248, 250, 252, 1) 90%);
    min-height: 100vh;
}
</style>
