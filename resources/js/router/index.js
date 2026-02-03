import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/authStore';

const routes = [
    // Auth Routes - Guest only routes with prefetch for fast login flow
    {
        path: '/login',
        name: 'login',
        component: () => import(
            /* webpackChunkName: "auth-login" */
            /* webpackPrefetch: true */
            '../views/auth/LoginView.vue'
        ),
        meta: { guest: true },
    },
    {
        path: '/register',
        name: 'register',
        component: () => import(
            /* webpackChunkName: "auth-register" */
            '../views/auth/RegisterView.vue'
        ),
        meta: { guest: true },
    },
    {
        path: '/forgot-password',
        name: 'forgot-password',
        component: () => import(
            /* webpackChunkName: "auth-forgot-password" */
            '../views/auth/ForgotPasswordView.vue'
        ),
        meta: { guest: true },
    },
    {
        path: '/reset-password/:token',
        name: 'reset-password',
        component: () => import(
            /* webpackChunkName: "auth-reset-password" */
            '../views/auth/ResetPasswordView.vue'
        ),
        meta: { guest: true },
    },
    {
        path: '/change-password',
        name: 'change-password',
        component: () => import(
            /* webpackChunkName: "auth-change-password" */
            '../views/auth/ChangePasswordView.vue'
        ),
        meta: { requiresAuth: true, allowMustChangePassword: true },
    },

    // Main App Routes - High priority routes with prefetch
    {
        path: '/',
        name: 'dashboard',
        component: () => import(
            /* webpackChunkName: "dashboard" */
            /* webpackPrefetch: true */
            '../views/DashboardView.vue'
        ),
        meta: { requiresAuth: true, permission: 'dashboard.view' },
    },
    {
        path: '/products',
        name: 'products',
        component: () => import(
            /* webpackChunkName: "products" */
            /* webpackPrefetch: true */
            '../views/ProductsView.vue'
        ),
        meta: { requiresAuth: true, permission: 'products.view' },
    },
    {
        path: '/orders',
        name: 'orders',
        component: () => import(
            /* webpackChunkName: "orders" */
            /* webpackPrefetch: true */
            '../views/OrdersView.vue'
        ),
        meta: { requiresAuth: true, permission: 'orders.view' },
    },
    {
        path: '/marketing/discounts',
        name: 'discounts',
        component: () => import(
            /* webpackChunkName: "discounts" */
            '../views/DiscountsView.vue'
        ),
        meta: { requiresAuth: true, permission: 'marketing.access' },
    },
    {
        path: '/notifications',
        name: 'notifications',
        component: () => import(
            /* webpackChunkName: "notifications" */
            '../views/NotificationsView.vue'
        ),
        meta: { requiresAuth: true },
    },
    {
        path: '/analysis',
        name: 'analysis',
        component: () => import(
            /* webpackChunkName: "analysis" */
            /* webpackPrefetch: true */
            '../views/AnalysisView.vue'
        ),
        meta: { requiresAuth: true, permission: 'analysis.view' },
    },
    {
        path: '/suggestions',
        name: 'suggestions',
        component: () => import(
            /* webpackChunkName: "suggestions" */
            '../views/SuggestionsView.vue'
        ),
        meta: { requiresAuth: true, permission: 'analysis.view' },
    },
    {
        path: '/suggestions/:id/workflow',
        name: 'suggestion-workflow',
        component: () => import(
            /* webpackChunkName: "suggestion-workflow" */
            '../views/SuggestionWorkflowView.vue'
        ),
        meta: { requiresAuth: true, permission: 'analysis.view' },
    },
    {
        path: '/impact',
        name: 'impact-dashboard',
        component: () => import(
            /* webpackChunkName: "impact-dashboard" */
            '../views/ImpactDashboardView.vue'
        ),
        meta: { requiresAuth: true },
    },
    {
        path: '/chat',
        name: 'chat',
        component: () => import(
            /* webpackChunkName: "chat" */
            /* webpackPrefetch: true */
            '../views/ChatView.vue'
        ),
        meta: { requiresAuth: true, permission: 'chat.use' },
    },
    {
        path: '/integrations',
        name: 'integrations',
        component: () => import(
            /* webpackChunkName: "integrations" */
            '../views/IntegrationsView.vue'
        ),
        meta: { requiresAuth: true, permission: 'integrations.manage' },
    },
    {
        path: '/stores/:id/config',
        name: 'store-config',
        component: () => import(
            /* webpackChunkName: "store-config" */
            '../views/StoreConfigView.vue'
        ),
        meta: { requiresAuth: true, permission: 'integrations.manage' },
    },
    {
        path: '/settings',
        name: 'settings',
        component: () => import(
            /* webpackChunkName: "settings" */
            '../views/SettingsView.vue'
        ),
        meta: { requiresAuth: true, permission: 'settings.view' },
    },
    {
        path: '/settings/users',
        name: 'users-management',
        component: () => import(
            /* webpackChunkName: "users-management" */
            '../views/UsersManagementView.vue'
        ),
        meta: { requiresAuth: true, permission: 'users.view' },
        beforeEnter: (to, from, next) => {
            const authStore = useAuthStore();
            // Admins não devem acessar essa área (eles usam /admin/clients)
            if (authStore.isAdmin) {
                next({ name: 'admin-clients' });
            } else {
                next();
            }
        },
    },

    // Admin Routes - Lower priority, no preload for better initial performance
    {
        path: '/admin',
        name: 'admin-dashboard',
        component: () => import(
            /* webpackChunkName: "admin" */
            /* webpackPreload: false */
            '../views/admin/AdminDashboardView.vue'
        ),
        meta: { requiresAuth: true, permission: 'admin.access' },
    },
    {
        path: '/admin/users',
        name: 'admin-users',
        component: () => import(
            /* webpackChunkName: "admin" */
            /* webpackPreload: false */
            '../views/admin/UsersView.vue'
        ),
        meta: { requiresAuth: true, permission: 'users.view' },
    },
    {
        path: '/admin/users/:id',
        name: 'admin-user-detail',
        component: () => import(
            /* webpackChunkName: "admin" */
            /* webpackPreload: false */
            '../views/admin/UserDetailView.vue'
        ),
        meta: { requiresAuth: true, permission: 'users.view' },
    },
    {
        path: '/admin/clients',
        name: 'admin-clients',
        component: () => import(
            /* webpackChunkName: "admin" */
            /* webpackPreload: false */
            '../views/admin/ClientsView.vue'
        ),
        meta: { requiresAuth: true, permission: 'admin.access' },
    },
    {
        path: '/admin/clients/:id',
        name: 'admin-client-detail',
        component: () => import(
            /* webpackChunkName: "admin" */
            /* webpackPreload: false */
            '../views/admin/ClientDetailView.vue'
        ),
        meta: { requiresAuth: true, permission: 'admin.access' },
    },
    {
        path: '/admin/settings',
        name: 'admin-settings',
        component: () => import(
            /* webpackChunkName: "admin" */
            /* webpackPreload: false */
            '../views/admin/SettingsView.vue'
        ),
        meta: { requiresAuth: true, permission: 'admin.access' },
    },
    {
        path: '/admin/plans',
        name: 'admin-plans',
        component: () => import(
            /* webpackChunkName: "admin" */
            /* webpackPreload: false */
            '../views/admin/PlansView.vue'
        ),
        meta: { requiresAuth: true, permission: 'admin.access' },
    },
    {
        path: '/admin/integrations',
        name: 'admin-integrations',
        component: () => import(
            /* webpackChunkName: "admin" */
            /* webpackPreload: false */
            '../views/admin/IntegrationsView.vue'
        ),
        meta: { requiresAuth: true, permission: 'admin.access' },
    },
    {
        path: '/admin/analyses',
        name: 'admin-analyses',
        component: () => import(
            /* webpackChunkName: "admin" */
            /* webpackPreload: false */
            '../views/admin/AnalysesView.vue'
        ),
        meta: { requiresAuth: true, permission: 'admin.access' },
    },

    // 404
    {
        path: '/:pathMatch(.*)*',
        name: 'not-found',
        component: () => import(
            /* webpackChunkName: "not-found" */
            '../views/NotFoundView.vue'
        ),
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach(async (to, from, next) => {
    const authStore = useAuthStore();

    // Check if user data is loaded
    if (!authStore.isInitialized) {
        await authStore.initialize();
    }

    const isAuthenticated = authStore.isAuthenticated;
    const requiresAuth = to.meta.requiresAuth;
    const isGuestOnly = to.meta.guest;
    const requiredPermission = to.meta.permission;
    const allowMustChangePassword = to.meta.allowMustChangePassword;

    // Redirect to login if not authenticated
    if (requiresAuth && !isAuthenticated) {
        return next({ name: 'login', query: { redirect: to.fullPath } });
    }

    // Redirect to dashboard if already authenticated
    if (isGuestOnly && isAuthenticated) {
        return next({ name: 'dashboard' });
    }

    // SECURITY: Force password change if required
    // Only allow access to the change-password page when must_change_password is true
    if (isAuthenticated && authStore.mustChangePassword && !allowMustChangePassword) {
        return next({ name: 'change-password', query: { redirect: to.fullPath } });
    }

    // Check permission
    if (requiredPermission && !authStore.hasPermission(requiredPermission)) {
        // SECURITY: This is client-side authorization for UI/UX only.
        // Backend MUST validate permissions on every API request.
        return next({ name: 'dashboard' });
    }

    next();
});

export default router;

