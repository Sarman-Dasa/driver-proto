<template>
    <div class="py-8 px-4 mx-auto" style="max-width: 600px;">
        <v-card class="rounded-2xl border pa-8 text-center bg-white elevation-1">
            <div class="d-inline-flex align-center justify-center rounded-circle bg-slate-100 pa-4 mb-5 border border-slate-200">
                <v-icon icon="mdi-cancel" color="#64748B" size="56"></v-icon>
            </div>
            <h1 class="text-h5 font-weight-bold text-slate-900 mb-2">
                Checkout Incomplete
            </h1>
            <p class="text-body-1 text-slate-600 mb-6">
                Your Stripe checkout session was cancelled. No charges were made to your account.
                You can try upgrading again at any time.
            </p>

            <div class="d-flex flex-column flex-sm-row justify-center gap-3">
                <v-btn
                    color="primary"
                    size="large"
                    variant="flat"
                    class="rounded-lg text-none font-weight-bold px-6"
                    to="/pricing"
                >
                    View Pricing Plans
                </v-btn>
                <v-btn
                    variant="outlined"
                    size="large"
                    class="rounded-lg text-none font-weight-bold px-6 ml-sm-3 mt-3 mt-sm-0"
                    to="/dashboard"
                >
                    Return to Dashboard
                </v-btn>
            </div>
        </v-card>
    </div>
</template>

<style scoped>
.text-slate-900 {
    color: #0f172a !important;
}
.text-slate-600 {
    color: #475569 !important;
}
.bg-slate-100 {
    background-color: #f1f5f9 !important;
}
.border-slate-200 {
    border-color: #e2e8f0 !important;
}
</style>
