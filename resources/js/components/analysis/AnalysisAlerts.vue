<script setup>
import {
    ExclamationTriangleIcon,
    ExclamationCircleIcon,
    InformationCircleIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';
import { ref } from 'vue';

const props = defineProps({
    alerts: { type: Array, default: () => [] },
});

const dismissedAlerts = ref([]);

const alertConfig = {
    warning: {
        icon: ExclamationTriangleIcon,
        gradient: 'from-amber-500 to-orange-500',
        bg: 'bg-gradient-to-r from-amber-50 to-orange-50',
        border: 'border-amber-200/50',
        iconBg: 'bg-gradient-to-br from-amber-400 to-orange-500',
        text: 'text-amber-900',
        subtext: 'text-amber-700',
    },
    danger: {
        icon: ExclamationCircleIcon,
        gradient: 'from-rose-500 to-red-500',
        bg: 'bg-gradient-to-r from-rose-50 to-red-50',
        border: 'border-rose-200/50',
        iconBg: 'bg-gradient-to-br from-rose-400 to-red-500',
        text: 'text-rose-900',
        subtext: 'text-rose-700',
    },
    info: {
        icon: InformationCircleIcon,
        gradient: 'from-blue-500 to-indigo-500',
        bg: 'bg-gradient-to-r from-blue-50 to-indigo-50',
        border: 'border-blue-200/50',
        iconBg: 'bg-gradient-to-br from-blue-400 to-indigo-500',
        text: 'text-blue-900',
        subtext: 'text-blue-700',
    },
};

function getConfig(type) {
    return alertConfig[type] || alertConfig.info;
}

function dismissAlert(index) {
    dismissedAlerts.value.push(index);
}

function isVisible(index) {
    return !dismissedAlerts.value.includes(index);
}
</script>

<template>
    <div class="space-y-3">
        <transition-group name="alert">
            <div
                v-for="(alert, index) in alerts"
                :key="index"
                v-show="isVisible(index)"
                :class="[
                    'group relative flex items-start gap-4 p-4 rounded-2xl border backdrop-blur-sm transition-all duration-300 hover:shadow-lg',
                    getConfig(alert.type).bg,
                    getConfig(alert.type).border
                ]"
            >
                <!-- Icon -->
                <div 
                    :class="[
                        'flex-shrink-0 w-10 h-10 rounded-xl flex items-center justify-center shadow-lg',
                        getConfig(alert.type).iconBg
                    ]"
                >
                    <component
                        :is="getConfig(alert.type).icon"
                        class="w-5 h-5 text-white"
                    />
                </div>

                <!-- Content -->
                <div class="flex-1 min-w-0">
                    <p :class="['font-medium leading-relaxed', getConfig(alert.type).text]">
                        {{ alert.message }}
                    </p>
                </div>

                <!-- Dismiss Button -->
                <button
                    @click.stop="dismissAlert(index)"
                    :class="[
                        'flex-shrink-0 p-1.5 rounded-lg opacity-0 group-hover:opacity-100 transition-all duration-200 hover:bg-white/50',
                        getConfig(alert.type).subtext
                    ]"
                >
                    <XMarkIcon class="w-4 h-4" />
                </button>

                <!-- Accent Line -->
                <div 
                    :class="[
                        'absolute left-0 top-4 bottom-4 w-1 rounded-r-full bg-gradient-to-b',
                        getConfig(alert.type).gradient
                    ]"
                ></div>
            </div>
        </transition-group>
    </div>
</template>

<style scoped>
.alert-enter-active,
.alert-leave-active {
    transition: all 0.3s ease;
}

.alert-enter-from {
    opacity: 0;
    transform: translateX(-20px);
}

.alert-leave-to {
    opacity: 0;
    transform: translateX(20px);
}
</style>
