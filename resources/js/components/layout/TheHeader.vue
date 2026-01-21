<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/authStore';
import { useSystemNotificationStore } from '../../stores/systemNotificationStore';
import StoreSelector from './StoreSelector.vue';
import ThemeToggle from '../common/ThemeToggle.vue';
import NotificationDropdown from '../notifications/NotificationDropdown.vue';
import {
    BellIcon,
    MagnifyingGlassIcon,
    UserCircleIcon,
    Cog6ToothIcon,
    ArrowLeftOnRectangleIcon,
    Bars3Icon,
} from '@heroicons/vue/24/outline';

const emit = defineEmits(['toggle-mobile-sidebar']);

const router = useRouter();
const authStore = useAuthStore();
const notificationStore = useSystemNotificationStore();

const showUserMenu = ref(false);
const showNotifications = ref(false);
const showMobileSearch = ref(false);
const searchQuery = ref('');

const userName = computed(() => authStore.userName);
const userEmail = computed(() => authStore.userEmail);
const userInitials = computed(() => {
    if (!userName.value) return 'U';
    return userName.value
        .split(' ')
        .map(n => n[0])
        .join('')
        .substring(0, 2)
        .toUpperCase();
});

const unreadCount = computed(() => notificationStore.unreadCount);

function toggleUserMenu() {
    showUserMenu.value = !showUserMenu.value;
    showNotifications.value = false;
}

function toggleNotifications() {
    showNotifications.value = !showNotifications.value;
    showUserMenu.value = false;

    // Fetch unread notifications when opening dropdown
    if (showNotifications.value) {
        notificationStore.fetchUnread();
    }
}

function closeMenus() {
    showUserMenu.value = false;
    showNotifications.value = false;
}

function handleSearch() {
    if (searchQuery.value.trim()) {
        router.push({ name: 'products', query: { search: searchQuery.value } });
        showMobileSearch.value = false;
    }
}

function goToSettings() {
    closeMenus();
    router.push({ name: 'settings' });
}

async function handleLogout() {
    closeMenus();
    await authStore.logoutFromServer();
    router.push({ name: 'login' });
}

// Close menus on outside click
function handleClickOutside(event) {
    const userMenuEl = document.getElementById('user-menu');
    const notificationsEl = document.getElementById('notifications-menu');
    
    if (userMenuEl && !userMenuEl.contains(event.target)) {
        showUserMenu.value = false;
    }
    
    if (notificationsEl && !notificationsEl.contains(event.target)) {
        showNotifications.value = false;
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside);

    // Fetch unread notifications on mount
    notificationStore.fetchUnread();

    // Start polling for new notifications every minute
    notificationStore.startPolling(60000);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);

    // Stop polling when component is destroyed
    notificationStore.stopPolling();
});
</script>

<template>
    <header class="sticky top-0 z-30 bg-white/80 dark:bg-gray-800/80 backdrop-blur-xl border-b border-gray-100 dark:border-gray-700">
        <div class="flex items-center justify-between h-16 lg:h-20 px-4 lg:px-8">
            <!-- Left Section: Mobile Menu + Search -->
            <div class="flex items-center gap-3">
                <!-- Mobile Menu Button -->
                <button
                    @click="emit('toggle-mobile-sidebar')"
                    class="lg:hidden p-2 -ml-2 rounded-xl text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 dark:bg-gray-800 dark:hover:bg-gray-700"
                >
                    <Bars3Icon class="w-6 h-6" />
                </button>

                <!-- Search Bar - Desktop -->
                <div class="hidden md:block flex-1 max-w-xl">
                    <div class="relative">
                        <MagnifyingGlassIcon class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
                        <input
                            v-model="searchQuery"
                            @keyup.enter="handleSearch"
                            type="text"
                            placeholder="Buscar produtos, pedidos..."
                            class="w-full pl-12 pr-4 py-3 rounded-xl bg-gray-50 dark:bg-gray-900 dark:bg-gray-700 border border-gray-100 dark:border-gray-700 dark:border-gray-600 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:bg-white dark:bg-gray-800 dark:focus:bg-gray-600 focus:border-primary-200 dark:focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 focus:outline-none"
                        />
                    </div>
                </div>

                <!-- Mobile Search Button -->
                <button
                    @click="showMobileSearch = !showMobileSearch"
                    class="md:hidden p-2 rounded-xl text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 dark:bg-gray-800 dark:hover:bg-gray-700"
                >
                    <MagnifyingGlassIcon class="w-6 h-6" />
                </button>
            </div>

            <!-- Right Section -->
            <div class="flex items-center gap-2 lg:gap-4">
                <!-- Store Selector -->
                <StoreSelector v-if="!authStore.isAdmin" class="hidden sm:block" />

                <!-- Theme Toggle -->
                <ThemeToggle />

                <!-- Notifications -->
                <div id="notifications-menu" class="relative">
                    <button
                        @click.stop="toggleNotifications"
                        class="relative p-2 lg:p-3 rounded-xl text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 dark:bg-gray-900 dark:hover:bg-gray-700"
                    >
                        <BellIcon class="w-5 h-5 lg:w-6 lg:h-6" />
                        <span
                            v-if="unreadCount > 0"
                            class="absolute top-1.5 right-1.5 lg:top-2 lg:right-2 w-2 h-2 rounded-full bg-danger-500"
                        ></span>
                    </button>

                    <!-- Notifications Dropdown -->
                    <transition
                        enter-active-class="transition ease-out duration-200"
                        enter-from-class="opacity-0 translate-y-1"
                        enter-to-class="opacity-100 translate-y-0"
                        leave-active-class="transition ease-in duration-150"
                        leave-from-class="opacity-100 translate-y-0"
                        leave-to-class="opacity-0 translate-y-1"
                    >
                        <div
                            v-if="showNotifications"
                            class="absolute right-0 mt-2"
                        >
                            <NotificationDropdown />
                        </div>
                    </transition>
                </div>

                <!-- User Menu -->
                <div id="user-menu" class="relative">
                    <button
                        @click.stop="toggleUserMenu"
                        class="flex items-center gap-2 lg:gap-3 p-1.5 lg:p-2 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700 dark:bg-gray-900 dark:hover:bg-gray-700"
                    >
                        <div class="w-9 h-9 lg:w-10 lg:h-10 rounded-xl bg-gradient-to-br from-primary-400 to-primary-600 flex items-center justify-center text-white font-semibold text-sm lg:text-base shadow-lg shadow-primary-500/30">
                            {{ userInitials }}
                        </div>
                        <div class="hidden lg:block text-left">
                            <p class="font-medium text-gray-900 dark:text-gray-100 dark:text-white text-sm">{{ userName }}</p>
                            <p class="text-gray-500 dark:text-gray-400 text-xs">{{ userEmail }}</p>
                        </div>
                    </button>

                    <!-- User Dropdown -->
                    <transition
                        enter-active-class="transition ease-out duration-200"
                        enter-from-class="opacity-0 translate-y-1"
                        enter-to-class="opacity-100 translate-y-0"
                        leave-active-class="transition ease-in duration-150"
                        leave-from-class="opacity-100 translate-y-0"
                        leave-to-class="opacity-0 translate-y-1"
                    >
                        <div
                            v-if="showUserMenu"
                            class="absolute right-0 mt-2 w-56 rounded-2xl bg-white dark:bg-gray-800 shadow-xl ring-1 ring-black/5 dark:ring-gray-700 overflow-hidden"
                        >
                            <!-- Mobile only: User info -->
                            <div class="lg:hidden px-4 py-3 border-b border-gray-100 dark:border-gray-700">
                                <p class="font-medium text-gray-900 dark:text-gray-100 dark:text-white text-sm">{{ userName }}</p>
                                <p class="text-gray-500 dark:text-gray-400 text-xs">{{ userEmail }}</p>
                            </div>
                            <div class="p-2">
                                <button
                                    @click="goToSettings"
                                    class="w-full flex items-center gap-3 px-4 py-2.5 rounded-xl text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 dark:bg-gray-900 dark:hover:bg-gray-700 text-sm"
                                >
                                    <UserCircleIcon class="w-5 h-5 text-gray-400" />
                                    <span>Meu Perfil</span>
                                </button>
                                <button
                                    @click="goToSettings"
                                    class="w-full flex items-center gap-3 px-4 py-2.5 rounded-xl text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 dark:bg-gray-900 dark:hover:bg-gray-700 text-sm"
                                >
                                    <Cog6ToothIcon class="w-5 h-5 text-gray-400" />
                                    <span>Configurações</span>
                                </button>
                            </div>
                            <div class="border-t border-gray-100 dark:border-gray-700 p-2">
                                <button
                                    @click="handleLogout"
                                    class="w-full flex items-center gap-3 px-4 py-2.5 rounded-xl text-danger-600 dark:text-danger-400 hover:bg-danger-50 dark:hover:bg-danger-900/20 text-sm"
                                >
                                    <ArrowLeftOnRectangleIcon class="w-5 h-5" />
                                    <span>Sair</span>
                                </button>
                            </div>
                        </div>
                    </transition>
                </div>
            </div>
        </div>

        <!-- Mobile Search Bar -->
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0 -translate-y-2"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 -translate-y-2"
        >
            <div v-if="showMobileSearch" class="md:hidden px-4 pb-4">
                <div class="relative">
                    <MagnifyingGlassIcon class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
                    <input
                        v-model="searchQuery"
                        @keyup.enter="handleSearch"
                        type="text"
                        placeholder="Buscar produtos, pedidos..."
                        class="w-full pl-12 pr-4 py-3 rounded-xl bg-gray-50 dark:bg-gray-900 dark:bg-gray-700 border border-gray-100 dark:border-gray-700 dark:border-gray-600 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:bg-white dark:bg-gray-800 dark:focus:bg-gray-600 focus:border-primary-200 dark:focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 focus:outline-none"
                        autofocus
                    />
                </div>
            </div>
        </Transition>
    </header>
</template>
