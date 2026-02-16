import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import api from '../services/api';
import { logger } from '../utils/logger';

export const useAnalysisStore = defineStore('analysis', () => {
    const currentAnalysis = ref(null);
    const pendingAnalysis = ref(null);
    const analysisHistory = ref([]);
    const isLoading = ref(false);
    const isRequesting = ref(false);
    const error = ref(null);
    const nextAvailableAt = ref(null);
    const pollingInterval = ref(null);
    const analysisTypes = ref([]);

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
        // Don't count failed analyses as "in progress"
        return pendingAnalysis.value !== null &&
            pendingAnalysis.value.status !== 'failed';
    });

    const hasPendingError = computed(() => {
        return pendingAnalysis.value?.status === 'failed';
    });

    const canRequestAnalysis = computed(() => {
        // Can't request if there's already one in progress (but failed analyses allow retry)
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
    const premiumSummary = computed(() => currentAnalysis.value?.premium_summary || null);

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

    let pollingErrorCount = 0;
    const MAX_POLLING_ERRORS = 5;

    function startPolling() {
        if (pollingInterval.value) return;

        pollingInterval.value = setInterval(async () => {
            try {
                const response = await api.get('/analysis/current');
                pollingErrorCount = 0; // Reset on success

                // Check if analysis completed
                if (!response.data.pending_analysis && pendingAnalysis.value) {
                    // Analysis completed! Fetch full analysis data with suggestions
                    stopPolling();
                    await fetchCurrentAnalysis();
                    await fetchAnalysisHistory();
                } else {
                    // Still processing, update pending analysis progress
                    pendingAnalysis.value = response.data.pending_analysis;
                }
            } catch (err) {
                pollingErrorCount++;
                if (pollingErrorCount >= MAX_POLLING_ERRORS) {
                    // Stop polling after too many consecutive errors
                    if (import.meta.env.DEV) {
                        logger.error('Polling failed too many times, stopping');
                    }
                    stopPolling();
                    error.value = 'Erro ao verificar status da análise. Recarregue a página.';
                }
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
    
    async function fetchAnalysisTypes() {
        try {
            const response = await api.get('/analysis/types');
            analysisTypes.value = response.data.types.filter(t => t.available);
        } catch {
            analysisTypes.value = [];
        }
    }

    async function requestNewAnalysis(analysisType = 'general') {
        isRequesting.value = true;
        error.value = null;

        try {
            const response = await api.post('/analysis/request', {
                analysis_type: analysisType,
            });
            pendingAnalysis.value = response.data.pending_analysis;

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
        try {
            const response = await api.get(`/analysis/${id}`);
            return response.data;
        } catch {
            return null;
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

            const updatedData = response.data.suggestion;

            // Update persistentSuggestions array (merge to preserve formatted fields)
            const index = persistentSuggestions.value.findIndex(s => s.id === suggestionId);
            if (index !== -1) {
                const mergedPersistent = {
                    ...persistentSuggestions.value[index],
                    ...updatedData,
                    is_done: updatedData.status === 'completed',
                };
                // Use splice to ensure Vue reactivity
                persistentSuggestions.value.splice(index, 1, mergedPersistent);
            }

            // Update currentAnalysis.suggestions array (merge to preserve priority and other formatted fields)
            let mergedSuggestion = { ...updatedData, is_done: updatedData.status === 'completed' };
            if (currentAnalysis.value?.suggestions) {
                const analysisIndex = currentAnalysis.value.suggestions.findIndex(
                    s => s.id === suggestionId
                );
                if (analysisIndex !== -1) {
                    // Merge: keep existing formatted fields (especially 'priority'), update with new data
                    mergedSuggestion = {
                        ...currentAnalysis.value.suggestions[analysisIndex],
                        ...updatedData,
                        status: updatedData.status,
                        is_done: updatedData.status === 'completed',
                    };
                    // Use splice to ensure Vue reactivity
                    currentAnalysis.value.suggestions.splice(analysisIndex, 1, mergedSuggestion);
                }
            }

            // Refresh stats
            await fetchSuggestionStats();

            return { success: true, suggestion: mergedSuggestion };
        } catch (err) {
            return {
                success: false,
                message: err.response?.data?.message || 'Erro ao atualizar sugestão',
            };
        }
    }

    /**
     * Accept a suggestion - moves it to tracking page
     */
    async function acceptSuggestion(suggestionId) {
        try {
            const response = await api.post(`/suggestions/${suggestionId}/accept`);
            const updatedData = response.data.suggestion;

            // Update in currentAnalysis.suggestions
            if (currentAnalysis.value?.suggestions) {
                const index = currentAnalysis.value.suggestions.findIndex(s => s.id === suggestionId);
                if (index !== -1) {
                    currentAnalysis.value.suggestions.splice(index, 1, {
                        ...currentAnalysis.value.suggestions[index],
                        ...updatedData,
                    });
                }
            }

            // Refresh stats
            await fetchSuggestionStats();

            return { success: true, suggestion: updatedData };
        } catch (err) {
            return {
                success: false,
                message: err.response?.data?.message || 'Erro ao aceitar sugestão',
            };
        }
    }

    /**
     * Reject a suggestion - keeps it on analysis page
     */
    async function rejectSuggestion(suggestionId) {
        try {
            const response = await api.post(`/suggestions/${suggestionId}/reject`);
            const updatedData = response.data.suggestion;

            // Update in currentAnalysis.suggestions
            if (currentAnalysis.value?.suggestions) {
                const index = currentAnalysis.value.suggestions.findIndex(s => s.id === suggestionId);
                if (index !== -1) {
                    currentAnalysis.value.suggestions.splice(index, 1, {
                        ...currentAnalysis.value.suggestions[index],
                        ...updatedData,
                    });
                }
            }

            // Refresh stats
            await fetchSuggestionStats();

            return { success: true, suggestion: updatedData };
        } catch (err) {
            return {
                success: false,
                message: err.response?.data?.message || 'Erro ao rejeitar sugestão',
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

    // Suggestion Steps Actions
    async function fetchSuggestionSteps(suggestionId) {
        try {
            const response = await api.get(`/suggestions/${suggestionId}/steps`);
            return { success: true, steps: response.data.steps };
        } catch (err) {
            return {
                success: false,
                message: err.response?.data?.message || 'Erro ao carregar passos',
            };
        }
    }

    async function createSuggestionStep(suggestionId, data) {
        try {
            const response = await api.post(`/suggestions/${suggestionId}/steps`, data);
            return { success: true, step: response.data.step };
        } catch (err) {
            return {
                success: false,
                message: err.response?.data?.message || 'Erro ao criar passo',
            };
        }
    }

    async function updateSuggestionStep(suggestionId, stepId, data) {
        try {
            const response = await api.patch(`/suggestions/${suggestionId}/steps/${stepId}`, data);
            return { success: true, step: response.data.step };
        } catch (err) {
            return {
                success: false,
                message: err.response?.data?.message || 'Erro ao atualizar passo',
            };
        }
    }

    async function deleteSuggestionStep(suggestionId, stepId) {
        try {
            await api.delete(`/suggestions/${suggestionId}/steps/${stepId}`);
            return { success: true };
        } catch (err) {
            return {
                success: false,
                message: err.response?.data?.message || 'Erro ao deletar passo',
            };
        }
    }

    async function toggleStepStatus(suggestionId, stepId) {
        try {
            const response = await api.post(`/suggestions/${suggestionId}/steps/${stepId}/toggle`);
            return { success: true, step: response.data.step };
        } catch (err) {
            return {
                success: false,
                message: err.response?.data?.message || 'Erro ao atualizar status',
            };
        }
    }

    // Suggestion Comments Actions
    async function fetchSuggestionComments(suggestionId) {
        try {
            const response = await api.get(`/suggestions/${suggestionId}/comments`);
            return { success: true, comments: response.data.comments };
        } catch (err) {
            return {
                success: false,
                message: err.response?.data?.message || 'Erro ao carregar comentários',
            };
        }
    }

    async function createSuggestionComment(suggestionId, data) {
        try {
            const response = await api.post(`/suggestions/${suggestionId}/comments`, data);
            return { success: true, comment: response.data.comment };
        } catch (err) {
            return {
                success: false,
                message: err.response?.data?.message || 'Erro ao criar comentário',
            };
        }
    }

    async function deleteSuggestionComment(suggestionId, commentId) {
        try {
            await api.delete(`/suggestions/${suggestionId}/comments/${commentId}`);
            return { success: true };
        } catch (err) {
            return {
                success: false,
                message: err.response?.data?.message || 'Erro ao deletar comentário',
            };
        }
    }

    // Suggestion Tasks Actions
    async function fetchSuggestionTasks(suggestionId) {
        try {
            const response = await api.get(`/suggestions/${suggestionId}/tasks`);
            return { success: true, tasks: response.data.tasks };
        } catch (err) {
            return {
                success: false,
                message: err.response?.data?.message || 'Erro ao carregar tarefas',
            };
        }
    }

    async function createSuggestionTask(suggestionId, data) {
        try {
            const response = await api.post(`/suggestions/${suggestionId}/tasks`, data);
            return { success: true, task: response.data.task };
        } catch (err) {
            return {
                success: false,
                message: err.response?.data?.message || 'Erro ao criar tarefa',
            };
        }
    }

    async function updateSuggestionTask(suggestionId, taskId, data) {
        try {
            const response = await api.patch(`/suggestions/${suggestionId}/tasks/${taskId}`, data);
            return { success: true, task: response.data.task };
        } catch (err) {
            return {
                success: false,
                message: err.response?.data?.message || 'Erro ao atualizar tarefa',
            };
        }
    }

    async function deleteSuggestionTask(suggestionId, taskId) {
        try {
            await api.delete(`/suggestions/${suggestionId}/tasks/${taskId}`);
            return { success: true };
        } catch (err) {
            return {
                success: false,
                message: err.response?.data?.message || 'Erro ao deletar tarefa',
            };
        }
    }

    async function toggleTaskStatus(suggestionId, taskId) {
        try {
            const response = await api.post(`/suggestions/${suggestionId}/tasks/${taskId}/toggle`);
            return { success: true, task: response.data.task };
        } catch (err) {
            return {
                success: false,
                message: err.response?.data?.message || 'Erro ao atualizar status da tarefa',
            };
        }
    }

    async function updateSuggestionFeedback(suggestionId, wasSuccessful) {
        try {
            const response = await api.patch(`/suggestions/${suggestionId}/feedback`, {
                was_successful: wasSuccessful,
            });
            return { success: true, suggestion: response.data.suggestion };
        } catch (error) {
            console.error('Error updating feedback:', error);
            return { success: false, error };
        }
    }

    async function resendAnalysisEmail(analysisId) {
        try {
            const response = await api.post(`/analysis/${analysisId}/resend-email`);

            // Atualizar estado local
            if (currentAnalysis.value?.id === analysisId) {
                currentAnalysis.value.email_sent_at = new Date().toISOString();
                currentAnalysis.value.email_error = null;
            }

            return response.data;
        } catch (error) {
            throw error;
        }
    }

    /**
     * Set current analysis (for viewing historical analyses)
     */
    function setCurrentAnalysis(analysis) {
        currentAnalysis.value = analysis;
    }

    return {
        // Analysis state
        currentAnalysis,
        pendingAnalysis,
        hasAnalysisInProgress,
        hasPendingError,
        analysisHistory,
        isLoading,
        isRequesting,
        error,
        nextAvailableAt,
        canRequestAnalysis,
        timeUntilNextAnalysis,
        suggestions,
        alerts,
        opportunities,
        summary,
        premiumSummary,
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

        // Analysis types
        analysisTypes,
        fetchAnalysisTypes,

        // Analysis actions
        fetchCurrentAnalysis,
        fetchAnalysisHistory,
        requestNewAnalysis,
        getAnalysisById,
        setCurrentAnalysis,
        markSuggestionAsDone,
        getSuggestionsByCategory,
        startPolling,
        stopPolling,
        resendAnalysisEmail,

        // Persistent suggestions actions
        fetchPersistentSuggestions,
        fetchSuggestionStats,
        updateSuggestionStatus,
        acceptSuggestion,
        rejectSuggestion,
        getSuggestionDetail,
        setSuggestionsFilter,
        clearSuggestionsFilter,

        // Steps actions
        fetchSuggestionSteps,
        createSuggestionStep,
        updateSuggestionStep,
        deleteSuggestionStep,
        toggleStepStatus,

        // Comments actions
        fetchSuggestionComments,
        createSuggestionComment,
        deleteSuggestionComment,

        // Tasks actions
        fetchSuggestionTasks,
        createSuggestionTask,
        updateSuggestionTask,
        deleteSuggestionTask,
        toggleTaskStatus,

        // Feedback actions
        updateSuggestionFeedback,
    };
});

