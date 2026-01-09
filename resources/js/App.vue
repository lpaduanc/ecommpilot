<script setup>
import { computed, ref, provide, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import TheSidebar from './components/layout/TheSidebar.vue';
import TheHeader from './components/layout/TheHeader.vue';
import NotificationToast from './components/common/NotificationToast.vue';
import ErrorBoundary from './components/shared/ErrorBoundary.vue';
import { useAuthStore } from './stores/authStore';
import { useSidebarStore } from './stores/sidebarStore';
import { useThemeStore } from './stores/themeStore';

const route = useRoute();
const authStore = useAuthStore();
const sidebarStore = useSidebarStore();
const themeStore = useThemeStore();

// Initialize theme on app mount
onMounted(() => {
    themeStore.initTheme();
});

const isAuthenticated = computed(() => authStore.isAuthenticated);
const isAuthPage = computed(() => {
    const authRoutes = ['login', 'register', 'forgot-password', 'reset-password'];
    return authRoutes.includes(route.name);
});

const showLayout = computed(() => isAuthenticated.value && !isAuthPage.value);

// Mobile sidebar state
const isMobileSidebarOpen = ref(false);
provide('mobileSidebar', {
    isOpen: isMobileSidebarOpen,
    toggle: () => isMobileSidebarOpen.value = !isMobileSidebarOpen.value,
    close: () => isMobileSidebarOpen.value = false,
});
</script>

<template>
    <div class="min-h-screen bg-gray-50 dark:bg-gray-900 transition-colors duration-200">
        <!-- Skip Links (acessibilidade) - visível apenas no :focus -->
        <nav aria-label="Atalhos de teclado" class="sr-only focus-within:not-sr-only">
            <a
                href="#main-content"
                class="skip-link"
            >
                Pular para conteúdo principal
            </a>
            <a
                href="#sidebar-nav"
                class="skip-link"
            >
                Ir para navegação
            </a>
        </nav>

        <div v-if="showLayout" class="flex">
            <!-- Mobile Sidebar Overlay -->
            <Transition name="fade">
                <div
                    v-if="isMobileSidebarOpen"
                    class="fixed inset-0 bg-gray-900/50 dark:bg-black/60 backdrop-blur-sm z-40 lg:hidden"
                    @click="isMobileSidebarOpen = false"
                ></div>
            </Transition>

            <TheSidebar id="sidebar-nav" :is-mobile-open="isMobileSidebarOpen" @close="isMobileSidebarOpen = false" />

            <!-- Main Content - responsive margin -->
            <div :class="['flex-1 min-h-screen transition-all duration-300', sidebarStore.contentMargin]">
                <TheHeader @toggle-mobile-sidebar="isMobileSidebarOpen = !isMobileSidebarOpen" />
                <main id="main-content" class="p-4 sm:p-6 lg:p-8">
                    <!-- ErrorBoundary envolve o router-view para capturar erros em rotas -->
                    <ErrorBoundary>
                        <router-view v-slot="{ Component }">
                            <transition
                                name="fade"
                                mode="out-in"
                            >
                                <component :is="Component" />
                            </transition>
                        </router-view>
                    </ErrorBoundary>
                </main>
            </div>
        </div>
        
        <div v-else>
            <!-- ErrorBoundary também protege páginas de autenticação -->
            <ErrorBoundary>
                <router-view v-slot="{ Component }">
                    <transition
                        name="fade"
                        mode="out-in"
                    >
                        <component :is="Component" />
                    </transition>
                </router-view>
            </ErrorBoundary>
        </div>
        
        <NotificationToast />
    </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

/* Screen Reader Only - esconde visualmente mas mantém acessível */
.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border-width: 0;
}

/* Mostra skip links quando recebem foco */
.focus-within\:not-sr-only:focus-within {
    position: static;
    width: auto;
    height: auto;
    padding: inherit;
    margin: inherit;
    overflow: visible;
    clip: auto;
    white-space: normal;
}

/* Estilização dos Skip Links */
.skip-link {
    position: fixed;
    top: 1rem;
    left: 1rem;
    z-index: 9999;
    padding: 0.75rem 1.5rem;
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    color: white;
    font-weight: 600;
    font-size: 0.875rem;
    border-radius: 0.5rem;
    box-shadow: 0 10px 25px -5px rgba(99, 102, 241, 0.4);
    text-decoration: none;
    outline: none;
    transition: all 0.2s ease;
}

.skip-link:focus {
    outline: 2px solid #6366f1;
    outline-offset: 2px;
    transform: scale(1.05);
}

.skip-link:hover {
    background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
    box-shadow: 0 15px 30px -5px rgba(99, 102, 241, 0.5);
}

.skip-link + .skip-link {
    left: 12rem;
}
</style>

