<?php

namespace App\DTOs;

/**
 * DTO para configuração de módulo de análise especializada.
 * Contém configs adicionais por agente, injetadas condicionalmente nos prompts.
 */
readonly class AnalysisModuleConfig
{
    public function __construct(
        public string $analysisType,
        public bool $isSpecialized,
        public array $collectorFocus,
        public array $analystKeywords,
        public array $strategistConfig,
        public array $criticConfig,
        public ?float $temperatureOverride = null,
    ) {}

    /**
     * Config padrão para análise geral ou tipos não implementados.
     * isSpecialized = false → nenhuma injeção extra nos prompts.
     */
    public static function general(): self
    {
        return new self(
            analysisType: 'general',
            isSpecialized: false,
            collectorFocus: [],
            analystKeywords: [],
            strategistConfig: [],
            criticConfig: [],
            temperatureOverride: null,
        );
    }

    public function toArray(): array
    {
        return [
            'analysis_type' => $this->analysisType,
            'is_specialized' => $this->isSpecialized,
            'collector_focus' => $this->collectorFocus,
            'analyst_keywords' => $this->analystKeywords,
            'strategist_config' => $this->strategistConfig,
            'critic_config' => $this->criticConfig,
            'temperature_override' => $this->temperatureOverride,
        ];
    }
}
