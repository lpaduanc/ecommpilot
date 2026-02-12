<?php

namespace App\Services\AI;

use App\DTOs\AnalysisModuleConfig;

class AnalysisRouter
{
    /**
     * Resolve o tipo de análise para sua configuração de módulo.
     * Para "general" e tipos não implementados, retorna config vazia (pipeline inalterada).
     */
    public function resolve(string $analysisType): AnalysisModuleConfig
    {
        return match ($analysisType) {
            'financial' => $this->financialConfig(),
            'conversion' => $this->conversionConfig(),
            'competitors' => $this->competitorsConfig(),
            default => AnalysisModuleConfig::general(),
        };
    }

    private function financialConfig(): AnalysisModuleConfig
    {
        return new AnalysisModuleConfig(
            analysisType: 'financial',
            isSpecialized: true,
            collectorFocus: [
                'dados_prioridade' => 'faturamento, margem bruta, margem líquida, ticket médio, CAC, LTV, custo de frete, taxa de recompra',
                'metricas_obrigatorias' => ['faturamento_mensal', 'ticket_medio', 'margem', 'custo_aquisicao', 'ltv'],
            ],
            analystKeywords: [
                'keywords' => 'margem, ticket médio, CAC, LTV, faturamento, pricing, custo de frete, sazonalidade financeira, mix de produtos por rentabilidade, custo operacional',
                'foco_analise' => 'Concentre sua análise em saúde financeira e oportunidades de otimização de pricing e margem. Identifique produtos com melhor e pior margem.',
            ],
            strategistConfig: [
                'foco' => 'otimização financeira, pricing, margem e redução de custos',
                'exemplo_bom' => 'Implemente pricing dinâmico para os 5 produtos com margem acima de 40% (identificados nos dados: Produto A 45%, Produto B 42%...). Lojas de {nicho} que usam bundles estratégicos aumentam ticket médio em 12-18%. Sugestão: crie kit do Produto A + Produto C (margem combinada de 38%) por R$X, projetando aumento de ticket médio de R$185 para R$215.',
                'exemplo_ruim' => 'Aumente seus preços para melhorar a margem.',
            ],
            criticConfig: [
                'criterios_extras' => 'Valide que toda sugestão financeira cita números reais da loja. Verifique se cálculos de margem, ticket médio e projeções de receita estão matematicamente corretos. Rejeite sugestões de pricing que não consideram o posicionamento competitivo do nicho.',
            ],
            temperatureOverride: null,
        );
    }

    private function conversionConfig(): AnalysisModuleConfig
    {
        return new AnalysisModuleConfig(
            analysisType: 'conversion',
            isSpecialized: true,
            collectorFocus: [
                'dados_prioridade' => 'taxa de conversão geral, conversão por dispositivo (mobile/desktop), taxa de abandono de carrinho, etapas do funil, bounce rate, páginas de saída, velocidade de carregamento',
                'metricas_obrigatorias' => ['taxa_conversao', 'taxa_abandono_carrinho', 'visitantes_mobile_vs_desktop', 'bounce_rate'],
            ],
            analystKeywords: [
                'keywords' => 'conversão, abandono de carrinho, funil de vendas, checkout, página de produto, UX, mobile, velocidade, bounce rate, CTAs, formulários, navegação',
                'foco_analise' => 'Concentre sua análise nos pontos de fricção do funil de conversão. Identifique onde os visitantes estão abandonando e por quê. Compare performance mobile vs desktop.',
            ],
            strategistConfig: [
                'foco' => 'otimização de conversão, redução de abandono, melhoria de UX e checkout',
                'exemplo_bom' => 'Simplifique o checkout removendo o campo "empresa" (dados mostram que 94% dos clientes são PF). Sua taxa de abandono é 73%, 15 pontos acima do benchmark de {nicho} (~58%). Passos: 1) Remova campos opcionais do checkout 2) Implemente preenchimento automático de endereço via CEP 3) Adicione indicador de progresso. Meta: reduzir abandono para 60% = ~25 vendas adicionais/mês.',
                'exemplo_ruim' => 'Melhore a experiência de checkout para converter mais clientes.',
            ],
            criticConfig: [
                'criterios_extras' => 'Valide que sugestões de conversão incluem passos de implementação concretos. Verifique se taxas citadas conferem com os dados coletados. Rejeite sugestões que não especificam onde no funil atuam (topo/meio/fundo).',
            ],
            temperatureOverride: null,
        );
    }

    private function competitorsConfig(): AnalysisModuleConfig
    {
        return new AnalysisModuleConfig(
            analysisType: 'competitors',
            isSpecialized: true,
            collectorFocus: [
                'dados_prioridade' => 'dados de concorrentes (preços, diferenciais, categorias, promoções, avaliações, catálogo), posicionamento de preço da loja vs mercado, gaps de features entre loja e concorrentes, oportunidades de categorias não exploradas',
                'metricas_obrigatorias' => [
                    'ticket_medio_vs_concorrentes',
                    'categorias_overlap',
                    'diferenciais_ausentes',
                    'comparativo_promocoes',
                    'faixa_preco_mercado',
                ],
            ],
            analystKeywords: [
                'keywords' => 'posicionamento competitivo, diferenciais ausentes, vantagens competitivas, gaps de mercado, pricing competitivo, overlap de categorias, market share estimado, proposta de valor única, ameaças competitivas, oportunidades de diferenciação',
                'foco_analise' => 'Realize análise competitiva dimensional: (1) PREÇO - compare ticket médio, faixa de preços e estratégia de pricing vs cada concorrente; (2) PRODUTO - identifique categorias que concorrentes exploram e a loja não, e vice-versa; (3) EXPERIÊNCIA - compare diferenciais (frete grátis, parcelamento, cashback, reviews, etc.); (4) PROMOÇÕES - compare quantidade e tipo de promoções ativas. Para cada dimensão, classifique a posição da loja como ACIMA, PAR ou ABAIXO da concorrência.',
            ],
            strategistConfig: [
                'foco' => 'vantagem competitiva, diferenciação de mercado, posicionamento único e exploração de gaps identificados nos concorrentes',
                'exemplo_bom' => 'Concorrente "Beleza Natural" oferece quiz personalizado (nota 4.8/5, 230 reviews) e a sua loja não tem essa feature. Implemente quiz de 8 perguntas usando seus 84 produtos ativos, categorizando por tipo de pele/cabelo. Dados do concorrente mostram ticket de R$ 259 vs seu R$ 185. Meta realista: aumentar ticket para R$ 220 (+19%) via recomendação personalizada = ~R$ 2.100/mês adicional com base nos seus 60 pedidos/mês.',
                'exemplo_ruim' => 'Copie o que os concorrentes fazem para não ficar para trás.',
            ],
            criticConfig: [
                'criterios_extras' => 'REGRAS OBRIGATÓRIAS para análise de concorrentes: (1) Toda sugestão de prioridade HIGH deve citar pelo menos 1 concorrente PELO NOME com dado numérico específico (preço, nota, quantidade). (2) Verifique que os dados citados sobre concorrentes conferem com a seção <dados_concorrentes> e <comparativo_concorrentes> do input. (3) Rejeite sugestões genéricas que dizem "concorrentes fazem X" sem citar nome e número. (4) Valide que a sugestão propõe DIFERENCIAÇÃO, não cópia - a loja deve criar vantagem própria, não replicar o concorrente. (5) Se não há dados de concorrentes disponíveis, rebaixe sugestões competitivas para prioridade LOW.',
            ],
            temperatureOverride: null,
        );
    }
}
