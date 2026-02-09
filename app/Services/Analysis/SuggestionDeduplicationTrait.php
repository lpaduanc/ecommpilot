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
     * Identifica temas saturados (sugeridos 2+ vezes)
     *
     * V5: Threshold aumentado para 2 - permite re-sugerir tema 1x antes de bloquear.
     * Considera também sugestões rejeitadas como fator de saturação.
     */
    protected function identifySaturatedThemes(array $suggestions): array
    {
        // Expandido para 25 categorias de temas monitorados
        $themeKeywords = [
            // Temas originais
            'quiz' => ['quiz', 'questionário', 'personalizado', 'recomendação', 'personalização'],
            'frete_gratis' => ['frete grátis', 'frete gratuito', 'entrega grátis', 'frete gratis'],
            'fidelidade' => ['fidelidade', 'pontos', 'cashback', 'loyalty', 'recompensa'],
            'kits' => ['kit', 'combo', 'bundle', 'cronograma', 'pack'],
            'estoque' => ['estoque', 'avise-me', 'reposição', 'inventário', 'ruptura'],
            'email' => ['email', 'e-mail', 'newsletter', 'automação', 'pós-venda'],
            'video' => ['vídeo', 'video', 'tutorial', 'youtube', 'reels'],
            'assinatura' => ['assinatura', 'recorrência', 'subscription', 'clube'],
            'cupom' => ['cupom', 'desconto', 'promoção', 'voucher', 'código'],
            'checkout' => ['checkout', 'carrinho', 'conversão', 'abandono', 'finalização'],
            'whatsapp' => ['whatsapp', 'telegram', 'chat', 'mensagem', 'zap'],
            'reviews' => ['review', 'ugc', 'avaliação', 'depoimento', 'fotos', 'vídeos', 'antes e depois'],
            'pos_compra' => ['pós-compra', 'pos-compra', 'follow-up', 'acompanhamento'],
            'influenciadores' => ['influenciador', 'micro-influenciador', 'embaixador', 'embaixadora', 'parceria', 'afiliado'],
            'gamificacao' => ['gamificação', 'gamificacao', 'desafio', 'milhas', 'níveis'],
            'conteudo' => ['conteúdo', 'conteudo', 'hub', 'guia', 'educativo'],

            // Novos temas adicionados (sazonais e operacionais)
            'carnaval' => ['carnaval', 'folia', 'fantasia', 'bloco'],
            'ticket' => ['ticket médio', 'ticket', 'aov', 'valor médio'],
            'cancelamento' => ['cancelamento', 'cancelado', 'desistência', 'churn'],
            'reativacao' => ['reativação', 'reativar', 'inativos', 'dormentes', 'win-back'],
            'upsell' => ['upsell', 'up-sell', 'upgrade', 'premium'],
            'cross_sell' => ['cross-sell', 'cross sell', 'venda cruzada', 'produtos relacionados', 'compre junto'],
            'preco' => ['preço', 'pricing', 'margem', 'precificação'],
            'seo' => ['seo', 'google', 'busca', 'orgânico', 'ranqueamento'],
            'remarketing' => ['remarketing', 'retargeting', 'pixel', 'público similar'],
        ];

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

        // V5: Threshold aumentado para 2
        // OU se foi rejeitado pelo menos 1x (penaliza temas que o cliente não gostou)
        $saturated = array_filter($counts, function ($count) {
            return $count >= 2;
        });

        // Adicionar temas rejeitados mesmo que apareçam só 1x
        foreach ($rejectedThemes as $theme => $rejectCount) {
            if (! isset($saturated[$theme]) && $rejectCount >= 1) {
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
            $maxSimilarity = $this->calculateMaxSimilarity($normalizedTitle, $previousTitles);
            if ($maxSimilarity > 0.75) {
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
