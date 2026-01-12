<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { useDashboardStore } from '../../stores/dashboardStore';
import BaseButton from '../common/BaseButton.vue';
import {
    FunnelIcon,
    CalendarIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';

const emit = defineEmits(['change']);
const dashboardStore = useDashboardStore();

const showFilters = ref(false);
const buttonRef = ref(null);
const dropdownPosition = ref({ top: 0, right: 0 });

// Local state for the form
const selectedPeriod = ref(dashboardStore.filters.period);
const startDate = ref(dashboardStore.filters.startDate || '');
const endDate = ref(dashboardStore.filters.endDate || '');

// Sync local state with store when dropdown opens
watch(showFilters, (isOpen) => {
    if (isOpen) {
        // Sync local state with store when opening
        selectedPeriod.value = dashboardStore.filters.period;
        startDate.value = dashboardStore.filters.startDate || '';
        endDate.value = dashboardStore.filters.endDate || '';
        // Update position after Vue renders
        setTimeout(updateDropdownPosition, 0);
    }
});

// Calculate dropdown position relative to the button
function updateDropdownPosition() {
    if (buttonRef.value && showFilters.value) {
        const rect = buttonRef.value.$el?.getBoundingClientRect() || buttonRef.value.getBoundingClientRect();
        dropdownPosition.value = {
            top: rect.bottom + 8,
            right: window.innerWidth - rect.right,
        };
    }
}

// Close dropdown when clicking outside
function handleClickOutside(event) {
    const button = buttonRef.value?.$el || buttonRef.value;
    if (showFilters.value && button && !button.contains(event.target)) {
        // Check if click is inside the dropdown (which is teleported)
        const dropdown = document.getElementById('dashboard-filters-dropdown');
        if (dropdown && !dropdown.contains(event.target)) {
            showFilters.value = false;
        }
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
    window.addEventListener('resize', updateDropdownPosition);
    window.addEventListener('scroll', updateDropdownPosition, true);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
    window.removeEventListener('resize', updateDropdownPosition);
    window.removeEventListener('scroll', updateDropdownPosition, true);
});

const periodOptions = [
    { value: 'today', label: 'Hoje' },
    { value: 'last_7_days', label: 'Últimos 7 dias' },
    { value: 'last_15_days', label: 'Últimos 15 dias' },
    { value: 'last_30_days', label: 'Últimos 30 dias' },
    { value: 'this_month', label: 'Este mês' },
    { value: 'last_month', label: 'Último mês' },
    { value: 'all_time', label: 'Todo o período' },
    { value: 'custom', label: 'Personalizado' },
];

const isCustomPeriod = computed(() => selectedPeriod.value === 'custom');

// Show label from store's current period (not local)
const currentPeriodLabel = computed(() => {
    const storePeriod = dashboardStore.filters.period;
    if (storePeriod === 'custom' && dashboardStore.filters.startDate && dashboardStore.filters.endDate) {
        // Format custom date range for display
        const start = new Date(dashboardStore.filters.startDate);
        const end = new Date(dashboardStore.filters.endDate);
        return `${start.toLocaleDateString('pt-BR')} - ${end.toLocaleDateString('pt-BR')}`;
    }
    const option = periodOptions.find(o => o.value === storePeriod);
    return option?.label || 'Últimos 15 dias';
});

function selectPeriod(period) {
    selectedPeriod.value = period;
    if (period !== 'custom') {
        dashboardStore.setFilters({
            period: period,
            startDate: null,
            endDate: null,
        });
        showFilters.value = false;
        emit('change');
    }
}

function applyCustomPeriod() {
    if (startDate.value && endDate.value) {
        dashboardStore.setFilters({
            period: 'custom',
            startDate: startDate.value,
            endDate: endDate.value,
        });
        showFilters.value = false;
        emit('change');
    }
}

function clearFilters() {
    selectedPeriod.value = 'last_15_days';
    startDate.value = '';
    endDate.value = '';

    dashboardStore.resetFilters();
    showFilters.value = false;
    emit('change');
}

function toggleFilters() {
    showFilters.value = !showFilters.value;
}
</script>

<template>
    <div class="relative">
        <BaseButton ref="buttonRef" variant="secondary" @click="toggleFilters">
            <FunnelIcon class="w-4 h-4" />
            {{ currentPeriodLabel }}
        </BaseButton>

        <!-- Dropdown - Teleported to body to avoid overflow:hidden issues -->
        <Teleport to="body">
            <transition
                enter-active-class="transition ease-out duration-200"
                enter-from-class="opacity-0 translate-y-1"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition ease-in duration-150"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 translate-y-1"
            >
                <div
                    v-if="showFilters"
                    id="dashboard-filters-dropdown"
                    class="fixed w-80 rounded-2xl bg-white dark:bg-gray-800 shadow-xl ring-1 ring-black/5 dark:ring-white/10 z-[9999] p-4"
                    :style="{ top: dropdownPosition.top + 'px', right: dropdownPosition.right + 'px' }"
                >
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold text-gray-900 dark:text-gray-100">Período</h3>
                    <button @click="showFilters = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <XMarkIcon class="w-5 h-5" />
                    </button>
                </div>

                <!-- Period Selection -->
                <div class="grid grid-cols-2 gap-2 mb-4">
                    <button
                        v-for="option in periodOptions"
                        :key="option.value"
                        @click="selectPeriod(option.value)"
                        :class="[
                            'px-3 py-2 rounded-lg text-sm font-medium transition-colors',
                            selectedPeriod === option.value
                                ? 'bg-primary-100 dark:bg-primary-900/50 text-primary-700 dark:text-primary-300'
                                : 'bg-gray-50 dark:bg-gray-900 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800'
                        ]"
                    >
                        {{ option.label }}
                    </button>
                </div>

                <!-- Custom Date Range -->
                <div v-if="isCustomPeriod" class="space-y-3 pt-4 border-t border-gray-100 dark:border-gray-700">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Data Inicial</label>
                        <div class="relative">
                            <CalendarIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
                            <input
                                v-model="startDate"
                                type="date"
                                class="w-full pl-10 pr-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                            />
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Data Final</label>
                        <div class="relative">
                            <CalendarIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
                            <input
                                v-model="endDate"
                                type="date"
                                class="w-full pl-10 pr-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                            />
                        </div>
                    </div>
                    <BaseButton size="sm" class="w-full" @click="applyCustomPeriod" :disabled="!startDate || !endDate">
                        Aplicar Período
                    </BaseButton>
                </div>
                </div>
            </transition>
        </Teleport>
    </div>
</template>

