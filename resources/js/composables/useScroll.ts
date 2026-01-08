/**
 * Composable para controle de scroll
 *
 * Fornece funções utilitárias para manipular scroll de elementos e da página
 *
 * @example
 * ```vue
 * <script setup>
 * import { ref } from 'vue';
 * import { useScroll } from '@/composables/useScroll';
 *
 * const chatContainer = ref<HTMLElement | null>(null);
 * const { scrollToBottom, scrollToTop } = useScroll();
 *
 * // Rolar para o final do chat quando nova mensagem chegar
 * watch(messages, () => {
 *   scrollToBottom(chatContainer);
 * });
 * </script>
 *
 * <template>
 *   <div ref="chatContainer" class="chat-messages">
 *     <!-- mensagens -->
 *   </div>
 *   <button @click="scrollToTop">Voltar ao topo</button>
 * </template>
 * ```
 */

import { nextTick, type Ref } from 'vue';

export function useScroll() {
  /**
   * Rola um elemento até o final (scroll para baixo)
   *
   * Utiliza nextTick para garantir que o scroll aconteça após
   * a atualização do DOM
   *
   * @param elementRef - Ref do elemento a ser rolado
   *
   * @example
   * const chatContainer = ref<HTMLElement | null>(null);
   * scrollToBottom(chatContainer);
   */
  const scrollToBottom = (elementRef: Ref<HTMLElement | null>): void => {
    nextTick(() => {
      if (elementRef.value) {
        elementRef.value.scrollTop = elementRef.value.scrollHeight;
      }
    });
  };

  /**
   * Rola a página até o topo com animação suave
   *
   * Utiliza scroll behavior smooth para uma transição suave
   *
   * @example
   * scrollToTop(); // Rola a página para o topo
   */
  const scrollToTop = (): void => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  /**
   * Rola um elemento até um offset específico com animação suave
   *
   * @param elementRef - Ref do elemento a ser rolado
   * @param offset - Posição do scroll em pixels
   *
   * @example
   * const container = ref<HTMLElement | null>(null);
   * scrollToPosition(container, 500); // Rola para 500px
   */
  const scrollToPosition = (
    elementRef: Ref<HTMLElement | null>,
    offset: number
  ): void => {
    nextTick(() => {
      if (elementRef.value) {
        elementRef.value.scrollTo({ top: offset, behavior: 'smooth' });
      }
    });
  };

  /**
   * Rola a página até um elemento específico
   *
   * @param elementId - ID do elemento para rolar até ele
   * @param offsetTop - Offset adicional em pixels (padrão: 0)
   *
   * @example
   * scrollToElement('main-content', -80); // Rola até #main-content com -80px offset
   */
  const scrollToElement = (elementId: string, offsetTop: number = 0): void => {
    const element = document.getElementById(elementId);
    if (element) {
      const elementPosition = element.getBoundingClientRect().top + window.pageYOffset;
      const offsetPosition = elementPosition + offsetTop;

      window.scrollTo({
        top: offsetPosition,
        behavior: 'smooth',
      });
    }
  };

  return {
    scrollToBottom,
    scrollToTop,
    scrollToPosition,
    scrollToElement,
  };
}
