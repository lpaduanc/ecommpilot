<script setup lang="ts">
interface Props {
  columns?: number;
}

const props = withDefaults(defineProps<Props>(), {
  columns: 4,
});

// Gera array de colunas para renderização
const columnArray = Array.from({ length: props.columns }, (_, i) => i);

// Larguras variadas para tornar mais realista
const getColumnWidth = (index: number): string => {
  const widths = ['w-1/4', 'w-1/3', 'w-1/2', 'w-2/3', 'w-3/4'];
  return widths[index % widths.length];
};
</script>

<template>
  <tr
    class="animate-pulse border-b border-gray-100"
    role="status"
    aria-live="polite"
    aria-label="Carregando linha da tabela"
  >
    <td
      v-for="(_, index) in columnArray"
      :key="index"
      class="px-6 py-4"
    >
      <div
        class="h-4 bg-gray-200 rounded"
        :class="getColumnWidth(index)"
      ></div>
    </td>
  </tr>
</template>
