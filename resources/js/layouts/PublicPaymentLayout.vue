<template>
    <v-app class="public-payment-layout">
        <!-- Minimal Public Secure Header -->
        <header class="public-secure-header">
            <v-container class="d-flex align-center justify-space-between py-3 max-w-layout">
                <!-- Branding -->
                <div class="d-flex align-center gap-3">
                    <v-avatar color="primary" rounded="lg" size="38" class="elevation-1">
                        <v-icon icon="mdi-shield-check-outline" color="white" size="22"></v-icon>
                    </v-avatar>
                    <div>
                        <div class="text-subtitle-1 font-weight-bold text-slate-900 tracking-tight lh-1">
                            BillFlow <span class="text-primary font-weight-medium">Secure Pay</span>
                        </div>
                        <div class="text-caption text-medium-emphasis">
                            Verified Merchant Payment Gateway
                        </div>
                    </div>
                </div>

                <!-- Trust Badges -->
                <div class="d-flex align-center gap-2">
                    <v-chip
                        size="small"
                        color="success"
                        variant="tonal"
                        prepend-icon="mdi-lock-outline"
                        class="font-weight-medium text-caption d-none d-sm-inline-flex"
                    >
                        256-Bit Encrypted
                    </v-chip>
                    <v-chip
                        size="small"
                        color="indigo"
                        variant="tonal"
                        prepend-icon="mdi-shield-lock-outline"
                        class="font-weight-medium text-caption"
                    >
                        Stripe Secured
                    </v-chip>
                </div>
            </v-container>
        </header>

        <!-- Main Content -->
        <v-main class="public-payment-main">
            <v-container class="py-6 py-md-10 max-w-layout">
                <slot />
            </v-container>
        </v-main>

        <!-- Minimal Public Secure Footer -->
        <footer class="public-secure-footer py-6 border-t">
            <v-container class="max-w-layout text-center">
                <div class="d-flex flex-wrap align-center justify-center gap-4 mb-3 text-caption text-medium-emphasis">
                    <span class="d-inline-flex align-center">
                        <v-icon icon="mdi-shield-check" size="14" color="success" class="mr-1"></v-icon>
                        PCI-DSS Level 1 Compliant
                    </span>
                    <span class="text-disabled">•</span>
                    <span class="d-inline-flex align-center">
                        <v-icon icon="mdi-lock" size="14" color="primary" class="mr-1"></v-icon>
                        End-to-End SSL Encryption
                    </span>
                    <span class="text-disabled">•</span>
                    <span class="d-inline-flex align-center">
                        <v-icon icon="mdi-credit-card-check-outline" size="14" color="indigo" class="mr-1"></v-icon>
                        Powered by Stripe
                    </span>
                </div>
                <div class="text-caption text-disabled">
                    &copy; {{ new Date().getFullYear() }} BillFlow Inc. All card transactions are securely routed through Stripe. No card details are stored on our servers.
                </div>
            </v-container>
        </footer>
    </v-app>
</template>

<script setup lang="ts">
// Dedicated standalone layout for public customer payments.
// Does NOT include the authenticated sidebar, app bar, workspace selector, or user profile.
</script>

<style scoped>
.public-payment-layout {
    background: radial-gradient(ellipse at 50% 0%, rgba(99, 102, 241, 0.04) 0%, rgba(248, 250, 252, 1) 75%);
    min-height: 100vh;
    color: #1e293b;
}

.public-secure-header {
    background-color: #ffffff;
    border-bottom: 1px solid rgba(226, 232, 240, 0.8);
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.03);
}

.public-payment-main {
    min-height: calc(100vh - 130px);
}

.public-secure-footer {
    background-color: #ffffff;
    border-top: 1px solid rgba(226, 232, 240, 0.8);
}

.max-w-layout {
    max-width: 1080px;
}

.lh-1 {
    line-height: 1.2;
}

.gap-2 {
    gap: 8px;
}

.gap-3 {
    gap: 12px;
}

.gap-4 {
    gap: 16px;
}

@media print {
    .public-secure-header,
    .public-secure-footer {
        display: none !important;
    }
    .public-payment-layout {
        background: #ffffff !important;
    }
    .public-payment-main {
        padding: 0 !important;
    }
}
</style>
