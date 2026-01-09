<script setup lang="ts">
// Product Card Skeleton Loader
// Skeleton loader específico para cards de produto
</script>

<template>
  <div
    class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-100 dark:border-gray-700 animate-pulse"
    role="status"
    aria-live="polite"
    aria-label="Carregando produto"
  >
    <!-- Image Placeholder -->
    <div class="w-full h-48 bg-gray-200 rounded-lg mb-4"></div>

    <!-- Title Placeholder -->
    <div class="h-4 bg-gray-200 rounded w-3/4 mb-2"></div>

    <!-- Price Placeholder -->
    <div class="h-4 bg-gray-200 rounded w-1/2 mb-3"></div>

    <!-- Additional Info Placeholder -->
    <div class="h-3 bg-gray-200 rounded w-2/3"></div>
  </div>
</template>
