<script setup>
import { ref, computed, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/authStore';
import { useSidebarStore } from '../../stores/sidebarStore';
import {
    ChartBarIcon,
    CubeIcon,
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
} from '@heroicons/vue/24/outline';

const props = defineProps({
    isMobileOpen: { type: Boolean, default: false },
});

const emit = defineEmits(['close']);

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const sidebarStore = useSidebarStore();

// Marketing submenu state
const isMarketingOpen = ref(false);

// Close mobile sidebar on route change
watch(() => route.name, () => {
    emit('close');
});

const menuItems = computed(() => [
    {
        name: 'Dashboard',
        label: 'Dashboard',
        icon: ChartBarIcon,
        route: 'dashboard',
        permission: 'dashboard.view',
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
        name: 'Análises IA',
        label: 'Análises IA',
        icon: SparklesIcon,
        route: 'analysis',
        permission: 'analysis.view',
        highlight: true,
    },
    {
        name: 'Chat IA',
        label: 'Chat IA',
        icon: ChatBubbleLeftRightIcon,
        route: 'chat',
        permission: 'chat.use',
    },
    {
        name: 'Integrações',
        label: 'Integrações',
        icon: LinkIcon,
        route: 'integrations',
        permission: 'integrations.manage',
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
        name: 'Config. Sistema',
        label: 'Config. Sistema',
        icon: WrenchScrewdriverIcon,
        route: 'admin-settings',
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

function toggleMarketing() {
    isMarketingOpen.value = !isMarketingOpen.value;
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
            class="fixed left-0 top-0 h-full w-72 bg-white border-r border-gray-100 shadow-xl z-50 lg:hidden flex flex-col"
        >
            <!-- Mobile Close Button -->
            <div class="absolute top-4 right-4">
                <button
                    @click="emit('close')"
                    class="p-2 rounded-lg bg-gray-100 hover:bg-gray-200 transition-colors"
                >
                    <XMarkIcon class="w-5 h-5 text-gray-600" />
                </button>
            </div>

            <!-- Logo -->
            <div class="h-20 flex items-center px-6 border-b border-gray-100">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary-500 to-secondary-600 flex items-center justify-center shadow-lg shadow-primary-500/30">
                        <SparklesIcon class="w-6 h-6 text-white" />
                    </div>
                    <span class="font-display font-bold text-xl text-gray-900">Ecommpilot</span>
                </div>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 overflow-y-auto scrollbar-thin p-4 space-y-1">
                <template v-for="item in visibleMenuItems" :key="item.name">
                    <!-- Item with submenu -->
                    <div v-if="item.submenu">
                        <button
                            @click="toggleMarketing"
                            :class="[
                                'w-full flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all duration-200',
                                isSubmenuActive(item.submenu)
                                    ? 'text-primary-600 bg-primary-50'
                                    : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'
                            ]"
                        >
                            <component :is="item.icon" class="w-5 h-5 flex-shrink-0" />
                            <span class="truncate">{{ item.label }}</span>
                            <ChevronDownIcon
                                :class="[
                                    'w-4 h-4 ml-auto transition-transform duration-200',
                                    isMarketingOpen ? 'rotate-180' : ''
                                ]"
                            />
                        </button>

                        <!-- Submenu items -->
                        <Transition name="submenu">
                            <div v-if="isMarketingOpen" class="ml-4 mt-1 space-y-1">
                                <button
                                    v-for="subitem in item.submenu"
                                    :key="subitem.route"
                                    @click="navigateTo(subitem.route)"
                                    :class="[
                                        'w-full flex items-center gap-3 px-4 py-2 rounded-lg font-medium transition-all duration-200 text-sm',
                                        isActive(subitem.route)
                                            ? 'bg-gradient-to-r from-primary-500 to-primary-600 text-white shadow-lg shadow-primary-500/30'
                                            : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'
                                    ]"
                                >
                                    <span class="truncate">{{ subitem.label }}</span>
                                </button>
                            </div>
                        </Transition>
                    </div>

                    <!-- Regular item -->
                    <button
                        v-else
                        @click="navigateTo(item.route)"
                        :class="[
                            'w-full flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all duration-200',
                            isActive(item.route)
                                ? 'bg-gradient-to-r from-primary-500 to-primary-600 text-white shadow-lg shadow-primary-500/30'
                                : item.highlight
                                    ? 'text-primary-600 bg-primary-50 hover:bg-primary-100'
                                    : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'
                        ]"
                    >
                        <component
                            :is="item.icon"
                            :class="[
                                'w-5 h-5 flex-shrink-0',
                                item.highlight && !isActive(item.route) ? 'text-primary-500' : ''
                            ]"
                        />
                        <span class="truncate">{{ item.label }}</span>
                        <span
                            v-if="item.highlight"
                            class="ml-auto text-xs px-2 py-0.5 rounded-full bg-accent-400 text-white font-semibold"
                        >
                            IA
                        </span>
                    </button>
                </template>

                <!-- Admin Section -->
                <template v-if="showAdminSection">
                    <div class="pt-6 pb-2">
                        <span class="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                            Administração
                        </span>
                    </div>

                    <template v-for="item in visibleAdminItems" :key="item.route">
                        <button
                            @click="navigateTo(item.route)"
                            :class="[
                                'w-full flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all duration-200',
                                isActive(item.route)
                                    ? 'bg-gradient-to-r from-primary-500 to-primary-600 text-white shadow-lg shadow-primary-500/30'
                                    : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'
                            ]"
                        >
                            <component :is="item.icon" class="w-5 h-5 flex-shrink-0" />
                            <span class="truncate">{{ item.label }}</span>
                        </button>
                    </template>
                </template>
            </nav>

            <!-- User Section -->
            <div class="p-4 border-t border-gray-100">
                <button
                    @click="handleLogout"
                    class="w-full flex items-center gap-3 px-4 py-3 rounded-xl font-medium text-gray-600 hover:bg-danger-50 hover:text-danger-600 transition-all duration-200"
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
            'hidden lg:flex lg:flex-col fixed left-0 top-0 h-full bg-white border-r border-gray-100 shadow-sm z-40 transition-all duration-300',
            sidebarStore.isCollapsed ? 'w-20' : 'w-72'
        ]"
    >
        <!-- Logo -->
        <div class="h-20 flex items-center justify-between px-6 border-b border-gray-100">
            <div v-if="!sidebarStore.isCollapsed" class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary-500 to-secondary-600 flex items-center justify-center shadow-lg shadow-primary-500/30">
                    <SparklesIcon class="w-6 h-6 text-white" />
                </div>
                <span class="font-display font-bold text-xl text-gray-900">Ecommpilot</span>
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
            class="absolute -right-3 top-24 w-6 h-6 rounded-full bg-white border border-gray-200 shadow-sm flex items-center justify-center hover:bg-gray-50 transition-colors"
        >
            <ChevronDoubleLeftIcon v-if="!sidebarStore.isCollapsed" class="w-3 h-3 text-gray-500" />
            <ChevronDoubleRightIcon v-else class="w-3 h-3 text-gray-500" />
        </button>

        <!-- Navigation -->
        <nav class="flex-1 overflow-y-auto scrollbar-thin p-4 space-y-1">
            <!-- Main Menu -->
            <template v-for="item in visibleMenuItems" :key="item.name">
                <!-- Item with submenu -->
                <div v-if="item.submenu">
                    <button
                        @click="toggleMarketing"
                        :class="[
                            'w-full flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all duration-200',
                            isSubmenuActive(item.submenu)
                                ? 'text-primary-600 bg-primary-50'
                                : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900',
                            sidebarStore.isCollapsed ? 'justify-center' : ''
                        ]"
                        :title="sidebarStore.isCollapsed ? item.label : ''"
                    >
                        <component :is="item.icon" class="w-5 h-5 flex-shrink-0" />
                        <span v-if="!sidebarStore.isCollapsed" class="truncate">{{ item.label }}</span>
                        <ChevronDownIcon
                            v-if="!sidebarStore.isCollapsed"
                            :class="[
                                'w-4 h-4 ml-auto transition-transform duration-200',
                                isMarketingOpen ? 'rotate-180' : ''
                            ]"
                        />
                    </button>

                    <!-- Submenu items -->
                    <Transition name="submenu">
                        <div v-if="isMarketingOpen && !sidebarStore.isCollapsed" class="ml-4 mt-1 space-y-1">
                            <button
                                v-for="subitem in item.submenu"
                                :key="subitem.route"
                                @click="navigateTo(subitem.route)"
                                :class="[
                                    'w-full flex items-center gap-3 px-4 py-2 rounded-lg font-medium transition-all duration-200 text-sm',
                                    isActive(subitem.route)
                                        ? 'bg-gradient-to-r from-primary-500 to-primary-600 text-white shadow-lg shadow-primary-500/30'
                                        : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'
                                ]"
                            >
                                <span class="truncate">{{ subitem.label }}</span>
                            </button>
                        </div>
                    </Transition>
                </div>

                <!-- Regular item -->
                <button
                    v-else
                    @click="navigateTo(item.route)"
                    :class="[
                        'w-full flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all duration-200',
                        isActive(item.route)
                            ? 'bg-gradient-to-r from-primary-500 to-primary-600 text-white shadow-lg shadow-primary-500/30'
                            : item.highlight
                                ? 'text-primary-600 bg-primary-50 hover:bg-primary-100'
                                : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900',
                        sidebarStore.isCollapsed ? 'justify-center' : ''
                    ]"
                    :title="sidebarStore.isCollapsed ? item.label : ''"
                >
                    <component
                        :is="item.icon"
                        :class="[
                            'w-5 h-5 flex-shrink-0',
                            item.highlight && !isActive(item.route) ? 'text-primary-500' : ''
                        ]"
                    />
                    <span v-if="!sidebarStore.isCollapsed" class="truncate">{{ item.label }}</span>
                    <span
                        v-if="item.highlight && !sidebarStore.isCollapsed"
                        class="ml-auto text-xs px-2 py-0.5 rounded-full bg-accent-400 text-white font-semibold"
                    >
                        IA
                    </span>
                </button>
            </template>

            <!-- Admin Section -->
            <template v-if="showAdminSection">
                <div v-if="!sidebarStore.isCollapsed" class="pt-6 pb-2">
                    <span class="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                        Administração
                    </span>
                </div>
                <div v-else class="pt-4 pb-2 flex justify-center">
                    <div class="w-8 h-px bg-gray-200"></div>
                </div>

                <template v-for="item in visibleAdminItems" :key="item.route">
                    <button
                        @click="navigateTo(item.route)"
                        :class="[
                            'w-full flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all duration-200',
                            isActive(item.route)
                                ? 'bg-gradient-to-r from-primary-500 to-primary-600 text-white shadow-lg shadow-primary-500/30'
                                : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900',
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
        <div class="p-4 border-t border-gray-100">
            <button
                @click="handleLogout"
                :class="[
                    'w-full flex items-center gap-3 px-4 py-3 rounded-xl font-medium text-gray-600 hover:bg-danger-50 hover:text-danger-600 transition-all duration-200',
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
