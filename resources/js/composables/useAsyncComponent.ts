import { defineAsyncComponent, Component } from 'vue';
import LoadingSpinner from '@/components/common/LoadingSpinner.vue';

/**
 * Configuration options for async component loading
 */
export interface AsyncComponentOptions {
    /**
     * Delay in milliseconds before showing loading component
     * @default 200
     */
    delay?: number;

    /**
     * Timeout in milliseconds before showing error component
     * @default 10000
     */
    timeout?: number;

    /**
     * Custom loading component to show while async component is loading
     * @default LoadingSpinner
     */
    loadingComponent?: Component;

    /**
     * Custom error component to show if loading fails
     * @default null
     */
    errorComponent?: Component;

    /**
     * Custom error handler callback
     */
    onError?: (error: Error, retry: () => void, fail: () => void, attempts: number) => void;
}

/**
 * Default error component for async component loading failures
 */
const DefaultErrorComponent = {
    template: `
        <div class="flex flex-col items-center justify-center p-8 text-center">
            <div class="w-16 h-16 rounded-full bg-danger-100 flex items-center justify-center mb-4">
                <svg class="w-8 h-8 text-danger-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <h3 class="text-lg font-semibold text-gray-900 mb-2">
                Falha ao Carregar Componente
            </h3>
            <p class="text-gray-600 mb-4">
                Ocorreu um erro ao carregar este recurso. Por favor, tente novamente.
            </p>
            <button
                @click="$emit('retry')"
                class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors"
            >
                Tentar Novamente
            </button>
        </div>
    `,
};

/**
 * Composable for creating standardized async components with loading states
 *
 * @example
 * ```typescript
 * // Basic usage
 * const AsyncChart = useAsyncComponent(() => import('./Chart.vue'));
 *
 * // With custom options
 * const AsyncChart = useAsyncComponent(
 *   () => import('./Chart.vue'),
 *   {
 *     delay: 300,
 *     timeout: 15000,
 *   }
 * );
 * ```
 *
 * @param loader - Function that returns a Promise resolving to the component
 * @param options - Configuration options for loading behavior
 * @returns Async component with configured loading states
 */
export function useAsyncComponent(
    loader: () => Promise<Component>,
    options: AsyncComponentOptions = {}
) {
    const {
        delay = 200,
        timeout = 10000,
        loadingComponent = LoadingSpinner,
        errorComponent = DefaultErrorComponent,
        onError,
    } = options;

    return defineAsyncComponent({
        loader,
        loadingComponent,
        errorComponent,
        delay,
        timeout,
        onError: onError || ((error, retry, fail, attempts) => {
            // Log error to console in development
            if (import.meta.env.DEV) {
                console.error('[useAsyncComponent] Failed to load component:', error);
                console.log(`Attempts: ${attempts}`);
            }

            // Retry up to 3 times with exponential backoff
            if (attempts < 3) {
                const retryDelay = Math.min(1000 * Math.pow(2, attempts), 5000);
                console.log(`[useAsyncComponent] Retrying in ${retryDelay}ms...`);

                setTimeout(() => {
                    retry();
                }, retryDelay);
            } else {
                // After 3 attempts, show error component
                console.error('[useAsyncComponent] Max retry attempts reached. Showing error component.');
                fail();
            }
        }),
    });
}

/**
 * Creates an async component specifically for heavy chart libraries
 * with longer timeout and custom loading message
 *
 * @example
 * ```typescript
 * const AsyncApexChart = useAsyncChartComponent(() => import('vue3-apexcharts'));
 * ```
 */
export function useAsyncChartComponent(loader: () => Promise<Component>) {
    return useAsyncComponent(loader, {
        delay: 300,
        timeout: 15000, // Charts can take longer to load
        loadingComponent: {
            template: `
                <div class="h-[300px] flex items-center justify-center">
                    <div class="text-center">
                        <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600 mb-3"></div>
                        <p class="text-gray-600 text-sm">Carregando gráfico...</p>
                    </div>
                </div>
            `,
        },
    });
}

/**
 * Creates an async component for modals with custom loading behavior
 *
 * @example
 * ```typescript
 * const AsyncModal = useAsyncModalComponent(() => import('./MyModal.vue'));
 * ```
 */
export function useAsyncModalComponent(loader: () => Promise<Component>) {
    return useAsyncComponent(loader, {
        delay: 100, // Show loading faster for modals
        timeout: 8000,
        loadingComponent: {
            template: `
                <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
                    <div class="bg-white rounded-2xl p-8 shadow-2xl">
                        <div class="flex items-center gap-4">
                            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
                            <span class="text-gray-700">Carregando...</span>
                        </div>
                    </div>
                </div>
            `,
        },
    });
}

/**
 * Creates an async component for admin pages with permission-aware error handling
 *
 * @example
 * ```typescript
 * const AsyncAdminPanel = useAsyncAdminComponent(() => import('./AdminPanel.vue'));
 * ```
 */
export function useAsyncAdminComponent(loader: () => Promise<Component>) {
    return useAsyncComponent(loader, {
        delay: 250,
        timeout: 12000,
        errorComponent: {
            template: `
                <div class="flex flex-col items-center justify-center p-12 text-center">
                    <div class="w-20 h-20 rounded-full bg-warning-100 flex items-center justify-center mb-4">
                        <svg class="w-10 h-10 text-warning-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">
                        Acesso Restrito
                    </h3>
                    <p class="text-gray-600 max-w-md">
                        Não foi possível carregar este módulo administrativo. Verifique suas permissões ou tente novamente.
                    </p>
                </div>
            `,
        },
    });
}
