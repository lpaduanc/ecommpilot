import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import api from '../services/api';

export const useChatStore = defineStore('chat', () => {
    const messages = ref([]);
    const isLoading = ref(false);
    const isSending = ref(false);
    const error = ref(null);
    const conversationId = ref(null);
    const upgradeRequired = ref(false);
    const currentContext = ref(null);
    
    const hasMessages = computed(() => messages.value.length > 0);
    
    const lastMessage = computed(() => 
        messages.value.length > 0 
            ? messages.value[messages.value.length - 1] 
            : null
    );
    
    async function fetchConversation() {
        isLoading.value = true;
        error.value = null;
        currentContext.value = null; // General chat has no suggestion context

        try {
            const response = await api.get('/chat/conversation');
            messages.value = response.data.messages || [];
            conversationId.value = response.data.conversation_id;

            // Add welcome message if no messages exist
            if (messages.value.length === 0) {
                addWelcomeMessage();
            }
        } catch (err) {
            error.value = err.response?.data?.message || 'Erro ao carregar conversa';
            // Add welcome message even on error (new user)
            if (messages.value.length === 0) {
                addWelcomeMessage();
            }
        } finally {
            isLoading.value = false;
        }
    }
    
    async function sendMessage(content, context = null) {
        isSending.value = true;
        error.value = null;

        // Use stored context for suggestion chats if no explicit context passed
        const effectiveContext = context || currentContext.value;

        // Add user message immediately
        const userMessage = {
            id: Date.now(),
            role: 'user',
            content,
            created_at: new Date().toISOString(),
        };
        messages.value.push(userMessage);

        try {
            const payload = { message: content };

            if (effectiveContext) {
                payload.context = effectiveContext;
            }
            
            const response = await api.post('/chat/message', payload);
            
            // Update user message with server ID
            userMessage.id = response.data.user_message_id;
            
            // Add assistant message
            const assistantMessage = {
                id: response.data.assistant_message_id,
                role: 'assistant',
                content: response.data.response,
                created_at: new Date().toISOString(),
            };
            messages.value.push(assistantMessage);
            
            return { success: true, response: response.data.response };
        } catch (err) {
            // Check for upgrade required (403)
            if (err.response?.status === 403 && err.response?.data?.upgrade_required) {
                // Remove user message for upgrade errors
                messages.value.pop();
                upgradeRequired.value = true;
                error.value = err.response.data.message || 'Seu plano não inclui acesso ao Assistente IA.';
                return { success: false, message: error.value, upgradeRequired: true };
            }

            // For other errors, keep user message and show error as assistant response
            const errorContent = err.response?.data?.message
                || 'Desculpe, ocorreu um erro inesperado. Estamos trabalhando para resolver o mais rápido possível. Por favor, tente novamente em alguns minutos.';

            const errorAssistantMessage = {
                id: 'error-' + Date.now(),
                role: 'assistant',
                content: errorContent,
                created_at: new Date().toISOString(),
                isError: true,
            };
            messages.value.push(errorAssistantMessage);

            error.value = errorContent;

            return { success: false, message: errorContent };
        } finally {
            isSending.value = false;
        }
    }
    
    async function clearConversation() {
        isLoading.value = true;
        
        try {
            await api.delete('/chat/conversation');
            messages.value = [];
            conversationId.value = null;
            
            return { success: true };
        } catch {
            return { success: false };
        } finally {
            isLoading.value = false;
        }
    }
    
    function addWelcomeMessage() {
        if (messages.value.length === 0) {
            messages.value.push({
                id: 'welcome',
                role: 'assistant',
                content: 'Olá! 👋 Sou seu assistente de marketing e-commerce. Posso ajudar você a entender melhor as análises da sua loja, tirar dúvidas sobre estratégias de vendas e sugerir melhorias. Como posso ajudar?',
                created_at: new Date().toISOString(),
            });
        }
    }

    function resetLocalState() {
        messages.value = [];
        conversationId.value = null;
        currentContext.value = null;
        error.value = null;
        isLoading.value = false;
        isSending.value = false;
        upgradeRequired.value = false;
    }

    async function resetAndClearBackend() {
        // Limpa estado local imediatamente
        resetLocalState();

        // Tenta limpar no backend (sem bloquear)
        try {
            await api.delete('/chat/conversation');
        } catch {
            // Ignora erros - o importante é o estado local estar limpo
        }
    }

    /**
     * Start a suggestion discussion with AI.
     * @param {Object} suggestion - The suggestion object from analysis
     * @returns {Promise<Object>} - Result of the operation
     */
    async function startSuggestionDiscussion(suggestion) {
        // Reset local state for fresh conversation
        resetLocalState();

        // Build context object and store it for follow-up messages
        const context = {
            type: 'suggestion',
            suggestion: {
                id: suggestion.id,
                title: suggestion.title,
                category: suggestion.category,
                description: suggestion.description,
                recommended_action: suggestion.recommended_action || suggestion.action_steps,
                expected_impact: suggestion.expected_impact || suggestion.priority,
                priority: suggestion.priority,
            }
        };
        currentContext.value = context;

        // Send initial message to trigger AI response with 5 suggestions
        const initialMessage = `Quero discutir esta sugestão: "${suggestion.title}"`;

        return sendMessage(initialMessage, context);
    }

    /**
     * Load existing conversation for a suggestion or start a new one.
     * @param {Object} suggestion - The suggestion object from analysis
     * @returns {Promise<Object>} - Result with success, hasHistory, and optional message
     */
    async function loadSuggestionConversation(suggestion) {
        isLoading.value = true;
        error.value = null;

        // Build and store context so follow-up messages keep the suggestion association
        const context = {
            type: 'suggestion',
            suggestion: {
                id: suggestion.id,
                title: suggestion.title,
                category: suggestion.category,
                description: suggestion.description,
                recommended_action: suggestion.recommended_action || suggestion.action_steps,
                expected_impact: suggestion.expected_impact || suggestion.priority,
                priority: suggestion.priority,
            }
        };
        currentContext.value = context;

        try {
            // Try to fetch existing conversation for this suggestion
            const response = await api.get(`/chat/conversation/suggestion/${suggestion.id}`);

            if (response.data.exists) {
                // Load existing conversation
                messages.value = response.data.messages || [];
                conversationId.value = response.data.conversation_id;
                return { success: true, hasHistory: true };
            } else {
                // No existing conversation - start a new one
                messages.value = [];
                conversationId.value = null;
                error.value = null;

                // Send initial message to trigger AI response
                const initialMessage = `Quero discutir esta sugestão: "${suggestion.title}"`;
                const result = await sendMessage(initialMessage, context);

                return { success: result.success, hasHistory: false, message: result.message };
            }
        } catch (err) {
            error.value = err.response?.data?.message || 'Erro ao carregar conversa';
            return { success: false, hasHistory: false, message: error.value };
        } finally {
            isLoading.value = false;
        }
    }

    return {
        messages,
        isLoading,
        isSending,
        error,
        conversationId,
        currentContext,
        upgradeRequired,
        hasMessages,
        lastMessage,
        fetchConversation,
        sendMessage,
        clearConversation,
        addWelcomeMessage,
        resetLocalState,
        resetAndClearBackend,
        startSuggestionDiscussion,
        loadSuggestionConversation,
    };
});

