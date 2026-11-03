<template>
    <div class="mx-auto" style="max-width: 1320px">
        <!-- Page Header -->
        <div class="d-flex flex-column flex-sm-row align-sm-center justify-space-between mb-6 gap-4">
            <div>
                <div class="d-flex align-center gap-2 mb-1">
                    <h1 class="text-h4 font-weight-bold tracking-tight text-slate-900">Products & Services</h1>
                    <v-chip
                        v-if="productStore.pagination"
                        size="small"
                        color="primary"
                        variant="tonal"
                        class="font-weight-bold ml-2"
                    >
                        {{ productStore.pagination.total }} items
                    </v-chip>
                </div>
                <p class="text-subtitle-1 text-medium-emphasis">
                    Maintain catalog items, pricing rates, and billable services for upcoming invoices
                </p>
            </div>

            <div class="d-flex align-center gap-2">
                <v-btn
                    v-if="permissionsStore.can('products.create')"
                    color="primary"
                    prepend-icon="mdi-plus"
                    class="text-none font-weight-bold rounded-lg"
                    size="large"
                    :disabled="isLimitReached"
                    @click="openCreateDialog"
                >
                    Add Product / Service
                </v-btn>
            </div>
        </div>

        <!-- Plan Limit Reached Banner -->
        <v-alert
            v-if="isLimitReached"
            type="warning"
            variant="tonal"
            density="comfortable"
            class="mb-6 rounded-xl border border-warning"
        >
            <div class="d-flex flex-column flex-sm-row align-sm-center justify-space-between gap-3">
                <div class="d-flex align-center gap-2">
                    <v-icon icon="mdi-alert-circle-outline" size="24"></v-icon>
                    <div>
                        <div class="font-weight-bold">Product catalog limit reached on your plan</div>
                        <div class="text-body-2 text-medium-emphasis">
                            You have reached your plan limit for products and services. Upgrade to Pro or Business for unlimited catalog items.
                        </div>
                    </div>
                </div>
                <v-btn
                    color="warning"
                    variant="flat"
                    size="small"
                    prepend-icon="mdi-arrow-up-bold-circle-outline"
                    class="text-none font-weight-bold rounded-lg text-dark"
                    :to="{ name: 'pricing' }"
                >
                    Upgrade Plan
                </v-btn>
            </div>
        </v-alert>

        <!-- Store Errors -->
        <v-alert
            v-if="productStore.error"
            type="error"
            variant="tonal"
            density="comfortable"
            class="mb-6 rounded-lg"
            closable
            @click:close="productStore.error = null"
        >
            {{ productStore.error }}
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

        <!-- Search & Filter Card -->
        <v-card class="rounded-xl border mb-6" elevation="0">
            <v-card-text class="pa-4">
                <v-row dense align="center">
                    <!-- Search Input -->
                    <v-col cols="12" md="5">
                        <v-text-field
                            v-model="searchInput"
                            placeholder="Search by name, SKU, or description..."
                            prepend-inner-icon="mdi-magnify"
                            variant="outlined"
                            density="compact"
                            hide-details
                            clearable
                            class="rounded-lg"
                            @update:model-value="onSearchInput"
                            @click:clear="onClearSearch"
                        ></v-text-field>
                    </v-col>

                    <!-- Type Filter Toggle -->
                    <v-col cols="12" sm="6" md="3">
                        <v-btn-toggle
                            v-model="productStore.filters.type"
                            mandatory
                            density="compact"
                            color="primary"
                            variant="outlined"
                            divided
                            class="rounded-lg w-100"
                            @update:model-value="onFilterChange"
                        >
                            <v-btn value="all" class="text-none flex-grow-1">All</v-btn>
                            <v-btn value="product" class="text-none flex-grow-1">Products</v-btn>
                            <v-btn value="service" class="text-none flex-grow-1">Services</v-btn>
                        </v-btn-toggle>
                    </v-col>

                    <!-- Status Filter Toggle -->
                    <v-col cols="12" sm="6" md="3">
                        <v-btn-toggle
                            v-model="productStore.filters.status"
                            mandatory
                            density="compact"
                            color="primary"
                            variant="outlined"
                            divided
                            class="rounded-lg w-100"
                            @update:model-value="onFilterChange"
                        >
                            <v-btn value="all" class="text-none flex-grow-1">All</v-btn>
                            <v-btn value="active" class="text-none flex-grow-1">Active</v-btn>
                            <v-btn value="inactive" class="text-none flex-grow-1">Inactive</v-btn>
                        </v-btn-toggle>
                    </v-col>

                    <!-- Refresh Button -->
                    <v-col cols="12" md="1" class="d-flex justify-end">
                        <v-btn
                            icon="mdi-refresh"
                            variant="tonal"
                            size="small"
                            :loading="productStore.loading"
                            title="Refresh products"
                            @click="refreshData"
                        ></v-btn>
                    </v-col>
                </v-row>
            </v-card-text>
        </v-card>

        <!-- Products Table Card -->
        <v-card class="rounded-xl border overflow-hidden" elevation="0">
            <v-table hover>
                <thead class="bg-grey-lighten-4">
                    <tr>
                        <th class="text-left font-weight-bold text-caption text-uppercase text-medium-emphasis">
                            Item / Service
                        </th>
                        <th class="text-left font-weight-bold text-caption text-uppercase text-medium-emphasis">
                            Type
                        </th>
                        <th class="text-left font-weight-bold text-caption text-uppercase text-medium-emphasis">
                            Price
                        </th>
                        <th class="text-left font-weight-bold text-caption text-uppercase text-medium-emphasis">
                            Tax
                        </th>
                        <th class="text-left font-weight-bold text-caption text-uppercase text-medium-emphasis">
                            Status
                        </th>
                        <th class="text-right font-weight-bold text-caption text-uppercase text-medium-emphasis pr-6">
                            Actions
                        </th>
                    </tr>
                </thead>

                <tbody>
                    <!-- Loading State -->
                    <tr v-if="productStore.loading && productStore.products.length === 0">
                        <td colspan="6" class="text-center py-10">
                            <v-progress-circular indeterminate color="primary" size="36"></v-progress-circular>
                            <div class="text-caption text-medium-emphasis mt-2">Loading catalog items...</div>
                        </td>
                    </tr>

                    <!-- Empty State -->
                    <tr v-else-if="productStore.products.length === 0">
                        <td colspan="6" class="text-center py-12">
                            <v-avatar color="primary" variant="tonal" size="64" class="mb-4">
                                <v-icon icon="mdi-package-variant-closed" size="32" color="primary"></v-icon>
                            </v-avatar>
                            <h3 class="text-h6 font-weight-bold mb-1">No products or services yet</h3>
                            <p class="text-body-2 text-medium-emphasis mb-4 mx-auto" style="max-width: 380px">
                                {{
                                    searchInput || productStore.filters.type !== 'all' || productStore.filters.status !== 'all'
                                        ? 'No items match your search or filter criteria. Try adjusting your query.'
                                        : 'Create your first product or billable service to populate invoices effortlessly.'
                                }}
                            </p>
                            <v-btn
                                v-if="!searchInput && productStore.filters.type === 'all' && permissionsStore.can('products.create')"
                                color="primary"
                                prepend-icon="mdi-plus"
                                class="text-none font-weight-bold rounded-lg"
                                :disabled="isLimitReached"
                                @click="openCreateDialog"
                            >
                                Add Your First Item
                            </v-btn>
                        </td>
                    </tr>

                    <!-- Product Rows -->
                    <tr
                        v-for="product in productStore.products"
                        v-else
                        :key="product.id"
                        class="cursor-pointer"
                        @click="viewProduct(product.id)"
                    >
                        <!-- Name & SKU -->
                        <td class="py-3">
                            <div class="d-flex align-center gap-3">
                                <v-avatar
                                    :color="product.type === 'service' ? 'deep-purple' : 'primary'"
                                    variant="tonal"
                                    size="38"
                                    class="rounded-lg mr-2"
                                >
                                    <v-icon
                                        :icon="product.type === 'service' ? 'mdi-briefcase-outline' : 'mdi-package-variant-closed'"
                                        size="20"
                                    ></v-icon>
                                </v-avatar>
                                <div>
                                    <div class="font-weight-bold text-body-2 text-high-emphasis d-flex align-center gap-2">
                                        {{ product.name }}
                                        <v-chip
                                            v-if="product.sku"
                                            size="x-small"
                                            variant="outlined"
                                            class="font-weight-medium text-caption"
                                        >
                                            {{ product.sku }}
                                        </v-chip>
                                    </div>
                                    <div v-if="product.description" class="text-caption text-medium-emphasis text-truncate" style="max-width: 320px;">
                                        {{ product.description }}
                                    </div>
                                </div>
                            </div>
                        </td>

                        <!-- Type Badge -->
                        <td class="py-3">
                            <v-chip
                                :color="product.type === 'service' ? 'deep-purple' : 'primary'"
                                size="small"
                                variant="tonal"
                                class="font-weight-bold text-capitalize"
                            >
                                <v-icon
                                    :icon="product.type === 'service' ? 'mdi-briefcase-outline' : 'mdi-package-variant-closed'"
                                    size="14"
                                    class="mr-1"
                                ></v-icon>
                                {{ product.type_label || product.type }}
                            </v-chip>
                        </td>

                        <!-- Price -->
                        <td class="py-3">
                            <div class="font-weight-bold text-body-2 text-high-emphasis">
                                {{ product.formatted_price || ('$' + (product.price / 100).toFixed(2)) }}
                                <span v-if="product.unit" class="text-caption text-medium-emphasis font-weight-regular">
                                    / {{ product.unit }}
                                </span>
                            </div>
                            <div class="text-caption text-disabled text-uppercase">
                                {{ product.currency }}
                            </div>
                        </td>

                        <!-- Tax Rate -->
                        <td class="py-3 text-body-2 text-medium-emphasis">
                            {{ product.tax_rate > 0 ? product.tax_rate + '%' : '0%' }}
                        </td>

                        <!-- Status Badge -->
                        <td class="py-3">
                            <v-chip
                                :color="product.status === 'active' ? 'success' : 'grey'"
                                size="small"
                                variant="tonal"
                                class="font-weight-bold text-capitalize"
                            >
                                {{ product.status }}
                            </v-chip>
                        </td>

                        <!-- Actions -->
                        <td class="py-3 text-right pr-4" @click.stop>
                            <v-menu location="bottom end">
                                <template #activator="{ props }">
                                    <v-btn
                                        icon="mdi-dots-vertical"
                                        variant="text"
                                        size="small"
                                        density="comfortable"
                                        v-bind="props"
                                    ></v-btn>
                                </template>

                                <v-list density="compact" class="rounded-xl pa-1" elevation="3">
                                    <v-list-item
                                        prepend-icon="mdi-eye-outline"
                                        title="View Details"
                                        rounded="lg"
                                        @click="viewProduct(product.id)"
                                    ></v-list-item>

                                    <v-list-item
                                        v-if="permissionsStore.can('products.update')"
                                        prepend-icon="mdi-pencil-outline"
                                        title="Edit Item"
                                        rounded="lg"
                                        @click="openEditDialog(product)"
                                    ></v-list-item>

                                    <v-list-item
                                        v-if="permissionsStore.can('products.update')"
                                        :prepend-icon="product.status === 'active' ? 'mdi-eye-off-outline' : 'mdi-eye-check-outline'"
                                        :title="product.status === 'active' ? 'Deactivate' : 'Activate'"
                                        rounded="lg"
                                        @click="toggleStatus(product)"
                                    ></v-list-item>

                                    <v-divider v-if="permissionsStore.can('products.delete')" class="my-1"></v-divider>

                                    <v-list-item
                                        v-if="permissionsStore.can('products.delete')"
                                        prepend-icon="mdi-delete-outline"
                                        title="Delete Item"
                                        rounded="lg"
                                        color="error"
                                        @click="confirmDelete(product)"
                                    ></v-list-item>
                                </v-list>
                            </v-menu>
                        </td>
                    </tr>
                </tbody>
            </v-table>

            <!-- Table Footer / Pagination -->
            <div
                v-if="productStore.pagination && productStore.pagination.last_page > 1"
                class="d-flex align-center justify-space-between px-6 py-4 bg-grey-lighten-4 border-t flex-wrap gap-2"
            >
                <div class="text-caption text-medium-emphasis">
                    Showing {{ (productStore.pagination.current_page - 1) * productStore.pagination.per_page + 1 }}
                    to {{ Math.min(productStore.pagination.current_page * productStore.pagination.per_page, productStore.pagination.total) }}
                    of {{ productStore.pagination.total }} items
                </div>

                <v-pagination
                    v-model="productStore.filters.page"
                    :length="productStore.pagination.last_page"
                    :total-visible="5"
                    density="comfortable"
                    rounded="circle"
                    @update:model-value="onPageChange"
                ></v-pagination>
            </div>
        </v-card>

        <!-- Product Form Dialog -->
        <ProductFormDialog
            v-model="showFormDialog"
            :product="selectedProduct"
            @saved="onProductSaved"
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
                    Are you sure you want to delete <strong>{{ productToDelete?.name }}</strong>?
                    The item will be removed from your catalog. Historical invoice records will remain safely preserved.
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
                        @click="executeDelete"
                    >
                        Delete
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useProductStore } from '@/stores/products';
import { usePermissionsStore } from '@/stores/permissions';
import { useSubscriptionStore } from '@/stores/subscription';
import ProductFormDialog from '@/components/products/ProductFormDialog.vue';
import type { Product } from '@/types';

const router = useRouter();
const productStore = useProductStore();
const permissionsStore = usePermissionsStore();
const subscriptionStore = useSubscriptionStore();

const searchInput = ref('');
const successMessage = ref('');
const showFormDialog = ref(false);
const selectedProduct = ref<Product | null>(null);

const showDeleteDialog = ref(false);
const productToDelete = ref<Product | null>(null);

const actionLoading = ref(false);
let searchDebounceTimer: any = null;

const isLimitReached = computed(() => {
    const feature = subscriptionStore.features['products'];
    if (!feature) {
        return false;
    }
    if (feature.is_unlimited) {
        return false;
    }
    return Boolean(feature.has_reached_limit);
});

onMounted(async () => {
    await Promise.all([
        productStore.fetchProducts(),
        subscriptionStore.fetchPlan(),
    ]);
});

const onSearchInput = (value: string) => {
    clearTimeout(searchDebounceTimer);
    searchDebounceTimer = setTimeout(async () => {
        productStore.filters.search = value.trim();
        productStore.filters.page = 1;
        await productStore.fetchProducts();
    }, 350);
};

const onClearSearch = async () => {
    searchInput.value = '';
    productStore.filters.search = '';
    productStore.filters.page = 1;
    await productStore.fetchProducts();
};

const onFilterChange = async () => {
    productStore.filters.page = 1;
    await productStore.fetchProducts();
};

const onPageChange = async (page: number) => {
    productStore.filters.page = page;
    await productStore.fetchProducts();
};

const refreshData = async () => {
    await Promise.all([
        productStore.fetchProducts(),
        subscriptionStore.fetchPlan(),
    ]);
};

const openCreateDialog = () => {
    selectedProduct.value = null;
    showFormDialog.value = true;
};

const openEditDialog = (product: Product) => {
    selectedProduct.value = product;
    showFormDialog.value = true;
};

const viewProduct = (id: number) => {
    router.push({ name: 'products.detail', params: { product: id } });
};

const onProductSaved = async (product: Product) => {
    successMessage.value = `Item "${product.name}" saved successfully.`;
    await subscriptionStore.fetchPlan();
};

const toggleStatus = async (product: Product) => {
    const nextStatus = product.status === 'active' ? 'inactive' : 'active';
    try {
        await productStore.updateStatus(product.id, nextStatus);
        successMessage.value = `"${product.name}" marked as ${nextStatus}.`;
        await subscriptionStore.fetchPlan();
    } catch {
        // Error is set in store
    }
};

const confirmDelete = (product: Product) => {
    productToDelete.value = product;
    showDeleteDialog.value = true;
};

const executeDelete = async () => {
    if (!productToDelete.value) return;
    actionLoading.value = true;
    try {
        await productStore.deleteProduct(productToDelete.value.id);
        successMessage.value = `"${productToDelete.value.name}" deleted from catalog.`;
        showDeleteDialog.value = false;
        productToDelete.value = null;
        await subscriptionStore.fetchPlan();
    } finally {
        actionLoading.value = false;
    }
};
</script>
