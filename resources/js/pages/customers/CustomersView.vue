<template>
    <div class="mx-auto" style="max-width: 1320px">
        <!-- Page Header -->
        <div class="d-flex flex-column flex-sm-row align-sm-center justify-space-between mb-6 gap-4">
            <div>
                <div class="d-flex align-center gap-2 mb-1">
                    <h1 class="text-h4 font-weight-bold tracking-tight text-slate-900">Customers</h1>
                    <v-chip
                        v-if="customerStore.pagination"
                        size="small"
                        color="primary"
                        variant="tonal"
                        class="font-weight-bold ml-2"
                    >
                        {{ customerStore.pagination.total }} total
                    </v-chip>
                </div>
                <p class="text-subtitle-1 text-medium-emphasis">
                    Manage client profiles, billing information, and shipping addresses
                </p>
            </div>

            <div class="d-flex align-center gap-2">
                <v-btn
                    v-if="permissionsStore.can('customers.create')"
                    color="primary"
                    prepend-icon="mdi-plus"
                    class="text-none font-weight-bold rounded-lg"
                    size="large"
                    :disabled="isLimitReached"
                    @click="openCreateDialog"
                >
                    Add Customer
                </v-btn>
            </div>
        </div>

        <!-- Limit Reached / Warning Banner -->
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
                        <div class="font-weight-bold">Customer limit reached on Free plan</div>
                        <div class="text-body-2 text-medium-emphasis">
                            You have reached the maximum of 5 active customers allowed on your current plan. Upgrade to Pro for unlimited customers.
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

        <!-- General Store Errors -->
        <v-alert
            v-if="customerStore.error"
            type="error"
            variant="tonal"
            density="comfortable"
            class="mb-6 rounded-lg"
            closable
            @click:close="customerStore.error = null"
        >
            {{ customerStore.error }}
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
                    <!-- Search input -->
                    <v-col cols="12" md="6">
                        <v-text-field
                            v-model="searchInput"
                            placeholder="Search by customer name, company, email, or tax ID..."
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

                    <!-- Status Filter Tabs / Chips -->
                    <v-col cols="12" sm="8" md="4">
                        <v-btn-toggle
                            v-model="customerStore.filters.status"
                            mandatory
                            density="compact"
                            color="primary"
                            variant="outlined"
                            divided
                            class="rounded-lg"
                            @update:model-value="onFilterChange"
                        >
                            <v-btn value="active" class="text-none">
                                <v-icon icon="mdi-account-check-outline" size="16" class="mr-1"></v-icon>
                                Active
                            </v-btn>
                            <v-btn value="archived" class="text-none">
                                <v-icon icon="mdi-archive-outline" size="16" class="mr-1"></v-icon>
                                Archived
                            </v-btn>
                            <v-btn value="all" class="text-none">
                                All
                            </v-btn>
                        </v-btn-toggle>
                    </v-col>

                    <!-- Refresh -->
                    <v-col cols="12" sm="4" md="2" class="d-flex justify-end">
                        <v-btn
                            icon="mdi-refresh"
                            variant="tonal"
                            size="small"
                            :loading="customerStore.loading"
                            title="Refresh customers"
                            @click="refreshData"
                        ></v-btn>
                    </v-col>
                </v-row>
            </v-card-text>
        </v-card>

        <!-- Table Card -->
        <v-card class="rounded-xl border overflow-hidden" elevation="0">
            <v-table hover>
                <thead class="bg-grey-lighten-4">
                    <tr>
                        <th class="text-left font-weight-bold text-caption text-uppercase text-medium-emphasis">
                            Customer
                        </th>
                        <th class="text-left font-weight-bold text-caption text-uppercase text-medium-emphasis">
                            Contact
                        </th>
                        <th class="text-left font-weight-bold text-caption text-uppercase text-medium-emphasis">
                            Location
                        </th>
                        <th class="text-left font-weight-bold text-caption text-uppercase text-medium-emphasis">
                            Status
                        </th>
                        <th class="text-left font-weight-bold text-caption text-uppercase text-medium-emphasis">
                            Created
                        </th>
                        <th class="text-right font-weight-bold text-caption text-uppercase text-medium-emphasis pr-6">
                            Actions
                        </th>
                    </tr>
                </thead>

                <tbody>
                    <!-- Loading Row -->
                    <tr v-if="customerStore.loading && customerStore.customers.length === 0">
                        <td colspan="6" class="text-center py-10">
                            <v-progress-circular indeterminate color="primary" size="36"></v-progress-circular>
                            <div class="text-caption text-medium-emphasis mt-2">Loading customers...</div>
                        </td>
                    </tr>

                    <!-- Empty State -->
                    <tr v-else-if="customerStore.customers.length === 0">
                        <td colspan="6" class="text-center py-12">
                            <v-avatar color="primary" variant="tonal" size="64" class="mb-4">
                                <v-icon icon="mdi-account-group-outline" size="32" color="primary"></v-icon>
                            </v-avatar>
                            <h3 class="text-h6 font-weight-bold mb-1">No customers found</h3>
                            <p class="text-body-2 text-medium-emphasis mb-4 mx-auto" style="max-width: 360px">
                                {{
                                    searchInput || customerStore.filters.status !== 'active'
                                        ? 'No customers match your search or filter criteria. Try adjusting your search term.'
                                        : 'You have not added any customers to this workspace yet. Add your first customer to get started.'
                                }}
                            </p>
                            <v-btn
                                v-if="!searchInput && customerStore.filters.status === 'active' && permissionsStore.can('customers.create')"
                                color="primary"
                                prepend-icon="mdi-plus"
                                class="text-none font-weight-bold rounded-lg"
                                :disabled="isLimitReached"
                                @click="openCreateDialog"
                            >
                                Add Your First Customer
                            </v-btn>
                        </td>
                    </tr>

                    <!-- Customer Rows -->
                    <tr
                        v-for="customer in customerStore.customers"
                        v-else
                        :key="customer.id"
                        class="cursor-pointer"
                        @click="viewCustomer(customer.id)"
                    >
                        <!-- Name & Company -->
                        <td class="py-3">
                            <div class="d-flex align-center gap-3">
                                <v-avatar color="primary" variant="tonal" size="38" class="rounded-lg mr-2 font-weight-bold text-caption">
                                    {{ getInitials(customer.name) }}
                                </v-avatar>
                                <div>
                                    <div class="font-weight-bold text-body-2 text-high-emphasis">
                                        {{ customer.name }}
                                    </div>
                                    <div v-if="customer.company_name" class="text-caption text-medium-emphasis">
                                        {{ customer.company_name }}
                                    </div>
                                </div>
                            </div>
                        </td>

                        <!-- Contact -->
                        <td class="py-3">
                            <div v-if="customer.email" class="text-body-2 d-flex align-center gap-1 text-truncate" style="max-width: 220px">
                                <v-icon icon="mdi-email-outline" size="14" class="text-medium-emphasis mr-1"></v-icon>
                                <span>{{ customer.email }}</span>
                            </div>
                            <div v-if="customer.phone" class="text-caption text-medium-emphasis d-flex align-center gap-1">
                                <v-icon icon="mdi-phone-outline" size="14" class="mr-1"></v-icon>
                                <span>{{ customer.phone }}</span>
                            </div>
                            <span v-if="!customer.email && !customer.phone" class="text-caption text-disabled">—</span>
                        </td>

                        <!-- Location -->
                        <td class="py-3">
                            <div v-if="customer.billing_city || customer.billing_country" class="text-body-2">
                                {{ [customer.billing_city, customer.billing_country].filter(Boolean).join(', ') }}
                            </div>
                            <span v-else class="text-caption text-disabled">—</span>
                        </td>

                        <!-- Status -->
                        <td class="py-3">
                            <v-chip
                                :color="customer.status === 'active' ? 'success' : 'grey'"
                                size="small"
                                variant="tonal"
                                class="font-weight-bold text-capitalize"
                            >
                                {{ customer.status }}
                            </v-chip>
                        </td>

                        <!-- Created -->
                        <td class="py-3 text-caption text-medium-emphasis">
                            {{ formatDate(customer.created_at) }}
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
                                        @click="viewCustomer(customer.id)"
                                    ></v-list-item>

                                    <v-list-item
                                        v-if="permissionsStore.can('customers.update')"
                                        prepend-icon="mdi-pencil-outline"
                                        title="Edit Customer"
                                        rounded="lg"
                                        @click="openEditDialog(customer)"
                                    ></v-list-item>

                                    <v-divider v-if="permissionsStore.can('customers.delete') || permissionsStore.can('customers.update')" class="my-1"></v-divider>

                                    <v-list-item
                                        v-if="customer.status === 'active' && permissionsStore.can('customers.delete')"
                                        prepend-icon="mdi-archive-arrow-down-outline"
                                        title="Archive Customer"
                                        rounded="lg"
                                        color="warning"
                                        @click="confirmArchive(customer)"
                                    ></v-list-item>

                                    <v-list-item
                                        v-if="customer.status === 'archived' && permissionsStore.can('customers.update')"
                                        prepend-icon="mdi-restore"
                                        title="Restore Customer"
                                        rounded="lg"
                                        color="success"
                                        @click="confirmRestore(customer)"
                                    ></v-list-item>
                                </v-list>
                            </v-menu>
                        </td>
                    </tr>
                </tbody>
            </v-table>

            <!-- Table Footer / Pagination -->
            <div
                v-if="customerStore.pagination && customerStore.pagination.last_page > 1"
                class="d-flex align-center justify-space-between px-6 py-4 bg-grey-lighten-4 border-t flex-wrap gap-2"
            >
                <div class="text-caption text-medium-emphasis">
                    Showing {{ (customerStore.pagination.current_page - 1) * customerStore.pagination.per_page + 1 }}
                    to {{ Math.min(customerStore.pagination.current_page * customerStore.pagination.per_page, customerStore.pagination.total) }}
                    of {{ customerStore.pagination.total }} customers
                </div>

                <v-pagination
                    v-model="customerStore.filters.page"
                    :length="customerStore.pagination.last_page"
                    :total-visible="5"
                    density="comfortable"
                    rounded="circle"
                    @update:model-value="onPageChange"
                ></v-pagination>
            </div>
        </v-card>

        <!-- Customer Form Dialog -->
        <CustomerFormDialog
            v-model="showFormDialog"
            :customer="selectedCustomer"
            @saved="onCustomerSaved"
        />

        <!-- Archive Confirm Dialog -->
        <v-dialog v-model="showArchiveDialog" max-width="480">
            <v-card class="rounded-xl pa-4">
                <v-card-title class="d-flex align-center gap-2">
                    <v-avatar color="warning" variant="tonal" size="36">
                        <v-icon icon="mdi-archive-alert-outline" color="warning"></v-icon>
                    </v-avatar>
                    <span class="text-h6 font-weight-bold ml-2">Archive Customer</span>
                </v-card-title>
                <v-card-text class="pt-2 text-body-2 text-medium-emphasis">
                    Are you sure you want to archive <strong>{{ customerToArchive?.name }}</strong>?
                    Archived customers cannot receive new invoices but their historical records are preserved.
                </v-card-text>
                <v-card-actions class="d-flex justify-end gap-2 pt-4">
                    <v-btn
                        variant="text"
                        class="text-none"
                        :disabled="actionLoading"
                        @click="showArchiveDialog = false"
                    >
                        Cancel
                    </v-btn>
                    <v-btn
                        color="warning"
                        variant="flat"
                        class="text-none font-weight-bold"
                        :loading="actionLoading"
                        @click="executeArchive"
                    >
                        Archive
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Restore Confirm Dialog -->
        <v-dialog v-model="showRestoreDialog" max-width="480">
            <v-card class="rounded-xl pa-4">
                <v-card-title class="d-flex align-center gap-2">
                    <v-avatar color="success" variant="tonal" size="36">
                        <v-icon icon="mdi-restore" color="success"></v-icon>
                    </v-avatar>
                    <span class="text-h6 font-weight-bold ml-2">Restore Customer</span>
                </v-card-title>
                <v-card-text class="pt-2 text-body-2 text-medium-emphasis">
                    Restore <strong>{{ customerToRestore?.name }}</strong> back to active status?
                </v-card-text>
                <v-card-actions class="d-flex justify-end gap-2 pt-4">
                    <v-btn
                        variant="text"
                        class="text-none"
                        :disabled="actionLoading"
                        @click="showRestoreDialog = false"
                    >
                        Cancel
                    </v-btn>
                    <v-btn
                        color="success"
                        variant="flat"
                        class="text-none font-weight-bold"
                        :loading="actionLoading"
                        @click="executeRestore"
                    >
                        Restore
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useCustomerStore } from '@/stores/customers';
import { usePermissionsStore } from '@/stores/permissions';
import { useSubscriptionStore } from '@/stores/subscription';
import CustomerFormDialog from '@/components/customers/CustomerFormDialog.vue';
import type { Customer } from '@/types';

const router = useRouter();
const customerStore = useCustomerStore();
const permissionsStore = usePermissionsStore();
const subscriptionStore = useSubscriptionStore();

const searchInput = ref('');
const successMessage = ref('');
const showFormDialog = ref(false);
const selectedCustomer = ref<Customer | null>(null);

const showArchiveDialog = ref(false);
const customerToArchive = ref<Customer | null>(null);

const showRestoreDialog = ref(false);
const customerToRestore = ref<Customer | null>(null);

const actionLoading = ref(false);
let searchDebounceTimer: any = null;

const isLimitReached = computed(() => {
    const customerFeature = subscriptionStore.features['customers'];
    if (!customerFeature) {
        return false;
    }
    if (customerFeature.is_unlimited) {
        return false;
    }
    return Boolean(customerFeature.has_reached_limit);
});

onMounted(async () => {
    await Promise.all([
        customerStore.fetchCustomers(),
        subscriptionStore.fetchPlan(),
    ]);
});

const onSearchInput = (value: string) => {
    clearTimeout(searchDebounceTimer);
    searchDebounceTimer = setTimeout(async () => {
        customerStore.filters.search = value.trim();
        customerStore.filters.page = 1;
        await customerStore.fetchCustomers();
    }, 350);
};

const onClearSearch = async () => {
    searchInput.value = '';
    customerStore.filters.search = '';
    customerStore.filters.page = 1;
    await customerStore.fetchCustomers();
};

const onFilterChange = async () => {
    customerStore.filters.page = 1;
    await customerStore.fetchCustomers();
};

const onPageChange = async (page: number) => {
    customerStore.filters.page = page;
    await customerStore.fetchCustomers();
};

const refreshData = async () => {
    await Promise.all([
        customerStore.fetchCustomers(),
        subscriptionStore.fetchPlan(),
    ]);
};

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
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
};

const openCreateDialog = () => {
    selectedCustomer.value = null;
    showFormDialog.value = true;
};

const openEditDialog = (customer: Customer) => {
    selectedCustomer.value = customer;
    showFormDialog.value = true;
};

const viewCustomer = (id: number) => {
    router.push({ name: 'customers.detail', params: { customer: id } });
};

const onCustomerSaved = async (customer: Customer) => {
    successMessage.value = `Customer "${customer.name}" saved successfully.`;
    await subscriptionStore.fetchPlan();
};

const confirmArchive = (customer: Customer) => {
    customerToArchive.value = customer;
    showArchiveDialog.value = true;
};

const executeArchive = async () => {
    if (!customerToArchive.value) return;
    actionLoading.value = true;
    try {
        await customerStore.archiveCustomer(customerToArchive.value.id);
        successMessage.value = `Customer "${customerToArchive.value.name}" has been archived.`;
        showArchiveDialog.value = false;
        customerToArchive.value = null;
        await subscriptionStore.fetchPlan();
    } catch {
        // Error is set in store
    } finally {
        actionLoading.value = false;
    }
};

const confirmRestore = (customer: Customer) => {
    customerToRestore.value = customer;
    showRestoreDialog.value = true;
};

const executeRestore = async () => {
    if (!customerToRestore.value) return;
    actionLoading.value = true;
    try {
        await customerStore.restoreCustomer(customerToRestore.value.id);
        successMessage.value = `Customer "${customerToRestore.value.name}" has been restored.`;
        showRestoreDialog.value = false;
        customerToRestore.value = null;
        await subscriptionStore.fetchPlan();
    } catch {
        // Error is set in store
    } finally {
        actionLoading.value = false;
    }
};
</script>
