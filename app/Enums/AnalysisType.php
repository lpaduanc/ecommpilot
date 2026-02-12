<?php

namespace App\Enums;

enum AnalysisType: string
{
    case General = 'general';
    case Financial = 'financial';
    case Conversion = 'conversion';
    case Competitors = 'competitors';
    case Campaigns = 'campaigns';
    case Tracking = 'tracking';

    public function label(): string
    {
        return match ($this) {
            self::General => 'Análise Geral',
            self::Financial => 'Análise Financeira',
            self::Conversion => 'Análise de Conversão',
            self::Competitors => 'Análise de Concorrentes',
            self::Campaigns => 'Análise de Campanhas',
            self::Tracking => 'Análise de Tracking',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::General => 'Visão completa da loja com recomendações gerais',
            self::Financial => 'Foco em margem, ticket médio, CAC, LTV e pricing',
            self::Conversion => 'Foco em taxa de conversão, checkout, UX e funil',
            self::Competitors => 'Análise competitiva: posicionamento, diferenciais e gaps vs concorrentes',
            self::Campaigns => 'Foco em ROAS, CPC, CTR e performance de anúncios',
            self::Tracking => 'Foco em logística, entrega e rastreamento',
        };
    }

    public function available(): bool
    {
        return match ($this) {
            self::General, self::Financial, self::Conversion, self::Competitors => true,
            self::Campaigns, self::Tracking => false,
        };
    }

    public function isDefault(): bool
    {
        return $this === self::General;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get only the available (implemented) types.
     */
    public static function availableTypes(): array
    {
        return array_filter(self::cases(), fn (self $type) => $type->available());
    }

    /**
     * Get all types formatted for API response.
     */
    public static function toApiArray(): array
    {
        return array_map(fn (self $type) => [
            'key' => $type->value,
            'label' => $type->label(),
            'description' => $type->description(),
            'available' => $type->available(),
            'is_default' => $type->isDefault(),
        ], self::cases());
    }
}
