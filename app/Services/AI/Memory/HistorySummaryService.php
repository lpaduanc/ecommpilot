<?php

namespace App\Services\AI\Memory;

use App\Models\Suggestion;

class HistorySummaryService
{
    /**
     * Gera um resumo estruturado do histórico de sugestões
     * Reduz 8k tokens para ~1.5-2k tokens
     */
    public function generateSummary(int $storeId): array
    {
        $suggestions = $this->getAllSuggestionsForStore($storeId);

        if (empty($suggestions)) {
            return $this->getEmptySummary();
        }

        return [
            'total_suggestions' => count($suggestions),
            'success_rate' => $this->calculateSuccessRate($suggestions),
            'implementation_by_category' => $this->getImplementationByCategory($suggestions),
            'ignored_by_category' => $this->getIgnoredByCategory($suggestions),
            'repeated_topics' => $this->getRepeatedTopics($suggestions),
            'recent_successes' => $this->getRecentSuccesses($suggestions),
            'categories_to_avoid' => $this->getIgnoredCategories($suggestions),
        ];
    }

    private function calculateSuccessRate(array $suggestions): string
    {
        $completed = count(array_filter($suggestions, fn ($s) => $s['status'] === 'completed'));
        $total = count($suggestions);
        $rate = $total > 0 ? round(($completed / $total) * 100, 0) : 0;

        return "{$completed} de {$total} ({$rate}%)";
    }

    private function getImplementationByCategory(array $suggestions): array
    {
        $implemented = array_filter($suggestions, fn ($s) => $s['status'] === 'completed');

        $byCategory = [];
        foreach ($implemented as $suggestion) {
            $category = $suggestion['category'];
            $byCategory[$category] = ($byCategory[$category] ?? 0) + 1;
        }

        arsort($byCategory);

        return $byCategory;
    }

    private function getIgnoredByCategory(array $suggestions): array
    {
        $ignored = array_filter($suggestions, fn ($s) => $s['status'] === 'ignored');

        $byCategory = [];
        foreach ($ignored as $suggestion) {
            $category = $suggestion['category'];
            $byCategory[$category] = ($byCategory[$category] ?? 0) + 1;
        }

        arsort($byCategory);

        return $byCategory;
    }

    /**
     * Retorna tópicos/palavras-chave que foram sugeridas muitas vezes
     * Útil para evitar repetições
     */
    private function getRepeatedTopics(array $suggestions): array
    {
        $keywords = [
            'estoque' => 0,
            'cupom' => 0,
            'cancelamento' => 0,
            'frete' => 0,
            'recompra' => 0,
            'produto' => 0,
            'marketing' => 0,
            'desconto' => 0,
        ];

        foreach ($suggestions as $suggestion) {
            $title = strtolower($suggestion['title'] ?? '');
            $description = strtolower($suggestion['description'] ?? '');
            $text = $title.' '.$description;

            foreach (array_keys($keywords) as $keyword) {
                if (str_contains($text, $keyword)) {
                    $keywords[$keyword]++;
                }
            }
        }

        // Retorna apenas tópicos mencionados mais de 5 vezes
        $repeated = array_filter($keywords, fn ($count) => $count > 5);
        arsort($repeated);

        return $repeated;
    }

    /**
     * Últimas 3 sugestões implementadas com sucesso
     */
    private function getRecentSuccesses(array $suggestions): array
    {
        $completed = array_filter($suggestions, fn ($s) => $s['status'] === 'completed');

        usort($completed, fn ($a, $b) => strtotime($b['completed_at'] ?? '1970-01-01') <=> strtotime($a['completed_at'] ?? '1970-01-01')
        );

        return array_map(function ($s) {
            $completedAt = $s['completed_at'] ?? null;
            $daysAgo = $completedAt ? floor((time() - strtotime($completedAt)) / 86400) : null;

            return [
                'title' => substr($s['title'] ?? '', 0, 60),
                'category' => $s['category'] ?? 'unknown',
                'days_ago' => $daysAgo,
            ];
        }, array_slice($completed, 0, 3));
    }

    private function getIgnoredCategories(array $suggestions): array
    {
        $ignored = array_filter($suggestions, fn ($s) => $s['status'] === 'ignored');

        $byCategory = [];

        foreach ($ignored as $suggestion) {
            $category = $suggestion['category'];
            $byCategory[$category] = ($byCategory[$category] ?? 0) + 1;
        }

        // Categorias onde 50%+ foram ignoradas
        $problematic = [];
        foreach ($byCategory as $category => $count) {
            $categoryTotal = count(array_filter($suggestions, fn ($s) => $s['category'] === $category));
            if ($categoryTotal > 0) {
                $ignoreRate = ($count / $categoryTotal) * 100;

                if ($ignoreRate >= 50) {
                    $problematic[$category] = round($ignoreRate, 0);
                }
            }
        }

        return $problematic;
    }

    private function getEmptySummary(): array
    {
        return [
            'total_suggestions' => 0,
            'success_rate' => 'N/A',
            'implementation_by_category' => [],
            'ignored_by_category' => [],
            'repeated_topics' => [],
            'recent_successes' => [],
            'categories_to_avoid' => [],
        ];
    }

    private function getAllSuggestionsForStore(int $storeId): array
    {
        // Retorna últimas 100 sugestões
        return Suggestion::where('store_id', $storeId)
            ->orderBy('created_at', 'desc')
            ->take(100)
            ->get(['id', 'category', 'title', 'description', 'status', 'completed_at'])
            ->toArray();
    }
}
