import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import api from '../services/api';

export const useChatStore = defineStore('chat', () => {
    const messages = ref([]);
    const isLoading = ref(false);
    const isSending = ref(false);
    const error = ref(null);
    const conversationId = ref(null);
    
    const hasMessages = computed(() => messages.value.length > 0);
    
    const lastMessage = computed(() => 
        messages.value.length > 0 
            ? messages.value[messages.value.length - 1] 
            : null
    );
    
    async function fetchConversation() {
        isLoading.value = true;
        error.value = null;
        
        try {
            const response = await api.get('/chat/conversation');
            messages.value = response.data.messages || [];
            conversationId.value = response.data.conversation_id;
        } catch (err) {
            error.value = err.response?.data?.message || 'Erro ao carregar conversa';
        } finally {
            isLoading.value = false;
        }
    }
    
    async function sendMessage(content, context = null) {
        isSending.value = true;
        error.value = null;
        
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
            
            if (context) {
                payload.context = context;
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
            // Remove failed user message
            messages.value.pop();
            
            const message = err.response?.data?.message || 'Erro ao enviar mensagem';
            error.value = message;
            
            return { success: false, message };
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
    
    return {
        messages,
        isLoading,
        isSending,
        error,
        conversationId,
        hasMessages,
        lastMessage,
        fetchConversation,
        sendMessage,
        clearConversation,
        addWelcomeMessage,
    };
});

