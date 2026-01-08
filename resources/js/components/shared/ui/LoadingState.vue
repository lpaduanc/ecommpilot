<script setup lang="ts">
import LoadingSpinner from '../../common/LoadingSpinner.vue';

type LoadingVariant = 'fullscreen' | 'overlay' | 'inline' | 'skeleton';

interface Props {
  variant?: LoadingVariant;
  message?: string;
}

const props = withDefaults(defineProps<Props>(), {
  variant: 'inline',
  message: 'Carregando...',
});
</script>

<template>
  <!-- Fullscreen Loading -->
  <div
    v-if="variant === 'fullscreen'"
    class="fixed inset-0 z-50 bg-white flex items-center justify-center"
    role="status"
    aria-live="polite"
  >
    <div class="text-center">
      <LoadingSpinner size="xl" class="text-primary-500 mx-auto mb-4" />
      <p class="text-gray-600 font-medium">{{ message }}</p>
    </div>
  </div>

  <!-- Overlay Loading -->
  <div
    v-else-if="variant === 'overlay'"
    class="absolute inset-0 bg-white/80 backdrop-blur-sm flex items-center justify-center z-10 rounded-lg"
    role="status"
    aria-live="polite"
  >
    <div class="text-center">
      <LoadingSpinner size="lg" class="text-primary-500 mx-auto mb-2" />
      <p v-if="message" class="text-gray-600 text-sm">{{ message }}</p>
    </div>
  </div>

  <!-- Inline Loading -->
  <div
    v-else-if="variant === 'inline'"
    class="flex items-center justify-center py-8"
    role="status"
    aria-live="polite"
  >
    <LoadingSpinner size="md" class="text-primary-500 mr-3" />
    <span class="text-gray-600">{{ message }}</span>
  </div>

  <!-- Skeleton Loading -->
  <div
    v-else-if="variant === 'skeleton'"
    class="animate-pulse space-y-4"
    role="status"
    aria-live="polite"
    aria-label="Carregando conteúdo"
  >
    <div class="h-4 bg-gray-200 rounded w-3/4"></div>
    <div class="h-4 bg-gray-200 rounded"></div>
    <div class="h-4 bg-gray-200 rounded w-5/6"></div>
  </div>
</template>
