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
        $accepted = $suggestions->filter(fn($s) => in_array($s->status, ['accepted', 'in_progress', 'completed']));
        $rejected = $suggestions->filter(fn($s) => in_array($s->status, ['rejected', 'ignored']));
        $pending = $suggestions->filter(fn($s) => in_array($s->status, ['new', 'pending']) || is_null($s->status));

        return [
            'all' => $suggestions->map(fn($s) => $this->formatSuggestionForHistory($s))->toArray(),
            'accepted_titles' => $accepted->pluck('title')->toArray(),
            'rejected_titles' => $rejected->pluck('title')->toArray(),
            'pending' => $pending->map(fn($s) => $this->formatSuggestionForHistory($s))->toArray(),
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
     * Identifica temas saturados (sugeridos 2+ vezes)
     */
    protected function identifySaturatedThemes(array $suggestions): array
    {
        $themeKeywords = [
            'quiz' => ['quiz', 'questionário', 'personalizado', 'recomendação'],
            'frete_gratis' => ['frete grátis', 'frete gratuito', 'entrega grátis'],
            'fidelidade' => ['fidelidade', 'pontos', 'cashback', 'loyalty', 'recompensa'],
            'kits' => ['kit', 'combo', 'bundle', 'cronograma'],
            'estoque' => ['estoque', 'avise-me', 'reposição', 'inventário', 'ruptura'],
            'email' => ['email', 'e-mail', 'newsletter', 'automação', 'pós-venda'],
            'video' => ['vídeo', 'tutorial', 'youtube'],
            'assinatura' => ['assinatura', 'recorrência', 'subscription', 'clube'],
            'cupom' => ['cupom', 'desconto', 'promoção'],
            'checkout' => ['checkout', 'carrinho', 'conversão', 'abandono'],
            'whatsapp' => ['whatsapp', 'telegram', 'chat', 'mensagem'],
            'reviews' => ['review', 'ugc', 'avaliação', 'depoimento', 'fotos', 'vídeos', 'antes e depois'],
            'pos_compra' => ['pós-compra', 'pos-compra', 'cancelamento', 'follow-up', 'acompanhamento'],
            'influenciadores' => ['influenciador', 'micro-influenciador', 'embaixador', 'embaixadora', 'parceria'],
            'gamificacao' => ['gamificação', 'gamificacao', 'pontos', 'desafio', 'milhas', 'níveis'],
            'conteudo' => ['conteúdo', 'conteudo', 'hub', 'guia', 'educativo', 'tutorial'],
        ];

        $counts = [];

        foreach ($suggestions as $suggestion) {
            $title = mb_strtolower($suggestion['title'] ?? '');
            foreach ($themeKeywords as $theme => $keywords) {
                foreach ($keywords as $keyword) {
                    if (str_contains($title, $keyword)) {
                        $counts[$theme] = ($counts[$theme] ?? 0) + 1;
                        break;
                    }
                }
            }
        }

        $saturated = array_filter($counts, fn($count) => $count >= 2);
        arsort($saturated);

        return $saturated;
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
            fn($s) => $this->normalizeTitle($s['title'] ?? ''),
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
                    'action' => 'descartada'
                ]);
                continue;
            }

            // Verificar repetição HISTÓRICA
            $maxSimilarity = $this->calculateMaxSimilarity($normalizedTitle, $previousTitles);
            if ($maxSimilarity > 0.75) {
                Log::warning('Sugestão similar a HISTÓRICA detectada', [
                    'title' => $title,
                    'similarity' => round($maxSimilarity * 100, 1) . '%',
                    'action' => 'descartada'
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
     * Normaliza título para comparação
     */
    protected function normalizeTitle(string $title): string
    {
        $title = mb_strtolower($title);
        $title = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title) ?: $title;
        $title = preg_replace('/[^a-z0-9\s]/', '', $title);
        $title = preg_replace('/\s+/', ' ', $title);
        return trim($title);
    }

    /**
     * Calcula similaridade máxima com lista de títulos
     */
    protected function calculateMaxSimilarity(string $title, array $previousTitles): float
    {
        $maxSimilarity = 0;

        foreach ($previousTitles as $prevTitle) {
            similar_text($title, $prevTitle, $percent);
            $similarity1 = $percent / 100;

            $maxLen = max(strlen($title), strlen($prevTitle));
            if ($maxLen > 0) {
                $levenshtein = levenshtein($title, $prevTitle);
                $similarity2 = 1 - ($levenshtein / $maxLen);
            } else {
                $similarity2 = 0;
            }

            $similarity = max($similarity1, $similarity2);
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
