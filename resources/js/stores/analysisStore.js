import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import api from '../services/api';

export const useAnalysisStore = defineStore('analysis', () => {
    const currentAnalysis = ref(null);
    const pendingAnalysis = ref(null);
    const analysisHistory = ref([]);
    const isLoading = ref(false);
    const isRequesting = ref(false);
    const error = ref(null);
    const nextAvailableAt = ref(null);
    const credits = ref(0);
    const pollingInterval = ref(null);
    
    const hasAnalysisInProgress = computed(() => {
        return pendingAnalysis.value !== null;
    });

    const canRequestAnalysis = computed(() => {
        // Can't request if there's already one in progress
        if (hasAnalysisInProgress.value) return false;
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
            pendingAnalysis.value = response.data.pending_analysis;
            nextAvailableAt.value = response.data.next_available_at;
            credits.value = response.data.credits;

            // Start polling if there's a pending analysis
            if (pendingAnalysis.value) {
                startPolling();
            } else {
                stopPolling();
            }
        } catch (err) {
            error.value = err.response?.data?.message || 'Erro ao carregar análise';
        } finally {
            isLoading.value = false;
        }
    }

    function startPolling() {
        if (pollingInterval.value) return;

        pollingInterval.value = setInterval(async () => {
            try {
                const response = await api.get('/analysis/current');

                // Check if analysis completed
                if (!response.data.pending_analysis && pendingAnalysis.value) {
                    // Analysis completed!
                    currentAnalysis.value = response.data.analysis;
                    pendingAnalysis.value = null;
                    nextAvailableAt.value = response.data.next_available_at;
                    credits.value = response.data.credits;
                    stopPolling();
                } else {
                    pendingAnalysis.value = response.data.pending_analysis;
                }
            } catch {
                // Silently ignore polling errors
            }
        }, 5000); // Poll every 5 seconds
    }

    function stopPolling() {
        if (pollingInterval.value) {
            clearInterval(pollingInterval.value);
            pollingInterval.value = null;
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
            pendingAnalysis.value = response.data.pending_analysis;
            credits.value = response.data.credits;

            // Start polling to check when analysis completes
            startPolling();

            return { success: true, pending: true };
        } catch (err) {
            const message = err.response?.data?.message || 'Erro ao solicitar análise';
            error.value = message;

            if (err.response?.status === 429) {
                nextAvailableAt.value = err.response.data.next_available_at;
            }

            if (err.response?.status === 409) {
                // Already has a pending analysis
                pendingAnalysis.value = err.response.data.pending_analysis;
                startPolling();
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
        pendingAnalysis,
        hasAnalysisInProgress,
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
        startPolling,
        stopPolling,
    };
});

