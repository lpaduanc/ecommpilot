<script setup lang="ts">
import { ref, onErrorCaptured } from 'vue';
import { XCircleIcon, ArrowPathIcon } from '@heroicons/vue/24/outline';

/**
 * ErrorBoundary Component
 *
 * Captura erros em componentes filhos e exibe uma UI de fallback amigável
 * ao invés de quebrar toda a aplicação.
 *
 * @example
 * <ErrorBoundary>
 *   <SomeComponentThatMightFail />
 * </ErrorBoundary>
 */

interface Props {
  /**
   * Se deve mostrar a UI de fallback quando ocorrer um erro
   * @default true
   */
  fallback?: boolean;

  /**
   * Callback opcional chamado quando um erro é capturado
   */
  onError?: (error: Error, errorInfo: string) => void;
}

const props = withDefaults(defineProps<Props>(), {
  fallback: true,
});

/**
 * Estado do erro capturado
 */
const error = ref<Error | null>(null);

/**
 * Informações sobre a origem do erro
 */
const errorInfo = ref<string>('');

/**
 * Hook do Vue 3 que captura erros de componentes descendentes
 *
 * @param err - O erro capturado
 * @param instance - Instância do componente que causou o erro
 * @param info - String identificando a origem do erro (ex: "render function", "event handler")
 * @returns false para prevenir propagação do erro
 */
onErrorCaptured((err: unknown, instance, info: string) => {
  const errorObject = err instanceof Error ? err : new Error(String(err));

  error.value = errorObject;
  errorInfo.value = info;

  // Log para desenvolvimento e monitoramento
  console.error('[ErrorBoundary] Erro capturado:', {
    error: errorObject,
    info,
    component: instance?.$options.name || 'Unknown Component',
    stack: errorObject.stack,
  });

  // Chamar callback customizado se fornecido
  if (props.onError) {
    try {
      props.onError(errorObject, info);
    } catch (callbackError) {
      console.error('[ErrorBoundary] Erro no callback onError:', callbackError);
    }
  }

  // Retornar false previne propagação do erro para outros errorCaptured hooks
  // e para app.config.errorHandler
  return false;
});

/**
 * Reseta o estado de erro, permitindo que o usuário tente novamente
 */
const reset = (): void => {
  error.value = null;
  errorInfo.value = '';
};
</script>

<template>
  <!-- UI de Fallback quando há erro -->
  <div
    v-if="error && fallback"
    class="min-h-[400px] flex items-center justify-center p-4"
    role="alert"
    aria-live="assertive"
  >
    <div class="max-w-md w-full bg-white dark:bg-gray-800 rounded-2xl shadow-lg p-8 text-center border border-gray-100">
      <!-- Ícone de Erro -->
      <div
        class="w-16 h-16 rounded-full bg-red-100 flex items-center justify-center mx-auto mb-4"
        aria-hidden="true"
      >
        <XCircleIcon class="w-8 h-8 text-red-600" />
      </div>

      <!-- Título -->
      <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-2">
        Ops! Algo deu errado
      </h3>

      <!-- Descrição -->
      <p class="text-gray-600 dark:text-gray-400 mb-6">
        Ocorreu um erro inesperado. Por favor, tente novamente.
      </p>

      <!-- Detalhes Técnicos (colapsável) -->
      <details class="text-left mb-6 bg-gray-50 dark:bg-gray-900 rounded-lg overflow-hidden">
        <summary
          class="cursor-pointer text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 px-4 py-2 font-medium transition-colors"
          role="button"
          aria-expanded="false"
        >
          Detalhes técnicos
        </summary>
        <div class="px-4 pb-4 pt-2">
          <div class="mb-3">
            <p class="text-xs font-semibold text-gray-700 mb-1">Mensagem:</p>
            <pre class="p-3 bg-white dark:bg-gray-800 rounded border border-gray-200 dark:border-gray-700 text-xs text-red-600 overflow-auto">{{ error.message }}</pre>
          </div>
          <div v-if="errorInfo">
            <p class="text-xs font-semibold text-gray-700 mb-1">Origem:</p>
            <p class="text-xs text-gray-600 dark:text-gray-400 bg-white dark:bg-gray-800 p-2 rounded border border-gray-200">{{ errorInfo }}</p>
          </div>
        </div>
      </details>

      <!-- Botão de Retry -->
      <button
        type="button"
        @click="reset"
        class="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-primary-600 to-primary-700 text-white rounded-lg hover:from-primary-700 hover:to-primary-800 transition-all duration-200 shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
        aria-label="Tentar novamente"
      >
        <ArrowPathIcon class="w-5 h-5" aria-hidden="true" />
        <span class="font-medium">Tentar Novamente</span>
      </button>
    </div>
  </div>

  <!-- Renderizar conteúdo normal se não houver erro -->
  <slot v-else />
</template>

<style scoped>
/**
 * Animação suave para o ícone de erro
 */
@keyframes shake {
  0%, 100% { transform: translateX(0); }
  25% { transform: translateX(-4px); }
  75% { transform: translateX(4px); }
}

.w-16.h-16 {
  animation: shake 0.5s ease-in-out;
}

/**
 * Estilo para o elemento details
 */
details summary::marker {
  content: '';
}

details summary::before {
  content: '▶';
  display: inline-block;
  margin-right: 0.5rem;
  transition: transform 0.2s;
}

details[open] summary::before {
  transform: rotate(90deg);
}

details summary::-webkit-details-marker {
  display: none;
}
</style>
