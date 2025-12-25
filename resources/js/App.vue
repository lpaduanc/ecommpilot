<script setup>
import { computed, ref, provide } from 'vue';
import { useRoute } from 'vue-router';
import TheSidebar from './components/layout/TheSidebar.vue';
import TheHeader from './components/layout/TheHeader.vue';
import NotificationToast from './components/common/NotificationToast.vue';
import { useAuthStore } from './stores/authStore';

const route = useRoute();
const authStore = useAuthStore();

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
    <div class="min-h-screen bg-gray-50">
        <div v-if="showLayout" class="flex">
            <!-- Mobile Sidebar Overlay -->
            <Transition name="fade">
                <div 
                    v-if="isMobileSidebarOpen"
                    class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm z-40 lg:hidden"
                    @click="isMobileSidebarOpen = false"
                ></div>
            </Transition>
            
            <TheSidebar :is-mobile-open="isMobileSidebarOpen" @close="isMobileSidebarOpen = false" />
            
            <!-- Main Content - responsive margin -->
            <div class="flex-1 lg:ml-72 min-h-screen">
                <TheHeader @toggle-mobile-sidebar="isMobileSidebarOpen = !isMobileSidebarOpen" />
                <main class="p-4 sm:p-6 lg:p-8">
                    <router-view v-slot="{ Component }">
                        <transition
                            name="fade"
                            mode="out-in"
                        >
                            <component :is="Component" />
                        </transition>
                    </router-view>
                </main>
            </div>
        </div>
        
        <div v-else>
            <router-view v-slot="{ Component }">
                <transition
                    name="fade"
                    mode="out-in"
                >
                    <component :is="Component" />
                </transition>
            </router-view>
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
</style>

