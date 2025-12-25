import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import api from '../services/api';

export const useAnalysisStore = defineStore('analysis', () => {
    const currentAnalysis = ref(null);
    const analysisHistory = ref([]);
    const isLoading = ref(false);
    const isRequesting = ref(false);
    const error = ref(null);
    const nextAvailableAt = ref(null);
    const credits = ref(0);
    
    const canRequestAnalysis = computed(() => {
        if (!nextAvailableAt.value) return true;
        return new Date() >= new Date(nextAvailableAt.value);
    });
    
    const timeUntilNextAnalysis = computed(() => {
        if (!nextAvailableAt.value || canRequestAnalysis.value) return null;
        
        const now = new Date();
        const next = new Date(nextAvailableAt.value);
        const diff = next - now;
        
        const minutes = Math.floor(diff / 60000);
        const seconds = Math.floor((diff % 60000) / 1000);
        
        return `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
    });
    
    const suggestions = computed(() => currentAnalysis.value?.suggestions || []);
    const alerts = computed(() => currentAnalysis.value?.alerts || []);
    const opportunities = computed(() => currentAnalysis.value?.opportunities || []);
    const summary = computed(() => currentAnalysis.value?.summary || null);
    
    const highPrioritySuggestions = computed(() => 
        suggestions.value.filter(s => s.priority === 'high')
    );
    
    const mediumPrioritySuggestions = computed(() => 
        suggestions.value.filter(s => s.priority === 'medium')
    );
    
    const lowPrioritySuggestions = computed(() => 
        suggestions.value.filter(s => s.priority === 'low')
    );
    
    async function fetchCurrentAnalysis() {
        isLoading.value = true;
        error.value = null;
        
        try {
            const response = await api.get('/analysis/current');
            currentAnalysis.value = response.data.analysis;
            nextAvailableAt.value = response.data.next_available_at;
            credits.value = response.data.credits;
        } catch (err) {
            error.value = err.response?.data?.message || 'Erro ao carregar análise';
        } finally {
            isLoading.value = false;
        }
    }
    
    async function fetchAnalysisHistory() {
        try {
            const response = await api.get('/analysis/history');
            analysisHistory.value = response.data;
        } catch {
            analysisHistory.value = [];
        }
    }
    
    async function requestNewAnalysis() {
        isRequesting.value = true;
        error.value = null;
        
        try {
            const response = await api.post('/analysis/request');
            currentAnalysis.value = response.data.analysis;
            nextAvailableAt.value = response.data.next_available_at;
            credits.value = response.data.credits;
            
            return { success: true };
        } catch (err) {
            const message = err.response?.data?.message || 'Erro ao solicitar análise';
            error.value = message;
            
            if (err.response?.status === 429) {
                nextAvailableAt.value = err.response.data.next_available_at;
            }
            
            return { success: false, message };
        } finally {
            isRequesting.value = false;
        }
    }
    
    async function getAnalysisById(id) {
        isLoading.value = true;
        
        try {
            const response = await api.get(`/analysis/${id}`);
            return response.data;
        } catch {
            return null;
        } finally {
            isLoading.value = false;
        }
    }
    
    async function markSuggestionAsDone(analysisId, suggestionId) {
        try {
            await api.post(`/analysis/${analysisId}/suggestions/${suggestionId}/done`);
            
            // Update local state
            if (currentAnalysis.value?.id === analysisId) {
                const suggestion = currentAnalysis.value.suggestions.find(
                    s => s.id === suggestionId
                );
                if (suggestion) {
                    suggestion.is_done = true;
                }
            }
            
            return { success: true };
        } catch {
            return { success: false };
        }
    }
    
    function getSuggestionsByCategory(category) {
        return suggestions.value.filter(s => s.category === category);
    }
    
    return {
        currentAnalysis,
        analysisHistory,
        isLoading,
        isRequesting,
        error,
        nextAvailableAt,
        credits,
        canRequestAnalysis,
        timeUntilNextAnalysis,
        suggestions,
        alerts,
        opportunities,
        summary,
        highPrioritySuggestions,
        mediumPrioritySuggestions,
        lowPrioritySuggestions,
        fetchCurrentAnalysis,
        fetchAnalysisHistory,
        requestNewAnalysis,
        getAnalysisById,
        markSuggestionAsDone,
        getSuggestionsByCategory,
    };
});

