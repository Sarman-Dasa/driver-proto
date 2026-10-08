<template>
    <v-container fluid class="pa-6" style="max-width: 1100px">
        <!-- Back Navigation -->
        <div class="mb-4">
            <v-btn
                variant="text"
                prepend-icon="mdi-arrow-left"
                class="text-none font-weight-medium px-0"
                :to="{ name: 'products' }"
            >
                Back to Products & Services
            </v-btn>
        </div>

        <!-- Loading State -->
        <div v-if="productStore.loading && !product" class="text-center py-16">
            <v-progress-circular indeterminate color="primary" size="48"></v-progress-circular>
            <div class="text-body-2 text-medium-emphasis mt-3">Loading item details...</div>
        </div>

        <!-- Error State -->
        <v-alert
            v-else-if="productStore.error && !product"
            type="error"
            variant="tonal"
            class="rounded-xl"
        >
            {{ productStore.error }}
        </v-alert>

        <!-- Main Details Content -->
        <div v-else-if="product">
            <!-- Product Header Banner -->
            <v-card class="rounded-xl border pa-6 mb-6" elevation="0">
                <div class="d-flex flex-column flex-md-row align-md-center justify-space-between gap-4">
                    <div class="d-flex align-center gap-4">
                        <v-avatar
                            :color="product.type === 'service' ? 'deep-purple' : 'primary'"
                            variant="tonal"
                            size="64"
                            class="rounded-xl"
                        >
                            <v-icon
                                :icon="product.type === 'service' ? 'mdi-briefcase-outline' : 'mdi-package-variant-closed'"
                                size="32"
                            ></v-icon>
                        </v-avatar>
                        <div>
                            <div class="d-flex align-center gap-2 flex-wrap">
                                <h1 class="text-h4 font-weight-bold tracking-tight">
                                    {{ product.name }}
                                </h1>
                                <v-chip
                                    :color="product.type === 'service' ? 'deep-purple' : 'primary'"
                                    size="small"
                                    variant="tonal"
                                    class="font-weight-bold text-capitalize"
                                >
                                    {{ product.type_label || product.type }}
                                </v-chip>
                                <v-chip
                                    :color="product.status === 'active' ? 'success' : 'grey'"
                                    size="small"
                                    variant="tonal"
                                    class="font-weight-bold text-capitalize"
                                >
                                    {{ product.status }}
                                </v-chip>
                            </div>
                            <div class="text-subtitle-1 text-medium-emphasis mt-1">
                                <span class="font-weight-bold text-high-emphasis">
                                    {{ product.formatted_price || ('$' + (product.price / 100).toFixed(2)) }}
                                </span>
                                <span v-if="product.unit"> / {{ product.unit }}</span>
                                <span class="ml-2 text-caption text-disabled text-uppercase">({{ product.currency }})</span>
                            </div>
                            <div class="text-caption text-medium-emphasis mt-1">
                                <span v-if="product.sku">SKU: {{ product.sku }} &bull; </span>
                                Item ID #{{ product.id }} &bull; Added {{ formatDate(product.created_at) }}
                            </div>
                        </div>
                    </div>

                    <!-- Header Action Buttons -->
                    <div class="d-flex align-center gap-2 flex-wrap">
                        <v-btn
                            v-if="permissionsStore.can('products.update')"
                            variant="outlined"
                            prepend-icon="mdi-pencil-outline"
                            class="text-none font-weight-bold rounded-lg"
                            @click="showEditDialog = true"
                        >
                            Edit Item
                        </v-btn>

                        <v-btn
                            v-if="permissionsStore.can('products.update')"
                            variant="tonal"
                            :color="product.status === 'active' ? 'warning' : 'success'"
                            :prepend-icon="product.status === 'active' ? 'mdi-eye-off-outline' : 'mdi-eye-check-outline'"
                            class="text-none font-weight-bold rounded-lg"
                            :loading="actionLoading"
                            @click="handleToggleStatus"
                        >
                            {{ product.status === 'active' ? 'Deactivate' : 'Activate' }}
                        </v-btn>

                        <v-btn
                            v-if="permissionsStore.can('products.delete')"
                            variant="tonal"
                            color="error"
                            prepend-icon="mdi-delete-outline"
                            class="text-none font-weight-bold rounded-lg"
                            :loading="actionLoading"
                            @click="showDeleteDialog = true"
                        >
                            Delete
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
                <!-- Left Column: Item Information -->
                <v-col cols="12" md="6">
                    <!-- General Details Card -->
                    <v-card class="rounded-xl border pa-5 mb-6" elevation="0">
                        <div class="text-subtitle-1 font-weight-bold mb-4 d-flex align-center gap-2 text-primary">
                            <v-icon icon="mdi-information-outline" size="20"></v-icon>
                            General Information
                        </div>

                        <v-list density="compact" class="pa-0">
                            <v-list-item class="px-0 py-2 border-b">
                                <template #prepend>
                                    <v-icon icon="mdi-tag-outline" size="18" class="text-medium-emphasis mr-3"></v-icon>
                                </template>
                                <v-list-item-title class="text-caption text-medium-emphasis">Type</v-list-item-title>
                                <v-list-item-subtitle class="text-body-2 font-weight-medium text-high-emphasis text-capitalize">
                                    {{ product.type_label || product.type }}
                                </v-list-item-subtitle>
                            </v-list-item>

                            <v-list-item class="px-0 py-2 border-b">
                                <template #prepend>
                                    <v-icon icon="mdi-barcode-scan" size="18" class="text-medium-emphasis mr-3"></v-icon>
                                </template>
                                <v-list-item-title class="text-caption text-medium-emphasis">SKU / Code</v-list-item-title>
                                <v-list-item-subtitle class="text-body-2 font-weight-medium text-high-emphasis">
                                    {{ product.sku || 'None assigned' }}
                                </v-list-item-subtitle>
                            </v-list-item>

                            <v-list-item class="px-0 py-2 border-b">
                                <template #prepend>
                                    <v-icon icon="mdi-scale-balance" size="18" class="text-medium-emphasis mr-3"></v-icon>
                                </template>
                                <v-list-item-title class="text-caption text-medium-emphasis">Default Billing Unit</v-list-item-title>
                                <v-list-item-subtitle class="text-body-2 font-weight-medium text-high-emphasis text-capitalize">
                                    {{ product.unit || 'unit' }}
                                </v-list-item-subtitle>
                            </v-list-item>

                            <v-list-item class="px-0 py-2">
                                <template #prepend>
                                    <v-icon icon="mdi-clock-outline" size="18" class="text-medium-emphasis mr-3"></v-icon>
                                </template>
                                <v-list-item-title class="text-caption text-medium-emphasis">Last Updated</v-list-item-title>
                                <v-list-item-subtitle class="text-body-2 font-weight-medium text-high-emphasis">
                                    {{ formatDate(product.updated_at) }}
                                </v-list-item-subtitle>
                            </v-list-item>
                        </v-list>
                    </v-card>

                    <!-- Description Card -->
                    <v-card class="rounded-xl border pa-5" elevation="0">
                        <div class="text-subtitle-1 font-weight-bold mb-3 d-flex align-center gap-2 text-primary">
                            <v-icon icon="mdi-text-box-outline" size="20"></v-icon>
                            Description (Invoice Line Template)
                        </div>
                        <p v-if="product.description" class="text-body-2 text-medium-emphasis lh-relaxed white-space-pre-wrap">
                            {{ product.description }}
                        </p>
                        <p v-else class="text-caption text-disabled font-italic mb-0">
                            No description provided. This item will populate with its name by default on invoices.
                        </p>
                    </v-card>
                </v-col>

                <!-- Right Column: Pricing & Future Invoicing -->
                <v-col cols="12" md="6">
                    <!-- Pricing Card -->
                    <v-card class="rounded-xl border pa-5 mb-6" elevation="0">
                        <div class="text-subtitle-1 font-weight-bold mb-4 d-flex align-center gap-2 text-primary">
                            <v-icon icon="mdi-currency-usd" size="20"></v-icon>
                            Pricing & Tax Settings
                        </div>

                        <v-list density="compact" class="pa-0">
                            <v-list-item class="px-0 py-2 border-b">
                                <template #prepend>
                                    <v-icon icon="mdi-cash-multiple" size="18" class="text-medium-emphasis mr-3"></v-icon>
                                </template>
                                <v-list-item-title class="text-caption text-medium-emphasis">Unit Base Price</v-list-item-title>
                                <v-list-item-subtitle class="text-h6 font-weight-bold text-primary">
                                    {{ product.formatted_price || ('$' + (product.price / 100).toFixed(2)) }}
                                </v-list-item-subtitle>
                            </v-list-item>

                            <v-list-item class="px-0 py-2 border-b">
                                <template #prepend>
                                    <v-icon icon="mdi-percent" size="18" class="text-medium-emphasis mr-3"></v-icon>
                                </template>
                                <v-list-item-title class="text-caption text-medium-emphasis">Standard Tax Rate</v-list-item-title>
                                <v-list-item-subtitle class="text-body-2 font-weight-medium text-high-emphasis">
                                    {{ product.tax_rate > 0 ? product.tax_rate + '%' : '0% (Exempt)' }}
                                </v-list-item-subtitle>
                            </v-list-item>

                            <v-list-item class="px-0 py-2">
                                <template #prepend>
                                    <v-icon icon="mdi-cash" size="18" class="text-medium-emphasis mr-3"></v-icon>
                                </template>
                                <v-list-item-title class="text-caption text-medium-emphasis">Billing Currency</v-list-item-title>
                                <v-list-item-subtitle class="text-body-2 font-weight-bold text-high-emphasis text-uppercase">
                                    {{ product.currency }}
                                </v-list-item-subtitle>
                            </v-list-item>
                        </v-list>
                    </v-card>

                    <!-- Invoicing Architecture Info Card -->
                    <v-card class="rounded-xl border pa-5" elevation="0">
                        <div class="text-subtitle-1 font-weight-bold mb-3 d-flex align-center gap-2 text-primary">
                            <v-icon icon="mdi-receipt-text-outline" size="20"></v-icon>
                            Invoice Integration
                        </div>
                        <div class="d-flex align-start gap-3">
                            <v-icon icon="mdi-shield-check-outline" color="success" size="22" class="mt-1"></v-icon>
                            <div class="text-body-2 text-medium-emphasis lh-relaxed">
                                When selected on future invoices, items will generate an <strong>immutable snapshot</strong> of price, tax, and description, ensuring future price updates never alter historical invoices.
                            </div>
                        </div>
                    </v-card>
                </v-col>
            </v-row>
        </div>

        <!-- Edit Dialog -->
        <ProductFormDialog
            v-if="product"
            v-model="showEditDialog"
            :product="product"
            @saved="onProductUpdated"
        />

        <!-- Delete Confirmation Dialog -->
        <v-dialog v-model="showDeleteDialog" max-width="480">
            <v-card class="rounded-xl pa-4">
                <v-card-title class="d-flex align-center gap-2">
                    <v-avatar color="error" variant="tonal" size="36">
                        <v-icon icon="mdi-delete-alert-outline" color="error"></v-icon>
                    </v-avatar>
                    <span class="text-h6 font-weight-bold ml-2">Delete Item</span>
                </v-card-title>
                <v-card-text class="pt-2 text-body-2 text-medium-emphasis">
                    Are you sure you want to delete <strong>{{ product?.name }}</strong>?
                    The item will be soft-deleted from your catalog. Historical records will remain intact.
                </v-card-text>
                <v-card-actions class="d-flex justify-end gap-2 pt-4">
                    <v-btn
                        variant="text"
                        class="text-none"
                        :disabled="actionLoading"
                        @click="showDeleteDialog = false"
                    >
                        Cancel
                    </v-btn>
                    <v-btn
                        color="error"
                        variant="flat"
                        class="text-none font-weight-bold"
                        :loading="actionLoading"
                        @click="handleDelete"
                    >
                        Delete
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-container>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useProductStore } from '@/stores/products';
import { usePermissionsStore } from '@/stores/permissions';
import { useSubscriptionStore } from '@/stores/subscription';
import ProductFormDialog from '@/components/products/ProductFormDialog.vue';
import type { Product } from '@/types';

const route = useRoute();
const router = useRouter();
const productStore = useProductStore();
const permissionsStore = usePermissionsStore();
const subscriptionStore = useSubscriptionStore();

const showEditDialog = ref(false);
const showDeleteDialog = ref(false);
const actionLoading = ref(false);
const successMessage = ref('');

const product = computed(() => productStore.currentProduct);

onMounted(async () => {
    const productId = route.params.product as string;
    if (productId) {
        await productStore.fetchProduct(productId);
    }
});

const formatDate = (dateStr: string): string => {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    return date.toLocaleDateString(undefined, {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    });
};

const onProductUpdated = (updated: Product) => {
    successMessage.value = `Item "${updated.name}" updated successfully.`;
};

const handleToggleStatus = async () => {
    if (!product.value) return;
    actionLoading.value = true;
    const nextStatus = product.value.status === 'active' ? 'inactive' : 'active';
    try {
        await productStore.updateStatus(product.value.id, nextStatus);
        successMessage.value = `Item marked as ${nextStatus}.`;
        await subscriptionStore.fetchPlan();
    } finally {
        actionLoading.value = false;
    }
};

const handleDelete = async () => {
    if (!product.value) return;
    actionLoading.value = true;
    try {
        await productStore.deleteProduct(product.value.id);
        router.push({ name: 'products' });
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
