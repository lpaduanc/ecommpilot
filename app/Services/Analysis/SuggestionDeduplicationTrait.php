<?php

namespace App\Services\Analysis;

use App\Models\Suggestion;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

trait SuggestionDeduplicationTrait
{
    /**
     * Busca sugestões anteriores com detalhes completos
     */
    protected function getPreviousSuggestionsDetailed(int $storeId, int $limit = 50): array
    {
        $suggestions = Suggestion::where('store_id', $storeId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        // Separar por status para tratamento diferenciado
        $accepted = $suggestions->filter(fn ($s) => in_array($s->status, ['accepted', 'in_progress', 'completed']));
        $rejected = $suggestions->filter(fn ($s) => in_array($s->status, ['rejected', 'ignored']));
        $pending = $suggestions->filter(fn ($s) => in_array($s->status, ['new', 'pending']) || is_null($s->status));

        return [
            'all' => $suggestions->map(fn ($s) => $this->formatSuggestionForHistory($s))->toArray(),
            'accepted_titles' => $accepted->pluck('title')->toArray(),
            'rejected_titles' => $rejected->pluck('title')->toArray(),
            'pending' => $pending->map(fn ($s) => $this->formatSuggestionForHistory($s))->toArray(),
        ];
    }

    protected function formatSuggestionForHistory($suggestion): array
    {
        return [
            'id' => $suggestion->id,
            'title' => $suggestion->title,
            'category' => $suggestion->category,
            'expected_impact' => $suggestion->expected_impact,
            'description' => Str::limit($suggestion->description ?? '', 200),
            'status' => $suggestion->status ?? 'pending',
            'was_successful' => $suggestion->was_successful ?? null,
            'created_at' => $suggestion->created_at->format('Y-m-d'),
            'analysis_id' => $suggestion->analysis_id,
        ];
    }

    /**
     * Identifica temas saturados (sugeridos 3+ vezes)
     *
     * V6: Threshold aumentado para 3 - permite re-sugerir tema 2x antes de bloquear.
     * Considera também sugestões rejeitadas como fator de saturação.
     * Usa ThemeKeywords centralizado para consistência entre trait/prompts.
     */
    protected function identifySaturatedThemes(array $suggestions): array
    {
        $themeKeywords = ThemeKeywords::all();

        $counts = [];
        $rejectedThemes = [];

        foreach ($suggestions as $suggestion) {
            // Verificar título E descrição para maior precisão
            $text = mb_strtolower(($suggestion['title'] ?? '').(' '.($suggestion['description'] ?? '')));
            $status = $suggestion['status'] ?? 'pending';
            $isRejected = in_array($status, ['rejected', 'ignored']);

            foreach ($themeKeywords as $theme => $keywords) {
                foreach ($keywords as $keyword) {
                    if (str_contains($text, $keyword)) {
                        $counts[$theme] = ($counts[$theme] ?? 0) + 1;

                        // Rastrear temas rejeitados
                        if ($isRejected) {
                            $rejectedThemes[$theme] = ($rejectedThemes[$theme] ?? 0) + 1;
                        }
                        break;
                    }
                }
            }
        }

        // V7: Threshold aumentado para 3 — permite evolução de temas
        // Um tema pode ser re-sugerido com abordagem diferente até 2x antes de bloquear.
        // Temas rejeitados 2+ vezes são bloqueados (cliente não quer).
        $saturated = array_filter($counts, function ($count) {
            return $count >= 3;
        });

        // Adicionar temas rejeitados 2+ vezes (cliente claramente não quer)
        foreach ($rejectedThemes as $theme => $rejectCount) {
            if (! isset($saturated[$theme]) && $rejectCount >= 2) {
                $saturated[$theme] = $counts[$theme] ?? 1;
            }
        }

        arsort($saturated);

        return $saturated;
    }

    /**
     * Obtém categorias bloqueadas por múltiplas rejeições (3+)
     */
    protected function getBlockedCategoriesByRejection(int $storeId): array
    {
        return Suggestion::where('store_id', $storeId)
            ->whereIn('status', ['rejected', 'ignored'])
            ->selectRaw('category, count(*) as reject_count')
            ->groupBy('category')
            ->havingRaw('count(*) >= 3')
            ->pluck('reject_count', 'category')
            ->toArray();
    }

    /**
     * Valida unicidade antes de salvar
     */
    protected function validateSuggestionUniqueness(
        array $newSuggestions,
        array $previousSuggestions
    ): array {
        $validSuggestions = [];
        $seenTitles = [];
        $previousTitles = array_map(
            fn ($s) => $this->normalizeTitle($s['title'] ?? ''),
            $previousSuggestions
        );

        foreach ($newSuggestions as $suggestion) {
            // Suporte para estrutura com final_version (do Critic) ou direta
            $title = $suggestion['final_version']['title']
                ?? $suggestion['title']
                ?? '';
            $normalizedTitle = $this->normalizeTitle($title);

            // Verificar repetição INTERNA
            if (in_array($normalizedTitle, $seenTitles)) {
                Log::warning('Sugestão repetida INTERNAMENTE detectada', [
                    'title' => $title,
                    'action' => 'descartada',
                ]);

                continue;
            }

            // Verificar repetição HISTÓRICA
            // V7: Threshold relaxado de 0.75 para 0.85 — permite evoluções de temas anteriores
            $maxSimilarity = $this->calculateMaxSimilarity($normalizedTitle, $previousTitles);
            if ($maxSimilarity > 0.85) {
                Log::warning('Sugestão similar a HISTÓRICA detectada', [
                    'title' => $title,
                    'similarity' => round($maxSimilarity * 100, 1).'%',
                    'action' => 'descartada',
                ]);

                continue;
            }

            $seenTitles[] = $normalizedTitle;
            $validSuggestions[] = $suggestion;
        }

        Log::info('Validação de unicidade concluída', [
            'recebidas' => count($newSuggestions),
            'aprovadas' => count($validSuggestions),
            'descartadas' => count($newSuggestions) - count($validSuggestions),
        ]);

        return $validSuggestions;
    }

    /**
     * Normaliza título para comparação.
     *
     * V7: Unificado com StoreAnalysisService::normalizeTitle()
     * - Remove stopwords comuns em português
     * - Remove verbos de ação genéricos (implementar, otimizar, etc.)
     * - Remove palavras de domínio (produto, cliente, loja, etc.)
     * - Ordena palavras alfabeticamente para comparação order-independent
     */
    protected function normalizeTitle(string $title): string
    {
        $title = mb_strtolower(trim($title));
        $title = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title) ?: $title;
        $title = preg_replace('/[^a-z0-9\s]/', '', $title);

        $stopwords = [
            'de', 'da', 'do', 'das', 'dos', 'para', 'com', 'em', 'a', 'o', 'as', 'os',
            'e', 'ou', 'um', 'uma', 'uns', 'umas', 'no', 'na', 'nos', 'nas',
            'pelo', 'pela', 'pelos', 'pelas', 'ao', 'aos',
            'implementar', 'otimizar', 'criar', 'desenvolver', 'lancar', 'ativar',
            'configurar', 'melhorar', 'aprimorar', 'revisar', 'analisar', 'oferecer',
            'estrategia', 'estrategico', 'estrategica',
            'gestao', 'gerenciamento', 'sistema', 'programa', 'plano',
            'produtos', 'produto', 'clientes', 'cliente', 'loja', 'lojas',
        ];

        $words = preg_split('/\s+/', $title);
        $filteredWords = array_filter($words, fn ($w) => ! in_array($w, $stopwords) && strlen($w) > 2);

        sort($filteredWords);

        return implode(' ', $filteredWords);
    }

    /**
     * Calcula similaridade entre dois títulos normalizados usando Jaccard (word-based).
     * Apropriado para títulos já normalizados com stopwords removidos e palavras ordenadas.
     */
    protected function calculateTitleSimilarity(string $title1, string $title2): float
    {
        $words1 = array_unique(array_filter(preg_split('/\s+/', $title1)));
        $words2 = array_unique(array_filter(preg_split('/\s+/', $title2)));

        if (empty($words1) || empty($words2)) {
            return 0.0;
        }

        $intersection = count(array_intersect($words1, $words2));
        $union = count(array_unique(array_merge($words1, $words2)));

        if ($union === 0) {
            return 0.0;
        }

        return $intersection / $union;
    }

    /**
     * Calcula similaridade máxima com lista de títulos
     */
    protected function calculateMaxSimilarity(string $title, array $previousTitles): float
    {
        $maxSimilarity = 0;

        foreach ($previousTitles as $prevTitle) {
            $similarity = $this->calculateTitleSimilarity($title, $prevTitle);
            $maxSimilarity = max($maxSimilarity, $similarity);
        }

        return $maxSimilarity;
    }

    /**
     * Loga estatísticas de deduplicação
     */
    protected function logDeduplicationStats(int $analysisId, array $stats): void
    {
        Log::info('Estatísticas de deduplicação', [
            'analysis_id' => $analysisId,
            'suggestions_from_strategist' => $stats['from_strategist'] ?? 0,
            'suggestions_from_critic' => $stats['from_critic'] ?? 0,
            'internal_duplicates_removed' => $stats['internal_duplicates'] ?? 0,
            'historical_duplicates_removed' => $stats['historical_duplicates'] ?? 0,
            'final_count' => $stats['final_count'] ?? 0,
            'saturated_themes_detected' => $stats['saturated_themes'] ?? [],
        ]);
    }
}
