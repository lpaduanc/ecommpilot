<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { InformationCircleIcon } from '@heroicons/vue/20/solid';

const props = defineProps({
    text: { type: String, required: true },
    position: { type: String, default: 'top' },
    maxWidth: { type: String, default: 'max-w-xs' },
    iconClass: { type: String, default: '' },
});

const tooltipId = `tooltip-${Math.random().toString(36).slice(2, 9)}`;
const isVisible = ref(false);
const buttonRef = ref(null);

function show() {
    isVisible.value = true;
}

function hide() {
    isVisible.value = false;
}

function toggle() {
    isVisible.value = !isVisible.value;
}

function onClickOutside(event) {
    if (buttonRef.value && !buttonRef.value.contains(event.target)) {
        isVisible.value = false;
    }
}

onMounted(() => {
    document.addEventListener('click', onClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', onClickOutside);
});

const positionClasses = {
    top: 'bottom-full left-1/2 -translate-x-1/2 mb-2',
    bottom: 'top-full left-1/2 -translate-x-1/2 mt-2',
    left: 'right-full top-1/2 -translate-y-1/2 mr-2',
    right: 'left-full top-1/2 -translate-y-1/2 ml-2',
};

const arrowClasses = {
    top: 'top-full left-1/2 -translate-x-1/2 border-t-gray-900 dark:border-t-gray-700 border-l-transparent border-r-transparent border-b-transparent',
    bottom: 'bottom-full left-1/2 -translate-x-1/2 border-b-gray-900 dark:border-b-gray-700 border-l-transparent border-r-transparent border-t-transparent',
    left: 'left-full top-1/2 -translate-y-1/2 border-l-gray-900 dark:border-l-gray-700 border-t-transparent border-b-transparent border-r-transparent',
    right: 'right-full top-1/2 -translate-y-1/2 border-r-gray-900 dark:border-r-gray-700 border-t-transparent border-b-transparent border-l-transparent',
};
</script>

<template>
    <span class="relative inline-flex items-center">
        <button
            ref="buttonRef"
            type="button"
            tabindex="0"
            :aria-describedby="tooltipId"
            class="flex items-center focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 rounded"
            :class="iconClass || 'text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-400'"
            @mouseenter="show"
            @mouseleave="hide"
            @focus="show"
            @blur="hide"
            @click.stop="toggle"
        >
            <InformationCircleIcon class="w-4 h-4" aria-hidden="true" />
            <span class="sr-only">Mais informações</span>
        </button>

        <Transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-200"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="isVisible"
                :id="tooltipId"
                role="tooltip"
                :class="[
                    'absolute z-50 pointer-events-none',
                    positionClasses[position] || positionClasses.top,
                    maxWidth,
                ]"
            >
                <div class="relative bg-gray-900 dark:bg-gray-700 text-white text-xs rounded-lg px-3 py-2 shadow-lg leading-relaxed w-max max-w-xs">
                    {{ text }}
                    <!-- Arrow -->
                    <span
                        class="absolute w-0 h-0 border-4"
                        :class="arrowClasses[position] || arrowClasses.top"
                    ></span>
                </div>
            </div>
        </Transition>
    </span>
</template>
