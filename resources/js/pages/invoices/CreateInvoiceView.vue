<template>
    <div class="mx-auto" style="max-width: 1200px">
        <!-- Header & Breadcrumbs -->
        <div class="mb-6">
            <v-btn
                variant="text"
                prepend-icon="mdi-arrow-left"
                class="text-none font-weight-medium mb-2 px-0"
                :to="{ name: 'invoices' }"
            >
                Back to Invoices
            </v-btn>
            <h1 class="text-h4 font-weight-bold tracking-tight text-slate-900">Create Invoice</h1>
            <p class="text-subtitle-1 text-medium-emphasis">
                Draft a new invoice with client billing details and custom or catalog line items
            </p>
        </div>

        <!-- Form Component -->
        <InvoiceForm :is-edit="false" />
    </div>
</template>

<script setup lang="ts">
import InvoiceForm from '@/components/invoices/InvoiceForm.vue';
</script>
