/**
 * Preview Mode Composable
 *
 * Gerencia o estado de "preview mode" para páginas que requerem upgrade de plano.
 * Permite que usuários visualizem funcionalidades bloqueadas em modo desabilitado.
 */

import { ref, computed } from 'vue';

// Estado global do preview mode (compartilhado entre componentes)
const isPreviewMode = ref(false);
const previewFeature = ref<string | null>(null);

export function usePreviewMode() {
  /**
   * Ativa o modo preview para uma funcionalidade específica
   */
  function enablePreviewMode(featureName?: string) {
    isPreviewMode.value = true;
    previewFeature.value = featureName || null;
  }

  /**
   * Desativa o modo preview
   */
  function disablePreviewMode() {
    isPreviewMode.value = false;
    previewFeature.value = null;
  }

  /**
   * Alterna o modo preview
   */
  function togglePreviewMode(featureName?: string) {
    if (isPreviewMode.value) {
      disablePreviewMode();
    } else {
      enablePreviewMode(featureName);
    }
  }

  /**
   * Verifica se está em modo preview
   */
  const isInPreviewMode = computed(() => isPreviewMode.value);

  /**
   * Nome da funcionalidade em preview
   */
  const currentPreviewFeature = computed(() => previewFeature.value);

  return {
    isInPreviewMode,
    currentPreviewFeature,
    enablePreviewMode,
    disablePreviewMode,
    togglePreviewMode,
  };
}
