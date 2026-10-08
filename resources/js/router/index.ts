import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useCompanyStore } from '@/stores/company';
import { usePermissionsStore } from '@/stores/permissions';
import { useSubscriptionStore } from '@/stores/subscription';

const routes: RouteRecordRaw[] = [
    {
        path: '/',
        redirect: '/dashboard',
    },
    {
        path: '/login',
        name: 'login',
        component: () => import('@/pages/auth/LoginView.vue'),
        meta: { guestOnly: true, layout: 'auth' },
    },
    {
        path: '/register',
        name: 'register',
        component: () => import('@/pages/auth/RegisterView.vue'),
        meta: { guestOnly: true, layout: 'auth' },
    },
    {
        path: '/forgot-password',
        name: 'forgot-password',
        component: () => import('@/pages/auth/ForgotPasswordView.vue'),
        meta: { guestOnly: true, layout: 'auth' },
    },
    {
        path: '/reset-password',
        name: 'reset-password',
        component: () => import('@/pages/auth/ResetPasswordView.vue'),
        meta: { guestOnly: true, layout: 'auth' },
    },
    {
        path: '/verify-email',
        name: 'verify-email',
        component: () => import('@/pages/auth/VerifyEmailView.vue'),
        meta: { requiresAuth: true, layout: 'auth' },
    },
    {
        path: '/onboarding',
        name: 'onboarding',
        component: () => import('@/pages/company/CreateCompanyView.vue'),
        meta: { requiresAuth: true, layout: 'app' },
    },
    {
        path: '/companies',
        name: 'companies',
        component: () => import('@/pages/company/CompanyListView.vue'),
        meta: { requiresAuth: true, layout: 'app' },
    },
    {
        path: '/companies/create',
        name: 'companies.create',
        component: () => import('@/pages/company/CreateCompanyView.vue'),
        meta: { requiresAuth: true, layout: 'app' },
    },
    {
        path: '/companies/:company/settings',
        name: 'companies.settings',
        component: () => import('@/pages/company/CompanySettingsView.vue'),
        meta: { requiresAuth: true, layout: 'app' },
    },
    {
        path: '/profile',
        name: 'profile',
        component: () => import('@/pages/profile/ProfileView.vue'),
        meta: { requiresAuth: true, layout: 'app' },
    },
    {
        path: '/pricing',
        name: 'pricing',
        component: () => import('@/pages/billing/PricingView.vue'),
        meta: { layout: 'app' },
    },
    {
        path: '/dashboard',
        name: 'dashboard',
        component: () => import('@/pages/dashboard/DashboardView.vue'),
        meta: { requiresAuth: true, requiresCompany: true, layout: 'app' },
    },
    {
        path: '/customers',
        name: 'customers',
        component: () => import('@/pages/customers/CustomersView.vue'),
        meta: { requiresAuth: true, requiresCompany: true, layout: 'app' },
    },
    {
        path: '/customers/:customer',
        name: 'customers.detail',
        component: () => import('@/pages/customers/CustomerDetailView.vue'),
        meta: { requiresAuth: true, requiresCompany: true, layout: 'app' },
    },
    {
        path: '/products',
        name: 'products',
        component: () => import('@/pages/products/ProductsView.vue'),
        meta: { requiresAuth: true, requiresCompany: true, layout: 'app' },
    },
    {
        path: '/products/:product',
        name: 'products.detail',
        component: () => import('@/pages/products/ProductDetailView.vue'),
        meta: { requiresAuth: true, requiresCompany: true, layout: 'app' },
    },
    {
        path: '/:pathMatch(.*)*',
        redirect: '/dashboard',
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach(async (to, _from, next) => {
    const authStore = useAuthStore();
    const companyStore = useCompanyStore();

    // Rehydrate user session if token exists but user is null
    if (authStore.token && !authStore.user) {
        await authStore.fetchUser();
    }

    const isAuthenticated = authStore.isAuthenticated;

    // Guest-only guard
    if (to.meta.guestOnly && isAuthenticated) {
        return next({ name: 'dashboard' });
    }

    // Requires authentication guard
    if (to.meta.requiresAuth && !isAuthenticated) {
        return next({
            name: 'login',
            query: { redirect: to.fullPath },
        });
    }

    // Authenticated checks
    if (isAuthenticated) {
        // If user is unverified and not already on verify-email
        if (!authStore.isEmailVerified && to.name !== 'verify-email') {
            return next({ name: 'verify-email' });
        }

        // If route requires company context
        if (to.meta.requiresCompany) {
            if (companyStore.companies.length === 0) {
                await companyStore.fetchCompanies();
            }

            if (!companyStore.currentCompany || !companyStore.currentCompany.is_active) {
                if (companyStore.companies.length > 0) {
                    return next({ name: 'companies' });
                }
                return next({ name: 'companies.create' });
            }

            const permissionsStore = usePermissionsStore();
            const subscriptionStore = useSubscriptionStore();
            if (!permissionsStore.role && !permissionsStore.loading && companyStore.currentCompany) {
                await permissionsStore.fetchAuthorization(companyStore.currentCompany.id);
            }
            if (!subscriptionStore.currentPlan && !subscriptionStore.loading && companyStore.currentCompany) {
                await subscriptionStore.fetchPlan(companyStore.currentCompany.id);
            }
        }
    }

    next();
});

export default router;
