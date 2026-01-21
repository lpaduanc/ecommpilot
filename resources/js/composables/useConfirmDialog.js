import { ref, reactive } from 'vue';

// Estado global compartilhado entre componentes
const isOpen = ref(false);
const resolveCallback = ref(null);

const dialogState = reactive({
    title: '',
    message: '',
    confirmText: 'Confirmar',
    cancelText: 'Cancelar',
    variant: 'primary', // 'primary' | 'danger' | 'warning'
});

export function useConfirmDialog() {
    /**
     * Abre o modal de confirmação e retorna uma Promise
     * @param {Object} options - Opções do diálogo
     * @param {string} options.title - Título do modal
     * @param {string} options.message - Mensagem de confirmação
     * @param {string} [options.confirmText='Confirmar'] - Texto do botão confirmar
     * @param {string} [options.cancelText='Cancelar'] - Texto do botão cancelar
     * @param {('primary'|'danger'|'warning')} [options.variant='primary'] - Variante do botão
     * @returns {Promise<boolean>} - Retorna true se confirmado, false se cancelado
     */
    function confirm(options) {
        return new Promise((resolve) => {
            dialogState.title = options.title || 'Confirmar ação';
            dialogState.message = options.message || 'Tem certeza que deseja continuar?';
            dialogState.confirmText = options.confirmText || 'Confirmar';
            dialogState.cancelText = options.cancelText || 'Cancelar';
            dialogState.variant = options.variant || 'primary';

            resolveCallback.value = resolve;
            isOpen.value = true;
        });
    }

    function handleConfirm() {
        if (resolveCallback.value) {
            resolveCallback.value(true);
        }
        close();
    }

    function handleCancel() {
        if (resolveCallback.value) {
            resolveCallback.value(false);
        }
        close();
    }

    function close() {
        isOpen.value = false;
        resolveCallback.value = null;
    }

    return {
        confirm,
        handleConfirm,
        handleCancel,
        close,
        isOpen,
        dialogState,
    };
}
