import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/authStore';

const routes = [
    // Auth Routes
    {
        path: '/login',
        name: 'login',
        component: () => import('../views/auth/LoginView.vue'),
        meta: { guest: true },
    },
    {
        path: '/register',
        name: 'register',
        component: () => import('../views/auth/RegisterView.vue'),
        meta: { guest: true },
    },
    {
        path: '/forgot-password',
        name: 'forgot-password',
        component: () => import('../views/auth/ForgotPasswordView.vue'),
        meta: { guest: true },
    },
    {
        path: '/reset-password/:token',
        name: 'reset-password',
        component: () => import('../views/auth/ResetPasswordView.vue'),
        meta: { guest: true },
    },
    
    // Main App Routes
    {
        path: '/',
        name: 'dashboard',
        component: () => import('../views/DashboardView.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/products',
        name: 'products',
        component: () => import('../views/ProductsView.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/orders',
        name: 'orders',
        component: () => import('../views/OrdersView.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/analysis',
        name: 'analysis',
        component: () => import('../views/AnalysisView.vue'),
        meta: { requiresAuth: true, permission: 'analytics.view' },
    },
    {
        path: '/chat',
        name: 'chat',
        component: () => import('../views/ChatView.vue'),
        meta: { requiresAuth: true, permission: 'chat.use' },
    },
    {
        path: '/integrations',
        name: 'integrations',
        component: () => import('../views/IntegrationsView.vue'),
        meta: { requiresAuth: true, permission: 'integrations.manage' },
    },
    {
        path: '/settings',
        name: 'settings',
        component: () => import('../views/SettingsView.vue'),
        meta: { requiresAuth: true },
    },
    
    // Admin Routes
    {
        path: '/admin',
        name: 'admin-dashboard',
        component: () => import('../views/admin/AdminDashboardView.vue'),
        meta: { requiresAuth: true, permission: 'admin.access' },
    },
    {
        path: '/admin/users',
        name: 'admin-users',
        component: () => import('../views/admin/UsersView.vue'),
        meta: { requiresAuth: true, permission: 'users.view' },
    },
    {
        path: '/admin/users/:id',
        name: 'admin-user-detail',
        component: () => import('../views/admin/UserDetailView.vue'),
        meta: { requiresAuth: true, permission: 'users.view' },
    },
    {
        path: '/admin/clients',
        name: 'admin-clients',
        component: () => import('../views/admin/ClientsView.vue'),
        meta: { requiresAuth: true, permission: 'admin.access' },
    },
    {
        path: '/admin/clients/:id',
        name: 'admin-client-detail',
        component: () => import('../views/admin/ClientDetailView.vue'),
        meta: { requiresAuth: true, permission: 'admin.access' },
    },
    {
        path: '/admin/settings',
        name: 'admin-settings',
        component: () => import('../views/admin/SettingsView.vue'),
        meta: { requiresAuth: true, permission: 'admin.access' },
    },
    
    // 404
    {
        path: '/:pathMatch(.*)*',
        name: 'not-found',
        component: () => import('../views/NotFoundView.vue'),
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
    
    // Redirect to login if not authenticated
    if (requiresAuth && !isAuthenticated) {
        return next({ name: 'login', query: { redirect: to.fullPath } });
    }
    
    // Redirect to dashboard if already authenticated
    if (isGuestOnly && isAuthenticated) {
        return next({ name: 'dashboard' });
    }
    
    // Check permission
    if (requiredPermission && !authStore.hasPermission(requiredPermission)) {
        return next({ name: 'dashboard' });
    }
    
    next();
});

export default router;

