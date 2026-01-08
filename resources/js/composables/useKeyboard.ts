import { ref, onMounted, onUnmounted, type Ref } from 'vue';

/**
 * Composable para navegação por teclado em listas
 *
 * @param items - Ref contendo array de itens para navegação
 * @param onSelect - Callback executado quando um item é selecionado (Enter/Space)
 * @param options - Opções de configuração
 * @returns currentIndex - Índice atual selecionado
 *
 * @example
 * const products = ref([...]);
 * const { currentIndex } = useKeyboardNavigation(
 *   products,
 *   (product) => console.log('Selected:', product),
 *   { loop: true }
 * );
 */
export function useKeyboardNavigation<T>(
  items: Ref<T[]>,
  onSelect: (item: T, index: number) => void,
  options: {
    loop?: boolean;
    disabled?: Ref<boolean>;
    initialIndex?: number;
  } = {}
) {
  const {
    loop = false,
    disabled = ref(false),
    initialIndex = 0,
  } = options;

  const currentIndex = ref(initialIndex);

  /**
   * Move o índice para o próximo item
   */
  const moveNext = () => {
    if (items.value.length === 0) return;

    if (currentIndex.value < items.value.length - 1) {
      currentIndex.value++;
    } else if (loop) {
      currentIndex.value = 0;
    }
  };

  /**
   * Move o índice para o item anterior
   */
  const movePrevious = () => {
    if (items.value.length === 0) return;

    if (currentIndex.value > 0) {
      currentIndex.value--;
    } else if (loop) {
      currentIndex.value = items.value.length - 1;
    }
  };

  /**
   * Move o índice para o primeiro item
   */
  const moveFirst = () => {
    if (items.value.length === 0) return;
    currentIndex.value = 0;
  };

  /**
   * Move o índice para o último item
   */
  const moveLast = () => {
    if (items.value.length === 0) return;
    currentIndex.value = items.value.length - 1;
  };

  /**
   * Seleciona o item atual
   */
  const selectCurrent = () => {
    if (items.value.length === 0) return;

    const item = items.value[currentIndex.value];
    if (item) {
      onSelect(item, currentIndex.value);
    }
  };

  /**
   * Handler de eventos de teclado
   */
  const handleKeyDown = (event: KeyboardEvent) => {
    if (disabled.value) return;

    // Apenas processar se não estiver em um input/textarea
    const target = event.target as HTMLElement;
    if (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA') {
      return;
    }

    switch (event.key) {
      case 'ArrowDown':
        event.preventDefault();
        moveNext();
        break;

      case 'ArrowUp':
        event.preventDefault();
        movePrevious();
        break;

      case 'Enter':
        event.preventDefault();
        selectCurrent();
        break;

      case ' ':
      case 'Space':
        event.preventDefault();
        selectCurrent();
        break;

      case 'Home':
        event.preventDefault();
        moveFirst();
        break;

      case 'End':
        event.preventDefault();
        moveLast();
        break;

      default:
        // Não fazer nada para outras teclas
        break;
    }
  };

  onMounted(() => {
    window.addEventListener('keydown', handleKeyDown);
  });

  onUnmounted(() => {
    window.removeEventListener('keydown', handleKeyDown);
  });

  return {
    currentIndex,
    moveNext,
    movePrevious,
    moveFirst,
    moveLast,
    selectCurrent,
  };
}

/**
 * Composable para trap de foco dentro de um elemento (útil para modals)
 *
 * @param containerRef - Ref do elemento container
 * @param options - Opções de configuração
 *
 * @example
 * const modalRef = ref<HTMLElement | null>(null);
 * const { activate, deactivate } = useFocusTrap(modalRef);
 *
 * onMounted(() => activate());
 * onUnmounted(() => deactivate());
 */
export function useFocusTrap(
  containerRef: Ref<HTMLElement | null>,
  options: {
    initialFocus?: Ref<HTMLElement | null>;
    returnFocus?: boolean;
  } = {}
) {
  const { returnFocus = true } = options;
  let previouslyFocusedElement: HTMLElement | null = null;

  /**
   * Obtém todos os elementos focáveis dentro do container
   */
  const getFocusableElements = (): HTMLElement[] => {
    if (!containerRef.value) return [];

    const selector = [
      'a[href]',
      'button:not([disabled])',
      'textarea:not([disabled])',
      'input:not([disabled])',
      'select:not([disabled])',
      '[tabindex]:not([tabindex="-1"])',
    ].join(', ');

    return Array.from(containerRef.value.querySelectorAll(selector));
  };

  /**
   * Handler de Tab para manter foco dentro do container
   */
  const handleTab = (event: KeyboardEvent) => {
    if (event.key !== 'Tab') return;
    if (!containerRef.value) return;

    const focusableElements = getFocusableElements();
    if (focusableElements.length === 0) return;

    const firstElement = focusableElements[0];
    const lastElement = focusableElements[focusableElements.length - 1];

    if (event.shiftKey) {
      // Shift + Tab
      if (document.activeElement === firstElement) {
        event.preventDefault();
        lastElement?.focus();
      }
    } else {
      // Tab
      if (document.activeElement === lastElement) {
        event.preventDefault();
        firstElement?.focus();
      }
    }
  };

  /**
   * Ativa o focus trap
   */
  const activate = () => {
    // Salvar elemento focado anteriormente
    previouslyFocusedElement = document.activeElement as HTMLElement;

    // Focar no container ou no initialFocus
    if (options.initialFocus?.value) {
      options.initialFocus.value.focus();
    } else {
      const focusableElements = getFocusableElements();
      if (focusableElements.length > 0) {
        focusableElements[0].focus();
      }
    }

    // Adicionar event listener
    document.addEventListener('keydown', handleTab);
  };

  /**
   * Desativa o focus trap
   */
  const deactivate = () => {
    document.removeEventListener('keydown', handleTab);

    // Retornar foco para elemento anterior
    if (returnFocus && previouslyFocusedElement) {
      previouslyFocusedElement.focus();
    }
  };

  return {
    activate,
    deactivate,
  };
}

/**
 * Composable para gerenciar atalhos de teclado
 *
 * @example
 * const { registerShortcut, unregisterShortcut } = useKeyboardShortcuts();
 *
 * onMounted(() => {
 *   registerShortcut('ctrl+k', () => console.log('Search opened'));
 *   registerShortcut('esc', () => console.log('Modal closed'));
 * });
 */
export function useKeyboardShortcuts() {
  const shortcuts = new Map<string, () => void>();

  /**
   * Normaliza a tecla pressionada para comparação
   */
  const normalizeKey = (event: KeyboardEvent): string => {
    const parts: string[] = [];

    if (event.ctrlKey) parts.push('ctrl');
    if (event.altKey) parts.push('alt');
    if (event.shiftKey) parts.push('shift');
    if (event.metaKey) parts.push('meta');

    const key = event.key.toLowerCase();
    if (!['control', 'alt', 'shift', 'meta'].includes(key)) {
      parts.push(key);
    }

    return parts.join('+');
  };

  /**
   * Handler de eventos de teclado
   */
  const handleKeyDown = (event: KeyboardEvent) => {
    const key = normalizeKey(event);
    const handler = shortcuts.get(key);

    if (handler) {
      event.preventDefault();
      handler();
    }
  };

  /**
   * Registra um novo atalho
   */
  const registerShortcut = (key: string, handler: () => void) => {
    shortcuts.set(key.toLowerCase(), handler);
  };

  /**
   * Remove um atalho
   */
  const unregisterShortcut = (key: string) => {
    shortcuts.delete(key.toLowerCase());
  };

  /**
   * Remove todos os atalhos
   */
  const clearShortcuts = () => {
    shortcuts.clear();
  };

  onMounted(() => {
    document.addEventListener('keydown', handleKeyDown);
  });

  onUnmounted(() => {
    document.removeEventListener('keydown', handleKeyDown);
    clearShortcuts();
  });

  return {
    registerShortcut,
    unregisterShortcut,
    clearShortcuts,
  };
}
