<template>
    <v-container fluid class="pa-6" style="max-width: 1200px">
        <!-- Header & Breadcrumbs -->
        <div class="mb-6">
            <v-btn
                variant="text"
                prepend-icon="mdi-arrow-left"
                class="text-none font-weight-medium mb-2 px-0"
                :to="{ name: 'invoices.detail', params: { invoice: invoiceId } }"
            >
                Back to Invoice
            </v-btn>
            <h1 class="text-h4 font-weight-bold tracking-tight">
                Edit Invoice {{ invoice?.invoice_number }}
            </h1>
            <p class="text-subtitle-1 text-medium-emphasis">
                Modify line items, quantities, pricing, and client billing terms for this draft invoice
            </p>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="text-center py-12">
            <v-progress-circular indeterminate color="primary"></v-progress-circular>
            <div class="text-caption text-medium-emphasis mt-2">Loading invoice details...</div>
        </div>

        <!-- Non-editable Warning -->
        <v-alert
            v-else-if="invoice && !invoice.is_editable"
            type="warning"
            variant="tonal"
            class="rounded-xl mb-6"
        >
            This invoice has status <strong>{{ invoice.status_label || invoice.status }}</strong> and cannot be edited. Only draft invoices can be modified.
        </v-alert>

        <!-- Form Component -->
        <InvoiceForm
            v-else-if="invoice"
            :is-edit="true"
            :initial-invoice="invoice"
        />
    </v-container>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { useInvoiceStore } from '@/stores/invoices';
import InvoiceForm from '@/components/invoices/InvoiceForm.vue';
import type { Invoice } from '@/types';

const route = useRoute();
const invoiceStore = useInvoiceStore();
const invoiceId = Number(route.params.invoice);

const loading = ref(true);
const invoice = ref<Invoice | null>(null);

onMounted(async () => {
    loading.value = true;
    try {
        invoice.value = await invoiceStore.fetchInvoice(invoiceId);
    } finally {
        loading.value = false;
    }
});
</script>
