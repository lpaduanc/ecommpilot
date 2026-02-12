<?php

namespace App\Services\Analysis;

/**
 * Centralized theme keywords for suggestion deduplication and saturation detection.
 *
 * This class provides the canonical list of themes and their associated keywords
 * used across the AI analysis pipeline to detect saturated themes and prevent
 * repetitive suggestions.
 *
 * @version 6.0 - Centralized from SuggestionDeduplicationTrait (25 themes)
 */
class ThemeKeywords
{
    /**
     * Get all theme keywords.
     *
     * Returns a map of theme => keywords array.
     * Keywords are used to detect theme presence in suggestion titles and descriptions.
     *
     * @return array<string, array<string>>
     */
    public static function all(): array
    {
        return [
            // Original themes (16)
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

            // Seasonal and operational themes (9)
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
    }

    /**
     * Get human-readable labels for themes.
     *
     * Used in prompts and UI to display theme names in a user-friendly format.
     *
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            'quiz' => 'Quiz de Recomendação',
            'frete_gratis' => 'Frete Grátis',
            'fidelidade' => 'Programa de Fidelidade',
            'kits' => 'Kits e Combos',
            'estoque' => 'Gestão de Estoque',
            'email' => 'Email Marketing',
            'video' => 'Conteúdo em Vídeo',
            'assinatura' => 'Modelo de Assinatura',
            'cupom' => 'Cupons e Descontos',
            'checkout' => 'Otimização de Checkout',
            'whatsapp' => 'Atendimento via WhatsApp',
            'reviews' => 'Reviews e UGC',
            'pos_compra' => 'Pós-Compra',
            'influenciadores' => 'Marketing de Influenciadores',
            'gamificacao' => 'Gamificação',
            'conteudo' => 'Hub de Conteúdo',
            'carnaval' => 'Campanha de Carnaval',
            'ticket' => 'Aumento de Ticket Médio',
            'cancelamento' => 'Redução de Cancelamento',
            'reativacao' => 'Reativação de Clientes',
            'upsell' => 'Upsell',
            'cross_sell' => 'Cross-sell',
            'preco' => 'Estratégia de Precificação',
            'seo' => 'SEO',
            'remarketing' => 'Remarketing',
        ];
    }

    /**
     * Get the total number of themes.
     */
    public static function count(): int
    {
        return count(self::all());
    }

    /**
     * Check if a theme exists.
     */
    public static function exists(string $theme): bool
    {
        return array_key_exists($theme, self::all());
    }

    /**
     * Get keywords for a specific theme.
     *
     * @return array<string>|null
     */
    public static function getKeywords(string $theme): ?array
    {
        return self::all()[$theme] ?? null;
    }

    /**
     * Get label for a specific theme.
     */
    public static function getLabel(string $theme): ?string
    {
        return self::labels()[$theme] ?? null;
    }
}
