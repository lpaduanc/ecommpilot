<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { PhotoIcon } from '@heroicons/vue/24/outline';

/**
 * Props for OptimizedImage component
 */
interface Props {
    /**
     * Image source URL
     */
    src: string;

    /**
     * Alt text for accessibility (required)
     */
    alt: string;

    /**
     * Image width in pixels
     */
    width?: number;

    /**
     * Image height in pixels
     */
    height?: number;

    /**
     * Enable lazy loading (default: true)
     * Uses native browser lazy loading
     */
    lazy?: boolean;

    /**
     * Fallback image URL if main image fails to load
     * @default '/images/placeholder-product.png'
     */
    fallback?: string;

    /**
     * Custom CSS classes for the image
     */
    imageClass?: string;

    /**
     * Custom CSS classes for the container
     */
    containerClass?: string;

    /**
     * Show skeleton loader while image is loading
     * @default true
     */
    showSkeleton?: boolean;

    /**
     * Aspect ratio for skeleton loader (e.g., '16/9', '4/3', '1/1')
     * @default '1/1'
     */
    aspectRatio?: string;

    /**
     * Object fit CSS property
     * @default 'cover'
     */
    objectFit?: 'cover' | 'contain' | 'fill' | 'none' | 'scale-down';
}

const props = withDefaults(defineProps<Props>(), {
    lazy: true,
    fallback: '/images/placeholder-product.png',
    imageClass: '',
    containerClass: '',
    showSkeleton: true,
    aspectRatio: '1/1',
    objectFit: 'cover',
});

/**
 * Component state
 */
const isLoading = ref(true);
const hasError = ref(false);
const imgElement = ref<HTMLImageElement | null>(null);

/**
 * Computed image source - returns fallback if error occurred
 */
const imageSrc = computed(() => hasError.value ? props.fallback : props.src);

/**
 * Computed object-fit class
 */
const objectFitClass = computed(() => {
    const fitMap = {
        cover: 'object-cover',
        contain: 'object-contain',
        fill: 'object-fill',
        none: 'object-none',
        'scale-down': 'object-scale-down',
    };
    return fitMap[props.objectFit];
});

/**
 * Handle successful image load
 */
const onLoad = () => {
    isLoading.value = false;
    hasError.value = false;
};

/**
 * Handle image load error
 */
const onError = (event: Event) => {
    const target = event.target as HTMLImageElement;

    // If already showing fallback and it failed, stop trying
    if (target.src === props.fallback || hasError.value) {
        isLoading.value = false;
        hasError.value = true;
        console.warn('[OptimizedImage] Failed to load image and fallback:', props.src);
        return;
    }

    // Try loading fallback image
    console.warn('[OptimizedImage] Failed to load image, trying fallback:', props.src);
    hasError.value = true;
    isLoading.value = false;
};

/**
 * Check if image is already cached
 * This prevents flash of loading state for cached images
 */
onMounted(() => {
    if (imgElement.value?.complete && imgElement.value?.naturalHeight !== 0) {
        isLoading.value = false;
    }
});
</script>

<template>
    <div
        :class="[
            'relative overflow-hidden',
            containerClass,
        ]"
        :style="showSkeleton && isLoading ? { aspectRatio } : undefined"
    >
        <!-- Skeleton Loader -->
        <div
            v-if="showSkeleton && isLoading"
            class="absolute inset-0 bg-gray-200 animate-pulse"
            aria-hidden="true"
        >
            <div class="flex items-center justify-center h-full">
                <PhotoIcon class="w-12 h-12 text-gray-400" />
            </div>
        </div>

        <!-- Image -->
        <img
            ref="imgElement"
            :src="imageSrc"
            :alt="alt"
            :width="width"
            :height="height"
            :loading="lazy ? 'lazy' : 'eager'"
            decoding="async"
            :class="[
                'transition-opacity duration-300',
                objectFitClass,
                imageClass,
                {
                    'opacity-0': isLoading,
                    'opacity-100': !isLoading,
                }
            ]"
            @load="onLoad"
            @error="onError"
        />

        <!-- Error State (when both main and fallback fail) -->
        <div
            v-if="hasError && imageSrc === fallback"
            class="absolute inset-0 bg-gray-100 flex flex-col items-center justify-center text-center p-4"
        >
            <PhotoIcon class="w-16 h-16 text-gray-400 mb-2" />
            <span class="text-sm text-gray-500">Imagem não disponível</span>
        </div>
    </div>
</template>

<style scoped>
/**
 * Ensure image fills container properly
 */
img {
    width: 100%;
    height: 100%;
    max-width: 100%;
}

/**
 * Prevent layout shift during loading
 */
.relative {
    min-height: 1px;
}
</style>
