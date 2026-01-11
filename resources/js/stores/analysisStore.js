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

    // Persistent suggestions state
    const persistentSuggestions = ref([]);
    const suggestionStats = ref(null);
    const suggestionsLoading = ref(false);
    const suggestionsPagination = ref({
        total: 0,
        currentPage: 1,
        lastPage: 1,
    });
    const suggestionsFilter = ref({
        status: null,
        category: null,
        impact: null,
    });
    
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

    // Persistent suggestions computed
    const pendingSuggestions = computed(() =>
        persistentSuggestions.value.filter(s => s.status === 'pending')
    );

    const inProgressSuggestions = computed(() =>
        persistentSuggestions.value.filter(s => s.status === 'in_progress')
    );

    const completedSuggestions = computed(() =>
        persistentSuggestions.value.filter(s => s.status === 'completed')
    );

    const ignoredSuggestions = computed(() =>
        persistentSuggestions.value.filter(s => s.status === 'ignored')
    );

    const highImpactSuggestions = computed(() =>
        persistentSuggestions.value.filter(s => s.expected_impact === 'high' && s.status !== 'completed' && s.status !== 'ignored')
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

    // Persistent suggestions actions
    async function fetchPersistentSuggestions(page = 1) {
        suggestionsLoading.value = true;

        try {
            const params = new URLSearchParams();
            params.append('page', page);

            if (suggestionsFilter.value.status) {
                params.append('status', suggestionsFilter.value.status);
            }
            if (suggestionsFilter.value.category) {
                params.append('category', suggestionsFilter.value.category);
            }
            if (suggestionsFilter.value.impact) {
                params.append('impact', suggestionsFilter.value.impact);
            }

            const response = await api.get(`/suggestions?${params.toString()}`);
            persistentSuggestions.value = response.data.suggestions;
            suggestionsPagination.value = {
                total: response.data.total,
                currentPage: response.data.current_page,
                lastPage: response.data.last_page,
            };
        } catch (err) {
            error.value = err.response?.data?.message || 'Erro ao carregar sugestões';
        } finally {
            suggestionsLoading.value = false;
        }
    }

    async function fetchSuggestionStats() {
        try {
            const response = await api.get('/suggestions/stats');
            suggestionStats.value = response.data.stats;
        } catch {
            suggestionStats.value = null;
        }
    }

    async function updateSuggestionStatus(suggestionId, newStatus) {
        try {
            const response = await api.patch(`/suggestions/${suggestionId}`, {
                status: newStatus,
            });

            // Update local state
            const index = persistentSuggestions.value.findIndex(s => s.id === suggestionId);
            if (index !== -1) {
                persistentSuggestions.value[index] = response.data.suggestion;
            }

            // Refresh stats
            await fetchSuggestionStats();

            return { success: true, suggestion: response.data.suggestion };
        } catch (err) {
            return {
                success: false,
                message: err.response?.data?.message || 'Erro ao atualizar sugestão',
            };
        }
    }

    async function getSuggestionDetail(suggestionId) {
        try {
            const response = await api.get(`/suggestions/${suggestionId}`);
            return response.data.suggestion;
        } catch {
            return null;
        }
    }

    function setSuggestionsFilter(filter) {
        suggestionsFilter.value = { ...suggestionsFilter.value, ...filter };
        fetchPersistentSuggestions(1);
    }

    function clearSuggestionsFilter() {
        suggestionsFilter.value = { status: null, category: null, impact: null };
        fetchPersistentSuggestions(1);
    }

    return {
        // Analysis state
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

        // Persistent suggestions state
        persistentSuggestions,
        suggestionStats,
        suggestionsLoading,
        suggestionsPagination,
        suggestionsFilter,
        pendingSuggestions,
        inProgressSuggestions,
        completedSuggestions,
        ignoredSuggestions,
        highImpactSuggestions,

        // Analysis actions
        fetchCurrentAnalysis,
        fetchAnalysisHistory,
        requestNewAnalysis,
        getAnalysisById,
        markSuggestionAsDone,
        getSuggestionsByCategory,
        startPolling,
        stopPolling,

        // Persistent suggestions actions
        fetchPersistentSuggestions,
        fetchSuggestionStats,
        updateSuggestionStatus,
        getSuggestionDetail,
        setSuggestionsFilter,
        clearSuggestionsFilter,
    };
});

