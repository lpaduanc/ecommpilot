<?php

namespace App\Services\Analysis;

use App\Models\CategoryStats;
use App\Models\FailureCase;
use App\Models\Store;
use App\Models\SuccessCase;
use App\Models\Suggestion;
use Illuminate\Support\Facades\Log;

trait FeedbackLoopTrait
{
    /**
     * Processa feedback de uma sugestão implementada
     */
    public function processSuggestionFeedback(
        Suggestion $suggestion,
        bool $wasSuccessful,
        ?array $metricsImpact = null
    ): void {
        $this->updateCategoryStats($suggestion->category, $wasSuccessful);

        if ($wasSuccessful) {
            $this->saveSuccessCase($suggestion, $metricsImpact);
        } else {
            $this->saveFailureCase($suggestion);
        }

        Log::info('Feedback de sugestão processado', [
            'suggestion_id' => $suggestion->id,
            'store_id' => $suggestion->store_id,
            'category' => $suggestion->category,
            'title' => $suggestion->title,
            'was_successful' => $wasSuccessful,
            'metrics_impact' => $metricsImpact,
        ]);
    }

    /**
     * Atualiza estatísticas de sucesso por categoria
     */
    protected function updateCategoryStats(string $category, bool $wasSuccessful): void
    {
        $stats = CategoryStats::firstOrCreate(
            ['category' => $category],
            ['total_implemented' => 0, 'total_successful' => 0]
        );

        $stats->total_implemented++;
        if ($wasSuccessful) {
            $stats->total_successful++;
        }
        $stats->success_rate = $stats->total_implemented > 0
            ? round(($stats->total_successful / $stats->total_implemented) * 100, 2)
            : 0;
        $stats->save();
    }

    /**
     * Salva caso de sucesso para referência futura
     */
    protected function saveSuccessCase(Suggestion $suggestion, ?array $metricsImpact): void
    {
        SuccessCase::create([
            'store_id' => $suggestion->store_id,
            'niche' => $suggestion->store->niche ?? 'geral',
            'subcategory' => $suggestion->store->niche_subcategory ?? 'geral',
            'category' => $suggestion->category,
            'title' => $suggestion->title,
            'description' => $suggestion->description,
            'implementation_details' => $suggestion->recommended_action,
            'metrics_impact' => $metricsImpact,
        ]);
    }

    /**
     * Salva caso de falha para evitar repetir erros
     */
    protected function saveFailureCase(Suggestion $suggestion): void
    {
        FailureCase::create([
            'store_id' => $suggestion->store_id,
            'category' => $suggestion->category,
            'title' => $suggestion->title,
            'failure_reason' => $suggestion->feedback ?? 'Não informado',
        ]);
    }

    /**
     * Busca casos de sucesso similares para uma loja
     */
    public function getSuccessCasesForStore(Store $store, int $limit = 5): array
    {
        return SuccessCase::where('niche', $store->niche)
            ->where('subcategory', $store->niche_subcategory)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($case) {
                return [
                    'category' => $case->category,
                    'title' => $case->title,
                    'description' => $case->description,
                    'metrics_impact' => $case->metrics_impact,
                ];
            })
            ->toArray();
    }

    /**
     * Busca casos de falha para evitar
     */
    public function getFailureCasesForStore(Store $store, int $limit = 10): array
    {
        return FailureCase::where('store_id', $store->id)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($case) {
                return [
                    'category' => $case->category,
                    'title' => $case->title,
                    'failure_reason' => $case->failure_reason,
                ];
            })
            ->toArray();
    }

    /**
     * Retorna taxas de sucesso atualizadas por categoria
     */
    public function getCategorySuccessRates(): array
    {
        return CategoryStats::all()
            ->mapWithKeys(function ($stats) {
                return [$stats->category => [
                    'total_implemented' => $stats->total_implemented,
                    'total_successful' => $stats->total_successful,
                    'success_rate' => $stats->success_rate,
                ]];
            })
            ->toArray();
    }
}
