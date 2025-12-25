<script setup>
import { ref, computed } from 'vue';
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

const periodOptions = [
    { value: 'today', label: 'Hoje' },
    { value: 'last_7_days', label: 'Últimos 7 dias' },
    { value: 'last_30_days', label: 'Últimos 30 dias' },
    { value: 'this_month', label: 'Este mês' },
    { value: 'last_month', label: 'Último mês' },
    { value: 'custom', label: 'Personalizado' },
];

const selectedPeriod = ref(dashboardStore.filters.period);
const startDate = ref(dashboardStore.filters.startDate || '');
const endDate = ref(dashboardStore.filters.endDate || '');

const isCustomPeriod = computed(() => selectedPeriod.value === 'custom');

const currentPeriodLabel = computed(() => {
    const option = periodOptions.find(o => o.value === selectedPeriod.value);
    return option?.label || 'Selecionar';
});

function applyFilters() {
    dashboardStore.setFilters({
        period: selectedPeriod.value,
        startDate: isCustomPeriod.value ? startDate.value : null,
        endDate: isCustomPeriod.value ? endDate.value : null,
    });
    
    showFilters.value = false;
    emit('change');
}

function clearFilters() {
    selectedPeriod.value = 'last_30_days';
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
        <BaseButton variant="secondary" @click="toggleFilters">
            <FunnelIcon class="w-4 h-4" />
            {{ currentPeriodLabel }}
        </BaseButton>

        <!-- Dropdown -->
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
                class="absolute right-0 mt-2 w-80 rounded-2xl bg-white shadow-xl ring-1 ring-black/5 z-50 p-4"
            >
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold text-gray-900">Filtros</h3>
                    <button @click="showFilters = false" class="text-gray-400 hover:text-gray-600">
                        <XMarkIcon class="w-5 h-5" />
                    </button>
                </div>

                <!-- Period Selection -->
                <div class="space-y-3 mb-4">
                    <label class="block text-sm font-medium text-gray-700">Período</label>
                    <div class="grid grid-cols-2 gap-2">
                        <button
                            v-for="option in periodOptions"
                            :key="option.value"
                            @click="selectedPeriod = option.value"
                            :class="[
                                'px-3 py-2 rounded-lg text-sm font-medium transition-colors',
                                selectedPeriod === option.value
                                    ? 'bg-primary-100 text-primary-700'
                                    : 'bg-gray-50 text-gray-600 hover:bg-gray-100'
                            ]"
                        >
                            {{ option.label }}
                        </button>
                    </div>
                </div>

                <!-- Custom Date Range -->
                <div v-if="isCustomPeriod" class="space-y-3 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Data Inicial</label>
                        <div class="relative">
                            <CalendarIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
                            <input
                                v-model="startDate"
                                type="date"
                                class="input pl-10"
                            />
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Data Final</label>
                        <div class="relative">
                            <CalendarIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
                            <input
                                v-model="endDate"
                                type="date"
                                class="input pl-10"
                            />
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center gap-2 pt-4 border-t border-gray-100">
                    <BaseButton variant="ghost" size="sm" @click="clearFilters">
                        Limpar Filtros
                    </BaseButton>
                    <BaseButton size="sm" class="flex-1" @click="applyFilters">
                        Aplicar Filtros
                    </BaseButton>
                </div>
            </div>
        </transition>
    </div>
</template>

