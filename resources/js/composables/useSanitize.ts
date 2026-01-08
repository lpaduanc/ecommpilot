/**
 * Composable para sanitização de HTML em componentes Vue
 *
 * Fornece computed properties reativos para sanitização de conteúdo HTML,
 * protegendo contra ataques XSS
 *
 * @example
 * ```vue
 * <script setup>
 * import { ref, computed } from 'vue';
 * import { useSanitize } from '@/composables/useSanitize';
 *
 * const props = defineProps<{ message: { content: string } }>();
 *
 * const messageContent = computed(() => props.message.content);
 * const { sanitized, stripped } = useSanitize(messageContent);
 * </script>
 *
 * <template>
 *   <!-- Renderiza HTML sanitizado -->
 *   <div v-html="sanitized"></div>
 *
 *   <!-- Renderiza apenas texto -->
 *   <p>{{ stripped }}</p>
 * </template>
 * ```
 */

import { computed, type Ref, type ComputedRef } from 'vue';
import { sanitizeHtml, stripHtml, sanitizeBasicFormatting } from '@/utils/sanitize';
import type DOMPurify from 'dompurify';

/**
 * Interface de retorno do composable useSanitize
 */
interface UseSanitizeReturn {
  /**
   * HTML sanitizado (seguro para v-html)
   * Remove scripts e elementos maliciosos mantendo formatação permitida
   */
  sanitized: ComputedRef<string>;

  /**
   * Texto puro sem nenhuma tag HTML
   * Útil para exibição em contextos de texto puro
   */
  stripped: ComputedRef<string>;

  /**
   * HTML com formatação básica apenas (b, i, em, strong, a, br)
   * Mais restritivo que sanitized
   */
  basic: ComputedRef<string>;
}

/**
 * Composable que fornece versões sanitizadas reativas de um input HTML
 *
 * @param input - Ref contendo string HTML a ser sanitizada
 * @param config - Configuração customizada do DOMPurify (opcional)
 * @returns Objeto com computed properties sanitizadas
 *
 * @example
 * ```typescript
 * // Uso básico
 * const content = ref('<p>Texto <b>negrito</b></p><script>alert("XSS")</script>');
 * const { sanitized, stripped } = useSanitize(content);
 *
 * console.log(sanitized.value); // '<p>Texto <b>negrito</b></p>'
 * console.log(stripped.value);  // 'Texto negrito'
 *
 * // Com configuração customizada
 * const { sanitized } = useSanitize(content, {
 *   ALLOWED_TAGS: ['p', 'b'],
 *   ALLOWED_ATTR: []
 * });
 * ```
 */
export function useSanitize(
  input: Ref<string>,
  config?: DOMPurify.Config
): UseSanitizeReturn {
  /**
   * Computed property com HTML sanitizado
   * Permite tags de formatação comuns mas remove scripts e elementos perigosos
   */
  const sanitized = computed(() => {
    if (!input.value) return '';
    return sanitizeHtml(input.value, config);
  });

  /**
   * Computed property com texto puro (sem HTML)
   * Remove todas as tags HTML, retornando apenas o conteúdo textual
   */
  const stripped = computed(() => {
    if (!input.value) return '';
    return stripHtml(input.value);
  });

  /**
   * Computed property com formatação básica apenas
   * Permite apenas tags de formatação de texto simples
   */
  const basic = computed(() => {
    if (!input.value) return '';
    return sanitizeBasicFormatting(input.value);
  });

  return {
    sanitized,
    stripped,
    basic,
  };
}

/**
 * Variante do composable para trabalhar com arrays de strings
 *
 * @param inputs - Ref contendo array de strings HTML
 * @param config - Configuração customizada do DOMPurify (opcional)
 * @returns Objeto com computed properties de arrays sanitizados
 *
 * @example
 * ```typescript
 * const messages = ref([
 *   '<p>Mensagem 1</p>',
 *   '<p>Mensagem 2 <script>alert(1)</script></p>'
 * ]);
 *
 * const { sanitized, stripped } = useSanitizeArray(messages);
 *
 * console.log(sanitized.value);
 * // ['<p>Mensagem 1</p>', '<p>Mensagem 2 </p>']
 *
 * console.log(stripped.value);
 * // ['Mensagem 1', 'Mensagem 2 ']
 * ```
 */
export function useSanitizeArray(
  inputs: Ref<string[]>,
  config?: DOMPurify.Config
) {
  const sanitized = computed(() => {
    if (!inputs.value || inputs.value.length === 0) return [];
    return inputs.value.map(input => sanitizeHtml(input, config));
  });

  const stripped = computed(() => {
    if (!inputs.value || inputs.value.length === 0) return [];
    return inputs.value.map(input => stripHtml(input));
  });

  const basic = computed(() => {
    if (!inputs.value || inputs.value.length === 0) return [];
    return inputs.value.map(input => sanitizeBasicFormatting(input));
  });

  return {
    sanitized,
    stripped,
    basic,
  };
}
