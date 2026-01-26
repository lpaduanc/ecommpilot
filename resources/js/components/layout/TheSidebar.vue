<script setup>
import { ref, computed, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/authStore';
import { useSidebarStore } from '../../stores/sidebarStore';
import { useSystemNotificationStore } from '../../stores/systemNotificationStore';
import {
    ChartBarIcon,
    CubeIcon,
    CubeTransparentIcon,
    CurrencyDollarIcon,
    SparklesIcon,
    ChatBubbleLeftRightIcon,
    LinkIcon,
    Cog6ToothIcon,
    UsersIcon,
    BuildingOfficeIcon,
    ArrowLeftOnRectangleIcon,
    ChevronDoubleLeftIcon,
    ChevronDoubleRightIcon,
    WrenchScrewdriverIcon,
    XMarkIcon,
    MegaphoneIcon,
    ChevronDownIcon,
    ChevronRightIcon,
    BellIcon,
    CreditCardIcon,
    LockClosedIcon,
    ClipboardDocumentListIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    isMobileOpen: { type: Boolean, default: false },
});

const emit = defineEmits(['close']);

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const sidebarStore = useSidebarStore();
const notificationStore = useSystemNotificationStore();

// Submenu states
const isAIAssistantsOpen = ref(true); // Aberto por padrão
const isMarketingOpen = ref(false);

// Close mobile sidebar on route change
watch(() => route.name, () => {
    emit('close');
});

const menuItems = computed(() => [
    {
        name: 'Assistentes de IA',
        label: 'Assistentes de IA',
        icon: SparklesIcon,
        permission: 'analysis.view',
        highlight: true,
        submenuKey: 'ai',
        submenu: [
            {
                name: 'Análises IA',
                label: 'Análises IA',
                route: 'analysis',
                permission: 'analysis.view',
                locked: !authStore.canAccessAiAnalysis,
            },
            {
                name: 'Assistente IA',
                label: 'Assistente IA',
                route: 'chat',
                permission: 'chat.use',
                locked: !authStore.canAccessAiChat,
            },
        ],
        separatorAfter: true,
    },
    {
        name: 'Dashboard',
        label: 'Dashboard',
        icon: ChartBarIcon,
        route: 'dashboard',
        permission: 'dashboard.view',
        locked: !authStore.canAccessCustomDashboards,
    },
    {
        name: 'Sugestões',
        label: 'Sugestões',
        icon: ClipboardDocumentListIcon,
        route: 'suggestions',
        permission: 'analysis.view',
    },
    {
        name: 'Produtos',
        label: 'Produtos',
        icon: CubeIcon,
        route: 'products',
        permission: 'products.view',
    },
    {
        name: 'Pedidos',
        label: 'Pedidos',
        icon: CurrencyDollarIcon,
        route: 'orders',
        permission: 'orders.view',
    },
    {
        name: 'Marketing',
        label: 'Marketing',
        icon: MegaphoneIcon,
        permission: 'marketing.access',
        submenuKey: 'marketing',
        submenu: [
            {
                name: 'Descontos',
                label: 'Descontos',
                route: 'discounts',
                permission: 'marketing.access',
            },
        ],
    },
    {
        name: 'Notificações',
        label: 'Notificações',
        icon: BellIcon,
        route: 'notifications',
        badge: notificationStore.unreadCount,
    },
    {
        name: 'Integrações',
        label: 'Integrações',
        icon: LinkIcon,
        route: 'integrations',
        permission: 'integrations.manage',
        locked: !authStore.canAccessExternalIntegrations,
    },
    {
        name: 'Configurações',
        label: 'Configurações',
        icon: Cog6ToothIcon,
        route: 'settings',
        permission: 'settings.view',
    },
]);

const adminMenuItems = computed(() => [
    {
        name: 'Painel Admin',
        label: 'Painel Admin',
        icon: BuildingOfficeIcon,
        route: 'admin-dashboard',
        permission: 'admin.access',
    },
    {
        name: 'Clientes',
        label: 'Clientes',
        icon: UsersIcon,
        route: 'admin-clients',
        permission: 'admin.access',
    },
    {
        name: 'Planos',
        label: 'Planos',
        icon: CreditCardIcon,
        route: 'admin-plans',
        permission: 'admin.access',
    },
    {
        name: 'Config. Sistema',
        label: 'Config. Sistema',
        icon: WrenchScrewdriverIcon,
        route: 'admin-settings',
        permission: 'admin.access',
    },
    {
        name: 'Integrações',
        label: 'Integrações',
        icon: CubeTransparentIcon,
        route: 'admin-integrations',
        permission: 'admin.access',
    },
    {
        name: 'Análises Geradas',
        label: 'Análises Geradas',
        icon: ChartBarIcon,
        route: 'admin-analyses',
        permission: 'admin.access',
    },
]);

const visibleMenuItems = computed(() =>
    menuItems.value.filter(item => 
        !item.permission || authStore.hasPermission(item.permission)
    )
);

const visibleAdminItems = computed(() =>
    adminMenuItems.value.filter(item => 
        !item.permission || authStore.hasPermission(item.permission)
    )
);

const showAdminSection = computed(() => visibleAdminItems.value.length > 0);

function isActive(routeName) {
    return route.name === routeName;
}

function isSubmenuActive(submenu) {
    return submenu.some(item => route.name === item.route);
}

function toggleSubmenu(submenuKey) {
    if (submenuKey === 'ai') {
        isAIAssistantsOpen.value = !isAIAssistantsOpen.value;
    } else if (submenuKey === 'marketing') {
        isMarketingOpen.value = !isMarketingOpen.value;
    }
}

function isSubmenuOpen(submenuKey) {
    if (submenuKey === 'ai') return isAIAssistantsOpen.value;
    if (submenuKey === 'marketing') return isMarketingOpen.value;
    return false;
}

function navigateTo(routeName) {
    router.push({ name: routeName });
    emit('close');
}

async function handleLogout() {
    await authStore.logoutFromServer();
    router.push({ name: 'login' });
}
</script>

<template>
    <!-- Mobile Sidebar -->
    <Transition name="slide">
        <aside
            v-if="isMobileOpen"
            class="fixed left-0 top-0 h-full w-72 bg-white dark:bg-gray-800 border-r border-gray-100 dark:border-gray-700 shadow-xl z-50 lg:hidden flex flex-col"
        >
            <!-- Mobile Close Button -->
            <div class="absolute top-4 right-4">
                <button
                    @click="emit('close')"
                    class="p-2 rounded-lg bg-gray-100 dark:bg-gray-800 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600"
                >
                    <XMarkIcon class="w-5 h-5 text-gray-600 dark:text-gray-400 dark:text-gray-300" />
                </button>
            </div>

            <!-- Logo -->
            <div class="h-20 flex items-center px-6 border-b border-gray-100 dark:border-gray-700">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary-500 to-secondary-600 flex items-center justify-center shadow-lg shadow-primary-500/30">
                        <SparklesIcon class="w-6 h-6 text-white" />
                    </div>
                    <span class="font-display font-bold text-xl text-gray-900 dark:text-gray-100 dark:text-white">Ecommpilot</span>
                </div>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 overflow-y-auto scrollbar-thin p-4 space-y-2">
                <template v-for="item in visibleMenuItems" :key="item.name">
                    <!-- Item with submenu -->
                    <div v-if="item.submenu" :class="{ 'mb-4': item.separatorAfter }">
                        <button
                            @click="toggleSubmenu(item.submenuKey)"
                            :class="[
                                'w-full flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all duration-200',
                                item.highlight
                                    ? 'bg-gradient-to-r from-primary-500/10 to-secondary-500/10 dark:from-primary-500/20 dark:to-secondary-500/20 border border-primary-200 dark:border-primary-700 text-primary-700 dark:text-primary-300 hover:from-primary-500/20 hover:to-secondary-500/20 dark:hover:from-primary-500/30 dark:hover:to-secondary-500/30'
                                    : isSubmenuActive(item.submenu)
                                        ? 'text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-900/20'
                                        : 'text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-white'
                            ]"
                        >
                            <component
                                :is="item.icon"
                                :class="[
                                    'w-5 h-5 flex-shrink-0',
                                    item.highlight ? 'text-primary-500' : ''
                                ]"
                            />
                            <span class="truncate">{{ item.label }}</span>
                            <span
                                v-if="item.highlight"
                                class="ml-auto mr-2 text-xs px-2 py-0.5 rounded-full bg-gradient-to-r from-primary-500 to-secondary-500 text-white font-semibold shadow-sm"
                            >
                                PRO
                            </span>
                            <ChevronDownIcon
                                :class="[
                                    'w-4 h-4 transition-transform duration-200',
                                    isSubmenuOpen(item.submenuKey) ? 'rotate-180' : '',
                                    item.highlight ? '' : 'ml-auto'
                                ]"
                            />
                        </button>

                        <!-- Submenu items -->
                        <Transition name="submenu">
                            <div v-if="isSubmenuOpen(item.submenuKey)" class="ml-4 mt-1 space-y-1">
                                <button
                                    v-for="subitem in item.submenu"
                                    :key="subitem.route"
                                    @click="navigateTo(subitem.route)"
                                    :class="[
                                        'w-full flex items-center gap-3 px-4 py-2 rounded-lg font-medium text-sm',
                                        subitem.locked
                                            ? 'text-gray-400 dark:text-gray-500 hover:bg-gray-50 dark:hover:bg-gray-700'
                                            : isActive(subitem.route)
                                                ? 'bg-gradient-to-r from-primary-500 to-primary-600 text-white shadow-lg shadow-primary-500/30'
                                                : 'text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-white'
                                    ]"
                                >
                                    <SparklesIcon v-if="item.highlight && !subitem.locked" class="w-4 h-4 text-primary-400" />
                                    <LockClosedIcon v-if="subitem.locked" class="w-4 h-4 text-gray-400 dark:text-gray-500" />
                                    <span class="truncate">{{ subitem.label }}</span>
                                    <span v-if="subitem.locked" class="ml-auto text-xs px-1.5 py-0.5 rounded bg-gray-200 dark:bg-gray-700 text-gray-500 dark:text-gray-400">
                                        PRO
                                    </span>
                                </button>
                            </div>
                        </Transition>

                        <!-- Separator after highlighted menu -->
                        <div v-if="item.separatorAfter" class="mt-4 mx-2 border-t border-gray-200 dark:border-gray-700"></div>
                    </div>

                    <!-- Regular item -->
                    <button
                        v-else
                        @click="navigateTo(item.route)"
                        :class="[
                            'w-full flex items-center gap-3 px-4 py-3 rounded-xl font-medium',
                            item.locked
                                ? 'text-gray-400 dark:text-gray-500 hover:bg-gray-50 dark:hover:bg-gray-700'
                                : isActive(item.route)
                                    ? 'bg-gradient-to-r from-primary-500 to-primary-600 text-white shadow-lg shadow-primary-500/30'
                                    : 'text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-white'
                        ]"
                    >
                        <component v-if="!item.locked" :is="item.icon" class="w-5 h-5 flex-shrink-0" />
                        <LockClosedIcon v-else class="w-5 h-5 flex-shrink-0 text-gray-400 dark:text-gray-500" />
                        <span class="truncate">{{ item.label }}</span>
                        <span
                            v-if="item.locked"
                            class="ml-auto text-xs px-1.5 py-0.5 rounded bg-gray-200 dark:bg-gray-700 text-gray-500 dark:text-gray-400"
                        >
                            PRO
                        </span>
                        <span
                            v-else-if="item.badge && item.badge > 0"
                            class="ml-auto px-2 py-0.5 text-xs font-semibold rounded-full bg-danger-500 text-white"
                        >
                            {{ item.badge > 99 ? '99+' : item.badge }}
                        </span>
                    </button>
                </template>

                <!-- Admin Section -->
                <template v-if="showAdminSection">
                    <div class="pt-6 pb-2">
                        <span class="px-4 text-xs font-semibold text-gray-400 dark:text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Administração
                        </span>
                    </div>

                    <template v-for="item in visibleAdminItems" :key="item.route">
                        <button
                            @click="navigateTo(item.route)"
                            :class="[
                                'w-full flex items-center gap-3 px-4 py-3 rounded-xl font-medium',
                                isActive(item.route)
                                    ? 'bg-gradient-to-r from-primary-500 to-primary-600 text-white shadow-lg shadow-primary-500/30'
                                    : 'text-gray-600 dark:text-gray-400 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 dark:bg-gray-900 dark:hover:bg-gray-700 hover:text-gray-900 dark:text-gray-100 dark:hover:text-white'
                            ]"
                        >
                            <component :is="item.icon" class="w-5 h-5 flex-shrink-0" />
                            <span class="truncate">{{ item.label }}</span>
                        </button>
                    </template>
                </template>
            </nav>

            <!-- User Section -->
            <div class="p-4 border-t border-gray-100 dark:border-gray-700">
                <button
                    @click="handleLogout"
                    class="w-full flex items-center gap-3 px-4 py-3 rounded-xl font-medium text-gray-600 dark:text-gray-400 dark:text-gray-300 hover:bg-danger-50 dark:hover:bg-danger-900/20 hover:text-danger-600 dark:hover:text-danger-400"
                >
                    <ArrowLeftOnRectangleIcon class="w-5 h-5 flex-shrink-0" />
                    <span>Sair</span>
                </button>
            </div>
        </aside>
    </Transition>

    <!-- Desktop Sidebar -->
    <aside
        :class="[
            'hidden lg:flex lg:flex-col fixed left-0 top-0 h-full bg-white dark:bg-gray-800 border-r border-gray-100 dark:border-gray-700 shadow-sm z-40 transition-[width] duration-300',
            sidebarStore.isCollapsed ? 'w-20' : 'w-72'
        ]"
    >
        <!-- Logo -->
        <div class="h-20 flex items-center justify-between px-6 border-b border-gray-100 dark:border-gray-700">
            <div v-if="!sidebarStore.isCollapsed" class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary-500 to-secondary-600 flex items-center justify-center shadow-lg shadow-primary-500/30">
                    <SparklesIcon class="w-6 h-6 text-white" />
                </div>
                <span class="font-display font-bold text-xl text-gray-900 dark:text-gray-100 dark:text-white">Ecommpilot</span>
            </div>
            <div v-else class="w-full flex justify-center">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary-500 to-secondary-600 flex items-center justify-center shadow-lg shadow-primary-500/30">
                    <SparklesIcon class="w-6 h-6 text-white" />
                </div>
            </div>
        </div>

        <!-- Collapse Button -->
        <button
            @click="sidebarStore.toggle"
            class="absolute -right-3 top-24 w-6 h-6 rounded-full bg-white dark:bg-gray-800 dark:bg-gray-700 border border-gray-200 dark:border-gray-700 dark:border-gray-600 shadow-sm flex items-center justify-center hover:bg-gray-50 dark:hover:bg-gray-700 dark:bg-gray-900 dark:hover:bg-gray-600"
        >
            <ChevronDoubleLeftIcon v-if="!sidebarStore.isCollapsed" class="w-3 h-3 text-gray-500 dark:text-gray-400" />
            <ChevronDoubleRightIcon v-else class="w-3 h-3 text-gray-500 dark:text-gray-400" />
        </button>

        <!-- Navigation -->
        <nav class="flex-1 overflow-y-auto scrollbar-thin p-4 space-y-2">
            <!-- Main Menu -->
            <template v-for="item in visibleMenuItems" :key="item.name">
                <!-- Item with submenu -->
                <div v-if="item.submenu" :class="{ 'mb-4': item.separatorAfter }">
                    <button
                        @click="toggleSubmenu(item.submenuKey)"
                        :class="[
                            'w-full flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all duration-200',
                            item.highlight
                                ? 'bg-gradient-to-r from-primary-500/10 to-secondary-500/10 dark:from-primary-500/20 dark:to-secondary-500/20 border border-primary-200 dark:border-primary-700 text-primary-700 dark:text-primary-300 hover:from-primary-500/20 hover:to-secondary-500/20 dark:hover:from-primary-500/30 dark:hover:to-secondary-500/30'
                                : isSubmenuActive(item.submenu)
                                    ? 'text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-900/20'
                                    : 'text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-white',
                            sidebarStore.isCollapsed ? 'justify-center' : ''
                        ]"
                        :title="sidebarStore.isCollapsed ? item.label : ''"
                    >
                        <component
                            :is="item.icon"
                            :class="[
                                'w-5 h-5 flex-shrink-0',
                                item.highlight ? 'text-primary-500' : ''
                            ]"
                        />
                        <span v-if="!sidebarStore.isCollapsed" class="truncate">{{ item.label }}</span>
                        <span
                            v-if="item.highlight && !sidebarStore.isCollapsed"
                            class="ml-auto mr-2 text-xs px-2 py-0.5 rounded-full bg-gradient-to-r from-primary-500 to-secondary-500 text-white font-semibold shadow-sm"
                        >
                            PRO
                        </span>
                        <ChevronDownIcon
                            v-if="!sidebarStore.isCollapsed"
                            :class="[
                                'w-4 h-4 transition-transform duration-200',
                                isSubmenuOpen(item.submenuKey) ? 'rotate-180' : '',
                                item.highlight ? '' : 'ml-auto'
                            ]"
                        />
                    </button>

                    <!-- Submenu items -->
                    <Transition name="submenu">
                        <div v-if="isSubmenuOpen(item.submenuKey) && !sidebarStore.isCollapsed" class="ml-4 mt-1 space-y-1">
                            <button
                                v-for="subitem in item.submenu"
                                :key="subitem.route"
                                @click="navigateTo(subitem.route)"
                                :class="[
                                    'w-full flex items-center gap-3 px-4 py-2 rounded-lg font-medium text-sm',
                                    subitem.locked
                                        ? 'text-gray-400 dark:text-gray-500 hover:bg-gray-50 dark:hover:bg-gray-700'
                                        : isActive(subitem.route)
                                            ? 'bg-gradient-to-r from-primary-500 to-primary-600 text-white shadow-lg shadow-primary-500/30'
                                            : 'text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-white'
                                ]"
                            >
                                <SparklesIcon v-if="item.highlight && !subitem.locked" class="w-4 h-4 text-primary-400" />
                                <LockClosedIcon v-if="subitem.locked" class="w-4 h-4 text-gray-400 dark:text-gray-500" />
                                <span class="truncate">{{ subitem.label }}</span>
                                <span v-if="subitem.locked" class="ml-auto text-xs px-1.5 py-0.5 rounded bg-gray-200 dark:bg-gray-700 text-gray-500 dark:text-gray-400">
                                    PRO
                                </span>
                            </button>
                        </div>
                    </Transition>

                    <!-- Separator after highlighted menu -->
                    <div v-if="item.separatorAfter && !sidebarStore.isCollapsed" class="mt-4 mx-2 border-t border-gray-200 dark:border-gray-700"></div>
                    <div v-if="item.separatorAfter && sidebarStore.isCollapsed" class="mt-4 flex justify-center">
                        <div class="w-8 h-px bg-gray-200 dark:bg-gray-600"></div>
                    </div>
                </div>

                <!-- Regular item -->
                <button
                    v-else
                    @click="navigateTo(item.route)"
                    :class="[
                        'w-full flex items-center gap-3 px-4 py-3 rounded-xl font-medium relative',
                        item.locked
                            ? 'text-gray-400 dark:text-gray-500 hover:bg-gray-50 dark:hover:bg-gray-700'
                            : isActive(item.route)
                                ? 'bg-gradient-to-r from-primary-500 to-primary-600 text-white shadow-lg shadow-primary-500/30'
                                : 'text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-white',
                        sidebarStore.isCollapsed ? 'justify-center' : ''
                    ]"
                    :title="sidebarStore.isCollapsed ? item.label : ''"
                >
                    <component v-if="!item.locked" :is="item.icon" class="w-5 h-5 flex-shrink-0" />
                    <LockClosedIcon v-else class="w-5 h-5 flex-shrink-0 text-gray-400 dark:text-gray-500" />
                    <span v-if="!sidebarStore.isCollapsed" class="truncate">{{ item.label }}</span>
                    <span
                        v-if="item.locked && !sidebarStore.isCollapsed"
                        class="ml-auto text-xs px-1.5 py-0.5 rounded bg-gray-200 dark:bg-gray-700 text-gray-500 dark:text-gray-400"
                    >
                        PRO
                    </span>
                    <span
                        v-else-if="item.badge && item.badge > 0 && !sidebarStore.isCollapsed"
                        class="ml-auto px-2 py-0.5 text-xs font-semibold rounded-full bg-danger-500 text-white"
                    >
                        {{ item.badge > 99 ? '99+' : item.badge }}
                    </span>
                    <span
                        v-if="item.badge && item.badge > 0 && sidebarStore.isCollapsed"
                        class="absolute top-2 right-2 w-2 h-2 rounded-full bg-danger-500"
                    ></span>
                </button>
            </template>

            <!-- Admin Section -->
            <template v-if="showAdminSection">
                <div v-if="!sidebarStore.isCollapsed" class="pt-6 pb-2">
                    <span class="px-4 text-xs font-semibold text-gray-400 dark:text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        Administração
                    </span>
                </div>
                <div v-else class="pt-4 pb-2 flex justify-center">
                    <div class="w-8 h-px bg-gray-200 dark:bg-gray-600"></div>
                </div>

                <template v-for="item in visibleAdminItems" :key="item.route">
                    <button
                        @click="navigateTo(item.route)"
                        :class="[
                            'w-full flex items-center gap-3 px-4 py-3 rounded-xl font-medium',
                            isActive(item.route)
                                ? 'bg-gradient-to-r from-primary-500 to-primary-600 text-white shadow-lg shadow-primary-500/30'
                                : 'text-gray-600 dark:text-gray-400 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 dark:bg-gray-900 dark:hover:bg-gray-700 hover:text-gray-900 dark:text-gray-100 dark:hover:text-white',
                            sidebarStore.isCollapsed ? 'justify-center' : ''
                        ]"
                        :title="sidebarStore.isCollapsed ? item.label : ''"
                    >
                        <component :is="item.icon" class="w-5 h-5 flex-shrink-0" />
                        <span v-if="!sidebarStore.isCollapsed" class="truncate">{{ item.label }}</span>
                    </button>
                </template>
            </template>
        </nav>

        <!-- User Section -->
        <div class="p-4 border-t border-gray-100 dark:border-gray-700">
            <button
                @click="handleLogout"
                :class="[
                    'w-full flex items-center gap-3 px-4 py-3 rounded-xl font-medium text-gray-600 dark:text-gray-400 dark:text-gray-300 hover:bg-danger-50 dark:hover:bg-danger-900/20 hover:text-danger-600 dark:hover:text-danger-400',
                    sidebarStore.isCollapsed ? 'justify-center' : ''
                ]"
                :title="sidebarStore.isCollapsed ? 'Sair' : ''"
            >
                <ArrowLeftOnRectangleIcon class="w-5 h-5 flex-shrink-0" />
                <span v-if="!sidebarStore.isCollapsed">Sair</span>
            </button>
        </div>
    </aside>
</template>

<style scoped>
.slide-enter-active,
.slide-leave-active {
    transition: transform 0.3s ease;
}

.slide-enter-from,
.slide-leave-to {
    transform: translateX(-100%);
}

.submenu-enter-active,
.submenu-leave-active {
    transition: all 0.2s ease;
}

.submenu-enter-from,
.submenu-leave-to {
    opacity: 0;
    transform: translateY(-10px);
}
</style>
