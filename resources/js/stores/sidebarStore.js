import { defineStore } from 'pinia';
import { ref, computed } from 'vue';

export const useSidebarStore = defineStore('sidebar', () => {
    const isCollapsed = ref(false);

    const sidebarWidth = computed(() => isCollapsed.value ? '5rem' : '18rem'); // 80px : 288px (w-20 : w-72)
    const contentMargin = computed(() => isCollapsed.value ? 'lg:ml-20' : 'lg:ml-72');

    function toggle() {
        isCollapsed.value = !isCollapsed.value;
    }

    function collapse() {
        isCollapsed.value = true;
    }

    function expand() {
        isCollapsed.value = false;
    }

    return {
        isCollapsed,
        sidebarWidth,
        contentMargin,
        toggle,
        collapse,
        expand,
    };
});
