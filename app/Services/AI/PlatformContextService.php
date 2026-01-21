<?php

namespace App\Services\AI;

class PlatformContextService
{
    /**
     * Get the full platform context/configuration.
     */
    public function getContext(string $platform): array
    {
        $platforms = config('platforms');

        return $platforms[$platform] ?? $platforms['nuvemshop']; // fallback to Nuvemshop
    }

    /**
     * Get implementation details for a specific resource on a platform.
     */
    public function getResourceImplementation(string $platform, string $resource): array
    {
        $context = $this->getContext($platform);

        return $context['recursos'][$resource] ?? [
            'disponivel' => false,
            'como_fazer' => 'Recurso não disponível nesta plataforma',
        ];
    }

    /**
     * Format all platform resources for inclusion in prompts.
     */
    public function formatResourcesForPrompt(string $platform): string
    {
        $context = $this->getContext($platform);
        $output = "## Recursos disponíveis em {$context['nome']}:\n\n";
        $output .= "**Complexidade geral da plataforma:** {$context['complexidade_geral']}\n";
        $output .= '**Possui App Store:** '.($context['possui_app_store'] ? 'Sim' : 'Não')."\n";
        $output .= "**Custo médio de apps:** {$context['custo_apps_medio']}\n\n";

        foreach ($context['recursos'] as $nome => $recurso) {
            $nomeFormatado = $this->formatResourceName($nome);
            $output .= "### {$nomeFormatado}\n";
            $output .= '- **Disponível:** '.($recurso['disponivel'] ? 'Sim' : 'Não')."\n";
            $output .= '- **Nativo:** '.($recurso['nativo'] ? 'Sim (incluso na plataforma)' : 'Não (requer app/integração)')."\n";

            if (! $recurso['nativo'] && isset($recurso['app_recomendado'])) {
                $output .= "- **App recomendado:** {$recurso['app_recomendado']}\n";
            }

            $output .= "- **Como fazer:** {$recurso['como_fazer']}\n";
            $output .= "- **Complexidade:** {$recurso['complexidade']}\n";
            $output .= "- **Tempo estimado:** {$recurso['tempo_estimado']}\n";

            if (isset($recurso['custo'])) {
                $output .= "- **Custo:** {$recurso['custo']}\n";
            }

            $output .= "\n";
        }

        return $output;
    }

    /**
     * Format resource name for display.
     */
    private function formatResourceName(string $name): string
    {
        $names = [
            'kits' => 'Kits/Combos',
            'assinatura' => 'Assinatura/Recorrência',
            'avise_me' => 'Avise-me (Back in Stock)',
            'cupom' => 'Cupons de Desconto',
            'frete_gratis_condicional' => 'Frete Grátis Condicional',
            'email_carrinho_abandonado' => 'Email de Carrinho Abandonado',
            'quiz_personalizado' => 'Quiz Personalizado',
        ];

        return $names[$name] ?? ucfirst(str_replace('_', ' ', $name));
    }

    /**
     * Get a summary of platform capabilities for quick reference.
     */
    public function getPlatformSummary(string $platform): array
    {
        $context = $this->getContext($platform);
        $nativeCount = 0;
        $appCount = 0;

        foreach ($context['recursos'] as $recurso) {
            if ($recurso['disponivel']) {
                if ($recurso['nativo']) {
                    $nativeCount++;
                } else {
                    $appCount++;
                }
            }
        }

        return [
            'nome' => $context['nome'],
            'complexidade' => $context['complexidade_geral'],
            'recursos_nativos' => $nativeCount,
            'recursos_via_app' => $appCount,
            'total_recursos' => $nativeCount + $appCount,
        ];
    }

    /**
     * Check if a resource is available natively on a platform.
     */
    public function isNativeResource(string $platform, string $resource): bool
    {
        $resourceConfig = $this->getResourceImplementation($platform, $resource);

        return $resourceConfig['disponivel'] && ($resourceConfig['nativo'] ?? false);
    }

    /**
     * Get recommended apps for a resource on a platform.
     */
    public function getRecommendedApp(string $platform, string $resource): ?string
    {
        $resourceConfig = $this->getResourceImplementation($platform, $resource);

        return $resourceConfig['app_recomendado'] ?? null;
    }

    /**
     * Get implementation complexity for a resource.
     */
    public function getResourceComplexity(string $platform, string $resource): string
    {
        $resourceConfig = $this->getResourceImplementation($platform, $resource);

        return $resourceConfig['complexidade'] ?? 'desconhecida';
    }

    /**
     * Format a specific resource implementation for prompts.
     */
    public function formatResourceForPrompt(string $platform, string $resource): string
    {
        $context = $this->getContext($platform);
        $recurso = $context['recursos'][$resource] ?? null;

        if (! $recurso) {
            return "Recurso '{$resource}' não disponível em {$context['nome']}.";
        }

        $nomeFormatado = $this->formatResourceName($resource);
        $output = "### Implementação de {$nomeFormatado} em {$context['nome']}\n";
        $output .= '- **Tipo:** '.($recurso['nativo'] ? 'Nativo' : 'Via App')."\n";

        if (! $recurso['nativo'] && isset($recurso['app_recomendado'])) {
            $output .= "- **App recomendado:** {$recurso['app_recomendado']}\n";
        }

        $output .= "- **Complexidade:** {$recurso['complexidade']}\n";
        $output .= "- **Tempo estimado:** {$recurso['tempo_estimado']}\n";

        if (isset($recurso['custo'])) {
            $output .= "- **Custo:** {$recurso['custo']}\n";
        }

        $output .= "- **Como fazer:** {$recurso['como_fazer']}\n";

        return $output;
    }
}
