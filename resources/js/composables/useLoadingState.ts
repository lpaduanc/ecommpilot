import { ref } from 'vue';
import { logger } from '../utils/logger';

/**
 * Composable para gerenciar estados de loading e erro
 *
 * @example
 * ```ts
 * const { isLoading, error, execute } = useLoadingState();
 *
 * const loadData = () => execute(async () => {
 *   const response = await api.get('/data');
 *   return response.data;
 * });
 * ```
 */
export function useLoadingState() {
  const isLoading = ref(false);
  const error = ref<string | null>(null);

  /**
   * Executa uma função assíncrona gerenciando estados de loading e erro
   *
   * @param fn - Função assíncrona a ser executada
   * @returns Promise com o resultado da função
   */
  async function execute<T>(fn: () => Promise<T>): Promise<T | undefined> {
    isLoading.value = true;
    error.value = null;

    try {
      const result = await fn();
      return result;
    } catch (err: any) {
      // Captura mensagem de erro do response ou usa mensagem genérica
      error.value = err.response?.data?.message || err.message || 'Ocorreu um erro inesperado';

      // Log do erro para debugging (apenas em desenvolvimento)
      if (import.meta.env.DEV) {
        logger.error('[useLoadingState] Error:', err);
      }

      // Retorna undefined em caso de erro
      return undefined;
    } finally {
      isLoading.value = false;
    }
  }

  /**
   * Reseta o estado de erro
   */
  function clearError() {
    error.value = null;
  }

  return {
    isLoading,
    error,
    execute,
    clearError,
  };
}
