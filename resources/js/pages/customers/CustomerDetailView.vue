<template>
    <div class="mx-auto" style="max-width: 1100px">
        <!-- Back Navigation -->
        <div class="mb-4">
            <v-btn
                variant="text"
                prepend-icon="mdi-arrow-left"
                class="text-none font-weight-medium px-0"
                :to="{ name: 'customers' }"
            >
                Back to Customers
            </v-btn>
        </div>

        <!-- Loading State -->
        <div v-if="customerStore.loading && !customer" class="text-center py-16">
            <v-progress-circular indeterminate color="primary" size="48"></v-progress-circular>
            <div class="text-body-2 text-medium-emphasis mt-3">Loading customer details...</div>
        </div>

        <!-- Error State -->
        <v-alert
            v-else-if="customerStore.error && !customer"
            type="error"
            variant="tonal"
            class="rounded-xl"
        >
            {{ customerStore.error }}
        </v-alert>

        <!-- Main Details Content -->
        <div v-else-if="customer">
            <!-- Customer Header Banner -->
            <v-card class="rounded-xl border pa-6 mb-6" elevation="0">
                <div class="d-flex flex-column flex-md-row align-md-center justify-space-between gap-4">
                    <div class="d-flex align-center gap-4">
                        <v-avatar color="primary" variant="tonal" size="64" class="rounded-xl font-weight-bold text-h5">
                            {{ getInitials(customer.name) }}
                        </v-avatar>
                        <div>
                            <div class="d-flex align-center gap-3">
                                <h1 class="text-h4 font-weight-bold tracking-tight">
                                    {{ customer.name }}
                                </h1>
                                <v-chip
                                    :color="customer.status === 'active' ? 'success' : 'grey'"
                                    size="small"
                                    variant="tonal"
                                    class="font-weight-bold text-capitalize"
                                >
                                    {{ customer.status }}
                                </v-chip>
                            </div>
                            <div v-if="customer.company_name" class="text-subtitle-1 text-medium-emphasis">
                                {{ customer.company_name }}
                            </div>
                            <div class="text-caption text-medium-emphasis mt-1">
                                Customer ID #{{ customer.id }} &bull; Added {{ formatDate(customer.created_at) }}
                            </div>
                        </div>
                    </div>

                    <!-- Header Action Buttons -->
                    <div class="d-flex align-center gap-2 flex-wrap">
                        <v-btn
                            v-if="permissionsStore.can('customers.update')"
                            variant="outlined"
                            prepend-icon="mdi-pencil-outline"
                            class="text-none font-weight-bold rounded-lg"
                            @click="showEditDialog = true"
                        >
                            Edit
                        </v-btn>

                        <v-btn
                            v-if="customer.status === 'active' && permissionsStore.can('customers.delete')"
                            variant="tonal"
                            color="warning"
                            prepend-icon="mdi-archive-arrow-down-outline"
                            class="text-none font-weight-bold rounded-lg"
                            :loading="actionLoading"
                            @click="handleArchive"
                        >
                            Archive
                        </v-btn>

                        <v-btn
                            v-if="customer.status === 'archived' && permissionsStore.can('customers.update')"
                            variant="tonal"
                            color="success"
                            prepend-icon="mdi-restore"
                            class="text-none font-weight-bold rounded-lg"
                            :loading="actionLoading"
                            @click="handleRestore"
                        >
                            Restore
                        </v-btn>
                    </div>
                </div>
            </v-card>

            <!-- Success Alert -->
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

            <v-row>
                <!-- Left Column: Contact & Information -->
                <v-col cols="12" md="6">
                    <!-- Contact Details Card -->
                    <v-card class="rounded-xl border pa-5 mb-6" elevation="0">
                        <div class="text-subtitle-1 font-weight-bold mb-4 d-flex align-center gap-2 text-primary">
                            <v-icon icon="mdi-card-account-details-outline" size="20"></v-icon>
                            Contact Information
                        </div>

                        <v-list density="compact" class="pa-0">
                            <v-list-item class="px-0 py-2 border-b">
                                <template #prepend>
                                    <v-icon icon="mdi-email-outline" size="18" class="text-medium-emphasis mr-3"></v-icon>
                                </template>
                                <v-list-item-title class="text-caption text-medium-emphasis">Email</v-list-item-title>
                                <v-list-item-subtitle class="text-body-2 font-weight-medium text-high-emphasis">
                                    {{ customer.email || 'None provided' }}
                                </v-list-item-subtitle>
                            </v-list-item>

                            <v-list-item class="px-0 py-2 border-b">
                                <template #prepend>
                                    <v-icon icon="mdi-phone-outline" size="18" class="text-medium-emphasis mr-3"></v-icon>
                                </template>
                                <v-list-item-title class="text-caption text-medium-emphasis">Phone</v-list-item-title>
                                <v-list-item-subtitle class="text-body-2 font-weight-medium text-high-emphasis">
                                    {{ customer.phone || 'None provided' }}
                                </v-list-item-subtitle>
                            </v-list-item>

                            <v-list-item class="px-0 py-2 border-b">
                                <template #prepend>
                                    <v-icon icon="mdi-domain" size="18" class="text-medium-emphasis mr-3"></v-icon>
                                </template>
                                <v-list-item-title class="text-caption text-medium-emphasis">Company Name</v-list-item-title>
                                <v-list-item-subtitle class="text-body-2 font-weight-medium text-high-emphasis">
                                    {{ customer.company_name || 'None provided' }}
                                </v-list-item-subtitle>
                            </v-list-item>

                            <v-list-item class="px-0 py-2">
                                <template #prepend>
                                    <v-icon icon="mdi-file-document-outline" size="18" class="text-medium-emphasis mr-3"></v-icon>
                                </template>
                                <v-list-item-title class="text-caption text-medium-emphasis">Tax / VAT ID</v-list-item-title>
                                <v-list-item-subtitle class="text-body-2 font-weight-medium text-high-emphasis">
                                    {{ customer.tax_number || 'None provided' }}
                                </v-list-item-subtitle>
                            </v-list-item>
                        </v-list>
                    </v-card>

                    <!-- Internal Notes Card -->
                    <v-card class="rounded-xl border pa-5" elevation="0">
                        <div class="text-subtitle-1 font-weight-bold mb-3 d-flex align-center gap-2 text-primary">
                            <v-icon icon="mdi-note-text-outline" size="20"></v-icon>
                            Internal Notes
                        </div>
                        <p v-if="customer.notes" class="text-body-2 text-medium-emphasis white-space-pre-wrap">
                            {{ customer.notes }}
                        </p>
                        <p v-else class="text-caption text-disabled font-italic mb-0">
                            No internal notes recorded for this customer.
                        </p>
                    </v-card>
                </v-col>

                <!-- Right Column: Addresses & Invoice Summary -->
                <v-col cols="12" md="6">
                    <!-- Billing Address Card -->
                    <v-card class="rounded-xl border pa-5 mb-6" elevation="0">
                        <div class="text-subtitle-1 font-weight-bold mb-3 d-flex align-center gap-2 text-primary">
                            <v-icon icon="mdi-map-marker-outline" size="20"></v-icon>
                            Billing Address
                        </div>

                        <div v-if="hasBillingAddress" class="text-body-2 text-medium-emphasis lh-relaxed">
                            <div v-if="customer.billing_address" class="text-high-emphasis font-weight-medium">
                                {{ customer.billing_address }}
                            </div>
                            <div v-if="customer.billing_city || customer.billing_state || customer.billing_postal_code">
                                {{ [customer.billing_city, customer.billing_state, customer.billing_postal_code].filter(Boolean).join(', ') }}
                            </div>
                            <div v-if="customer.billing_country" class="font-weight-bold mt-1">
                                {{ customer.billing_country }}
                            </div>
                        </div>
                        <div v-else class="text-caption text-disabled font-italic">
                            No billing address provided.
                        </div>
                    </v-card>

                    <!-- Shipping Address Card -->
                    <v-card class="rounded-xl border pa-5 mb-6" elevation="0">
                        <div class="d-flex align-center justify-space-between mb-3">
                            <div class="text-subtitle-1 font-weight-bold d-flex align-center gap-2 text-primary">
                                <v-icon icon="mdi-truck-delivery-outline" size="20"></v-icon>
                                Shipping Address
                            </div>
                            <v-chip
                                v-if="customer.shipping_same_as_billing"
                                size="x-small"
                                color="info"
                                variant="tonal"
                                class="font-weight-medium"
                            >
                                Same as billing
                            </v-chip>
                        </div>

                        <div v-if="customer.shipping_same_as_billing && hasBillingAddress" class="text-body-2 text-medium-emphasis lh-relaxed">
                            <div v-if="customer.billing_address" class="text-high-emphasis font-weight-medium">
                                {{ customer.billing_address }}
                            </div>
                            <div>
                                {{ [customer.billing_city, customer.billing_state, customer.billing_postal_code].filter(Boolean).join(', ') }}
                            </div>
                            <div v-if="customer.billing_country" class="font-weight-bold mt-1">
                                {{ customer.billing_country }}
                            </div>
                        </div>
                        <div v-else-if="!customer.shipping_same_as_billing && hasShippingAddress" class="text-body-2 text-medium-emphasis lh-relaxed">
                            <div v-if="customer.shipping_address" class="text-high-emphasis font-weight-medium">
                                {{ customer.shipping_address }}
                            </div>
                            <div>
                                {{ [customer.shipping_city, customer.shipping_state, customer.shipping_postal_code].filter(Boolean).join(', ') }}
                            </div>
                            <div v-if="customer.shipping_country" class="font-weight-bold mt-1">
                                {{ customer.shipping_country }}
                            </div>
                        </div>
                        <div v-else class="text-caption text-disabled font-italic">
                            No separate shipping address provided.
                        </div>
                    </v-card>

                    <!-- Invoice & Billing Activity Placeholder -->
                    <v-card class="rounded-xl border pa-5" elevation="0">
                        <div class="text-subtitle-1 font-weight-bold mb-3 d-flex align-center gap-2 text-primary">
                            <v-icon icon="mdi-receipt-text-outline" size="20"></v-icon>
                            Invoices & Transactions
                        </div>
                        <div class="text-center py-6">
                            <v-icon icon="mdi-file-document-outline" size="32" color="medium-emphasis" class="mb-2"></v-icon>
                            <div class="text-caption text-medium-emphasis">
                                No invoices have been issued for this customer yet.
                            </div>
                            <div class="text-caption text-disabled mt-1">
                                Invoicing integration will be available in the upcoming phase.
                            </div>
                        </div>
                    </v-card>
                </v-col>
            </v-row>
        </div>

        <!-- Edit Customer Dialog -->
        <CustomerFormDialog
            v-if="customer"
            v-model="showEditDialog"
            :customer="customer"
            @saved="onCustomerUpdated"
        />
    </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { useCustomerStore } from '@/stores/customers';
import { usePermissionsStore } from '@/stores/permissions';
import CustomerFormDialog from '@/components/customers/CustomerFormDialog.vue';
import type { Customer } from '@/types';

const route = useRoute();
const customerStore = useCustomerStore();
const permissionsStore = usePermissionsStore();

const showEditDialog = ref(false);
const actionLoading = ref(false);
const successMessage = ref('');

const customer = computed(() => customerStore.currentCustomer);

const hasBillingAddress = computed(() => {
    if (!customer.value) return false;
    return Boolean(
        customer.value.billing_address ||
        customer.value.billing_city ||
        customer.value.billing_state ||
        customer.value.billing_postal_code ||
        customer.value.billing_country
    );
});

const hasShippingAddress = computed(() => {
    if (!customer.value) return false;
    return Boolean(
        customer.value.shipping_address ||
        customer.value.shipping_city ||
        customer.value.shipping_state ||
        customer.value.shipping_postal_code ||
        customer.value.shipping_country
    );
});

onMounted(async () => {
    const customerId = route.params.customer as string;
    if (customerId) {
        await customerStore.fetchCustomer(customerId);
    }
});

const getInitials = (name: string): string => {
    if (!name) return 'C';
    const parts = name.trim().split(/\s+/);
    if (parts.length >= 2) {
        return `${parts[0][0]}${parts[1][0]}`.toUpperCase();
    }
    return name.slice(0, 2).toUpperCase();
};

const formatDate = (dateStr: string): string => {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    return date.toLocaleDateString(undefined, {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    });
};

const onCustomerUpdated = (updated: Customer) => {
    successMessage.value = `Customer "${updated.name}" updated successfully.`;
};

const handleArchive = async () => {
    if (!customer.value) return;
    actionLoading.value = true;
    try {
        await customerStore.archiveCustomer(customer.value.id);
        successMessage.value = `Customer has been archived.`;
    } finally {
        actionLoading.value = false;
    }
};

const handleRestore = async () => {
    if (!customer.value) return;
    actionLoading.value = true;
    try {
        await customerStore.restoreCustomer(customer.value.id);
        successMessage.value = `Customer has been restored.`;
    } finally {
        actionLoading.value = false;
    }
};
</script>

<style scoped>
.white-space-pre-wrap {
    white-space: pre-wrap;
}
.lh-relaxed {
    line-height: 1.6;
}
</style>
