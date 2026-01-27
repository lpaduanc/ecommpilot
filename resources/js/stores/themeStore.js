import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { logger } from '../utils/logger';

export const useThemeStore = defineStore('theme', () => {
    // Get stored theme or default
    function getStoredTheme() {
        if (typeof window === 'undefined') return 'system';
        return localStorage.getItem('theme') || 'light';
    }

    // Possible values: 'light', 'dark', 'system'
    const theme = ref(getStoredTheme());

    // Check if should be dark based on theme value
    function shouldBeDark(themeValue) {
        if (themeValue === 'dark') return true;
        if (themeValue === 'light') return false;
        // system - check media query
        return window.matchMedia('(prefers-color-scheme: dark)').matches;
    }

    // Computed property that resolves the actual theme considering system preference
    const isDark = computed(() => shouldBeDark(theme.value));

    // Current resolved theme ('light' or 'dark')
    const resolvedTheme = computed(() => isDark.value ? 'dark' : 'light');

    // Apply theme class to document element
    function applyTheme(themeValue = theme.value) {
        const root = document.documentElement;
        const dark = shouldBeDark(themeValue);

        if (dark) {
            root.classList.add('dark');
        } else {
            root.classList.remove('dark');
        }
    }

    // Set theme and persist to localStorage
    function setTheme(newTheme) {
        if (!['light', 'dark', 'system'].includes(newTheme)) {
            logger.warn(`Invalid theme: ${newTheme}`);
            return;
        }

        theme.value = newTheme;
        localStorage.setItem('theme', newTheme);
        applyTheme(newTheme); // Pass theme directly for immediate effect
    }

    // Cycle through themes: light -> dark -> system -> light
    function cycleTheme() {
        const themes = ['light', 'dark', 'system'];
        const currentIndex = themes.indexOf(theme.value);
        const nextIndex = (currentIndex + 1) % themes.length;
        setTheme(themes[nextIndex]);
    }

    // Initialize theme on store creation
    function initTheme() {
        applyTheme();

        // Listen for system preference changes
        const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        mediaQuery.addEventListener('change', () => {
            if (theme.value === 'system') {
                applyTheme();
            }
        });
    }

    return {
        theme,
        isDark,
        resolvedTheme,
        setTheme,
        cycleTheme,
        initTheme,
        applyTheme,
    };
});
