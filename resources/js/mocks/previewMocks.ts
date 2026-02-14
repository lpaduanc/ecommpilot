/**
 * Mock data para Preview Mode
 *
 * Dados realistas e atrativos para demonstrar funcionalidades
 * sem que o cliente precise ter dados reais na loja.
 */

import type { DashboardStats, RevenueChartData, OrdersStatusData, TopProduct } from '@/types/dashboard'
import type { Analysis, Suggestion, PremiumSummary } from '@/types/analysis'
import type { ChatMessage } from '@/types/chat'

// ============================================
// DASHBOARD MOCKS
// ============================================

export const mockDashboardStats: DashboardStats = {
    total_revenue: 45890.50,
    revenue_change: 12.5,
    total_orders: 234,
    orders_change: 8.3,
    total_products: 156,
    total_customers: 189,
    customers_change: 15.2,
    average_ticket: 196.15,
    ticket_change: 3.8,
    conversion_rate: 3.45,
    conversion_change: 1.2,
}

export const mockRevenueChart: RevenueChartData = {
    labels: [
        'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom',
        'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom',
    ],
    datasets: [{
        label: 'Receita',
        data: [
            3200, 2800, 3500, 4200, 3800, 5600, 6200,
            3400, 3100, 3900, 4500, 4100, 5800, 6500,
        ],
    }],
}

export const mockOrdersStatusChart: OrdersStatusData = {
    labels: ['Pago', 'Pendente', 'Cancelado', 'Devolvido'],
    datasets: [{
        data: [180, 35, 15, 4],
        backgroundColor: [
            'rgba(34, 197, 94, 0.8)',   // success
            'rgba(59, 130, 246, 0.8)',  // primary
            'rgba(239, 68, 68, 0.8)',   // danger
            'rgba(156, 163, 175, 0.8)', // gray
        ],
    }],
}

export const mockTopProducts: TopProduct[] = [
    {
        id: 1,
        name: 'Camiseta Premium Cotton',
        image: 'https://via.placeholder.com/80x80/4F46E5/FFFFFF?text=CP',
        sales: 87,
        revenue: 7830.00,
    },
    {
        id: 2,
        name: 'Tênis Esportivo Pro Runner',
        image: 'https://via.placeholder.com/80x80/06B6D4/FFFFFF?text=TE',
        sales: 62,
        revenue: 12400.00,
    },
    {
        id: 3,
        name: 'Relógio Smart Watch Elite',
        image: 'https://via.placeholder.com/80x80/8B5CF6/FFFFFF?text=RS',
        sales: 45,
        revenue: 13500.00,
    },
    {
        id: 4,
        name: 'Mochila Executiva Couro',
        image: 'https://via.placeholder.com/80x80/EC4899/FFFFFF?text=ME',
        sales: 38,
        revenue: 5320.00,
    },
    {
        id: 5,
        name: 'Óculos de Sol Polarizado',
        image: 'https://via.placeholder.com/80x80/F59E0B/FFFFFF?text=OS',
        sales: 34,
        revenue: 4080.00,
    },
]

export const mockLowStockProducts = [
    { id: 1, name: 'Camiseta Premium Cotton', stock: 3, sku: 'CPC-001' },
    { id: 2, name: 'Tênis Esportivo Pro Runner', stock: 5, sku: 'TEPR-002' },
    { id: 3, name: 'Relógio Smart Watch Elite', stock: 2, sku: 'RSWE-003' },
]

// ============================================
// ANALYSIS MOCKS
// ============================================

export const mockAnalysisSummary = {
    health_score: 78,
    trends: {
        revenue: 'up',
        orders: 'up',
        conversion: 'stable',
    },
    key_insights: [
        'Suas vendas aumentaram 12.5% nos últimos 15 dias',
        'Taxa de conversão está acima da média do mercado (3.45%)',
        'Ticket médio cresceu 3.8% - seus clientes estão comprando mais',
    ],
}

export const mockSuggestions: Suggestion[] = [
    // High Priority
    {
        id: 1,
        analysis_id: 1,
        category: 'inventory',
        title: 'Crítico: 3 produtos com estoque baixo',
        description: 'Você tem produtos populares com menos de 5 unidades em estoque. Isso pode resultar em vendas perdidas.',
        recommended_action: 'Reponha imediatamente: Camiseta Premium Cotton (3 un.), Relógio Smart Watch Elite (2 un.), e Tênis Pro Runner (5 un.). Considerando seu histórico de vendas, estes produtos vendem em média 15 unidades/semana.',
        expected_impact: 'high',
        priority: 1,
        status: 'new',
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
    },
    {
        id: 2,
        analysis_id: 1,
        category: 'marketing',
        title: 'Oportunidade: Criar campanha de recuperação de carrinho',
        description: 'Detectamos 42 carrinhos abandonados nos últimos 7 dias, representando R$ 8.234 em vendas potenciais.',
        recommended_action: 'Configure automação de e-mail com cupom de 10% de desconto para carrinhos abandonados há mais de 2 horas. Taxa de conversão média: 15-20%.',
        expected_impact: 'high',
        priority: 2,
        status: 'new',
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
    },
    {
        id: 3,
        analysis_id: 1,
        category: 'pricing',
        title: 'Ajuste de preço: Relógio Smart Watch Elite',
        description: 'Este produto tem alta demanda (45 vendas) mas margem abaixo do ideal. Um pequeno ajuste pode aumentar lucro significativamente.',
        recommended_action: 'Aumente o preço de R$ 299 para R$ 319 (6.7%). Análise de elasticidade sugere manter 90% das vendas com 21% mais lucro.',
        expected_impact: 'high',
        priority: 3,
        status: 'new',
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
    },

    // Medium Priority
    {
        id: 4,
        analysis_id: 1,
        category: 'product',
        title: 'Otimizar descrições de produtos',
        description: '23% dos seus produtos têm descrições com menos de 100 caracteres, o que pode prejudicar SEO e conversão.',
        recommended_action: 'Expanda descrições para pelo menos 300 caracteres, incluindo benefícios, especificações técnicas e palavras-chave relevantes.',
        expected_impact: 'medium',
        priority: 4,
        status: 'new',
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
    },
    {
        id: 5,
        analysis_id: 1,
        category: 'customer',
        title: 'Programa de fidelidade para clientes recorrentes',
        description: 'Você tem 34 clientes que já compraram 3+ vezes. Um programa de fidelidade pode aumentar retenção em 25%.',
        recommended_action: 'Crie programa de pontos: 1 ponto a cada R$ 10 gastos. 100 pontos = R$ 10 de desconto. Envie email exclusivo para os 34 clientes VIP.',
        expected_impact: 'medium',
        priority: 5,
        status: 'new',
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
    },
    {
        id: 6,
        analysis_id: 1,
        category: 'conversion',
        title: 'Adicionar avaliações de clientes',
        description: 'Produtos com avaliações convertem 270% mais. Você tem 189 clientes que podem avaliar produtos.',
        recommended_action: 'Envie email 7 dias após entrega pedindo avaliação. Ofereça cupom de R$ 15 para próxima compra como incentivo.',
        expected_impact: 'medium',
        priority: 6,
        status: 'new',
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
    },

    // Low Priority
    {
        id: 7,
        analysis_id: 1,
        category: 'operational',
        title: 'Melhorar fotos de produtos',
        description: '15 produtos têm apenas 1 foto. Produtos com múltiplas imagens vendem 58% mais.',
        recommended_action: 'Adicione pelo menos 3 fotos por produto: frente, detalhe e uso/contexto. Use fundo branco e boa iluminação.',
        expected_impact: 'low',
        priority: 7,
        status: 'new',
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
    },
    {
        id: 8,
        analysis_id: 1,
        category: 'marketing',
        title: 'Criar conteúdo para redes sociais',
        description: 'Presença social aumenta reconhecimento de marca e tráfego orgânico em até 40%.',
        recommended_action: 'Poste 3x/semana no Instagram: segunda (produto), quarta (dica/tutorial), sexta (promoção). Use seus produtos mais vendidos.',
        expected_impact: 'low',
        priority: 8,
        status: 'new',
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
    },
    {
        id: 9,
        analysis_id: 1,
        category: 'coupon',
        title: 'Cupom de boas-vindas para novos clientes',
        description: 'Cupons de primeira compra aumentam conversão de visitantes em 35%.',
        recommended_action: 'Crie popup com cupom "BEMVINDO10" (10% off, mínimo R$ 100). Exiba após 30 segundos de navegação.',
        expected_impact: 'low',
        priority: 9,
        status: 'new',
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
    },
]

export const mockCurrentAnalysis: Analysis = {
    id: 1,
    store_id: 1,
    status: 'completed',
    summary: mockAnalysisSummary,
    suggestions: mockSuggestions,
    created_at: new Date().toISOString(),
    updated_at: new Date().toISOString(),
    completed_at: new Date().toISOString(),
}

export const mockPremiumSummary: PremiumSummary = {
    executive_summary: {
        resumo_direto: {
            nao_precisa: 'vender mais barato',
            precisa: [
                'Vender com mais inteligencia',
                'Monetizar base existente',
                'Reduzir dependencia de cupom',
                'Trabalhar recorrencia',
                'Melhorar percepcao de valor',
            ],
            potencial_real: [
                'Assinatura',
                'Pos-compra',
                'Social Selling',
                'Personalizacao',
            ],
        },
        diagnostico_principal: 'Sua loja tem boa tracao com 234 pedidos/mes e ticket medio de R$ 196, mas opera com margem pressionada por dependencia de cupons (38% dos pedidos). O catalogo e concentrado nos 5 produtos top que representam 78% da receita.',
        maior_gargalo: 'Dependencia de cupons corroendo margem em 8-12% por pedido',
        maior_oportunidade: 'Cross-sell nos 87 compradores de Camiseta Premium Cotton pode gerar R$ 4.350/mes adicional',
        risco_mais_relevante: 'Ruptura de estoque nos 3 produtos mais vendidos pode causar perda de R$ 12.400/mes',
        potencial_crescimento_estimado_percentual: 25,
    },
    growth_score: {
        overall_score: 72,
        efficiency_score: 65,
        margin_health: 58,
        retention_score: 45,
        scale_readiness: 'Estruturada',
    },
    diagnostico_quantitativo: {
        ticket_medio_vs_benchmark: 'R$ 196 vs benchmark R$ 245 (-20%). Abaixo da media do segmento.',
        dependencia_desconto: '38% dos pedidos usam cupom. Media saudavel: <20%.',
        risco_margem: 'Desconto medio de 12% por pedido. Margem estimada comprimida em 8-12pp.',
        estrutura_catalogo: '156 produtos, mas top 5 = 78% da receita. Alta concentracao de risco.',
        potencial_retencao: 'Apenas 18% de recompra. Benchmark segmento: 30-35%.',
    },
    gaps_estrategicos: {
        dados_ausentes: ['Margem de lucro por produto', 'Custo de aquisicao (CAC)', 'Dados de trafego'],
        estruturais: ['Sem programa de fidelidade', 'Sem automacao de pos-venda'],
        operacionais: ['Estoque critico em 3 SKUs principais', 'Sem cross-sell configurado'],
        estrategicos: ['Dependencia de cupons para conversao', 'Ausencia de recorrencia'],
    },
    financial_opportunities: [
        {
            action: 'Aumentar ticket medio em R$ 30 via cross-sell/upsell',
            impact_type: 'ticket',
            estimated_monthly_impact: 7020,
            estimated_annual_impact: 84240,
        },
        {
            action: 'Reduzir uso de cupom de 38% para 20% dos pedidos',
            impact_type: 'margin',
            estimated_monthly_impact: 3600,
            estimated_annual_impact: 43200,
        },
        {
            action: 'Aumentar taxa de recompra de 18% para 28%',
            impact_type: 'retention',
            estimated_monthly_impact: 4590,
            estimated_annual_impact: 55080,
        },
        {
            action: 'Recuperar 15% dos carrinhos abandonados',
            impact_type: 'conversion',
            estimated_monthly_impact: 2470,
            estimated_annual_impact: 29640,
        },
    ],
    prioritized_roadmap: {
        '30_dias': [
            'Configurar automacao de carrinho abandonado (3 emails)',
            'Repor estoque dos 3 SKUs criticos',
            'Criar bundle com Camiseta Premium + acessorio',
            'Reduzir cupom BEMVINDO10 de 10% para 7%',
        ],
        '60_dias': [
            'Implementar programa de pontos basico',
            'Configurar cross-sell automatico no checkout',
            'Criar segmentacao de clientes VIP (3+ compras)',
            'Lancar campanha de reativacao para inativos 60d+',
        ],
        '90_dias': [
            'Lancar modelo de assinatura para produtos recorrentes',
            'Implementar precificacao dinamica baseada em demanda',
            'Criar fluxo de pos-compra com conteudo educativo',
            'Diversificar catalogo para reduzir concentracao nos top 5',
        ],
    },
    impact_effort_matrix: {
        quick_wins: [
            'Automacao de carrinho abandonado',
            'Reposicao de estoque critico',
            'Reducao do cupom de boas-vindas',
        ],
        high_impact: [
            'Programa de fidelidade/pontos',
            'Cross-sell automatico no checkout',
            'Modelo de assinatura',
        ],
        fill_ins: [
            'Melhorar descricoes de produtos',
            'Adicionar avaliacoes de clientes',
            'Conteudo para redes sociais',
        ],
        avoid: [
            'Aumentar catalogo sem demanda validada',
            'Criar mais cupons de desconto',
        ],
    },
    growth_scenarios: {
        conservador: {
            crescimento_percentual: 10,
            receita_mensal_projetada: 50479,
            receita_anual_projetada: 605753,
            o_que_precisa_melhorar: 'Corrigir estoque critico e implementar automacao basica de carrinho abandonado.',
        },
        base: {
            crescimento_percentual: 25,
            receita_mensal_projetada: 57363,
            receita_anual_projetada: 688356,
            o_que_precisa_melhorar: 'Implementar cross-sell, reduzir dependencia de cupom e aumentar recompra para 25%.',
        },
        agressivo: {
            crescimento_percentual: 50,
            receita_mensal_projetada: 68835,
            receita_anual_projetada: 826027,
            o_que_precisa_melhorar: 'Tudo acima + assinatura, precificacao dinamica e diversificacao de catalogo.',
        },
    },
    strategic_risks: [
        'Ruptura de estoque nos 3 principais SKUs pode causar queda de 25% na receita',
        'Dependencia excessiva de cupons esta criando cultura de desconto nos clientes',
        'Concentracao em 5 produtos (78% receita) cria vulnerabilidade a mudancas de demanda',
    ],
    final_verdict: {
        conclusao_estrategica: 'A loja tem boa tracao e base de clientes, mas opera abaixo do potencial por falta de automacao, excesso de cupons e ausencia de estrategia de retencao. O foco deve ser monetizar a base existente antes de buscar novos clientes.',
        current_stage: 'Estruturada',
        next_stage_requirement: 'Implementar automacoes de marketing (carrinho abandonado, pos-venda, segmentacao) e programa de fidelidade para atingir estagio Escalavel.',
    },
}

export const mockAnalysisAlerts = [
    {
        id: 1,
        type: 'warning',
        title: 'Estoque Crítico',
        message: '3 produtos precisam de reposição urgente',
    },
    {
        id: 2,
        type: 'info',
        title: 'Oportunidade de Crescimento',
        message: 'R$ 8.234 em carrinhos abandonados podem ser recuperados',
    },
]

export const mockOpportunities = [
    {
        id: 1,
        title: 'Recuperação de Carrinho Abandonado',
        description: 'Automatize emails para recuperar R$ 8.234 em vendas perdidas',
        potential_revenue: 8234.00,
        impact_score: 85,
    },
    {
        id: 2,
        title: 'Upsell e Cross-sell',
        description: 'Produtos frequentemente comprados juntos podem aumentar ticket médio em 18%',
        potential_revenue: 5600.00,
        impact_score: 72,
    },
]

// ============================================
// CHAT MOCKS
// ============================================

export const mockChatMessages: ChatMessage[] = [
    {
        id: 1,
        role: 'assistant',
        content: 'Olá! 👋 Sou seu Assistente de IA especializado em e-commerce. Posso ajudar você a:\n\n• Analisar suas vendas e métricas\n• Entender tendências do mercado\n• Otimizar produtos e preços\n• Criar estratégias de marketing\n• Responder dúvidas sobre sua loja\n\nComo posso ajudar você hoje?',
        timestamp: new Date(Date.now() - 5 * 60000).toISOString(),
    },
    {
        id: 2,
        role: 'user',
        content: 'Qual meu produto mais vendido e como posso aumentar as vendas dele?',
        timestamp: new Date(Date.now() - 4 * 60000).toISOString(),
    },
    {
        id: 3,
        role: 'assistant',
        content: 'Seu produto mais vendido é o **Relógio Smart Watch Elite** com 45 vendas e R$ 13.500 em receita nos últimos 15 dias! 🎯\n\n**Estratégias para aumentar vendas:**\n\n1. **Bundle/Combo** - Crie kit com pulseira extra (+R$ 49) ou película protetora (+R$ 29)\n2. **Urgência** - "Apenas 2 unidades em estoque!" (você realmente está com estoque baixo)\n3. **Social Proof** - Destaque avaliações: "⭐ 4.8/5 - 45 clientes aprovam"\n4. **Upsell** - Ofereça modelo premium (+R$ 100) no checkout\n5. **Email Marketing** - Envie para os 155 clientes que não compraram ainda\n\n**Projeção:** Estas ações podem gerar +15 vendas/mês = +R$ 4.500 💰',
        timestamp: new Date(Date.now() - 3 * 60000).toISOString(),
    },
    {
        id: 4,
        role: 'user',
        content: 'E sobre carrinhos abandonados? Como recuperar essas vendas?',
        timestamp: new Date(Date.now() - 2 * 60000).toISOString(),
    },
    {
        id: 5,
        role: 'assistant',
        content: 'Excelente pergunta! Você tem **42 carrinhos abandonados** = R$ 8.234 em vendas potenciais! 🛒\n\n**Estratégia de Recuperação (3 emails):**\n\n**Email 1** (2h depois):\n- Assunto: "Ops! Esqueceu algo? 🛍️"\n- Lembrete amigável + imagens dos produtos\n\n**Email 2** (24h depois):\n- Assunto: "Última chance: 10% OFF no seu carrinho! 🎁"\n- Cupom exclusivo: VOLTA10\n- Validade: 48h\n\n**Email 3** (72h depois):\n- Assunto: "Seu carrinho expira em breve! ⏰"\n- Urgência + depoimentos de clientes\n\n**Resultado esperado:**\n- Taxa de recuperação: 15-20%\n- Vendas recuperadas: R$ 1.235 - R$ 1.647\n- ROI: 300-400%\n\nQuer que eu ajude a criar os textos dos emails? ✍️',
        timestamp: new Date(Date.now() - 1 * 60000).toISOString(),
    },
]

// ============================================
// INTEGRATIONS MOCKS
// ============================================

export const mockIntegrationStore = {
    id: 1,
    name: 'Minha Loja Demo',
    domain: 'minhalojademo.lojavirtualnuvem.com.br',
    platform: 'nuvemshop',
    sync_status: 'completed',
    last_sync_at: new Date(Date.now() - 30 * 60000).toISOString(), // 30 min atrás
    created_at: new Date(Date.now() - 7 * 24 * 60 * 60000).toISOString(), // 7 dias atrás
}

export const mockTrackingSettings = {
    ga: {
        enabled: true,
        measurement_id: 'G-XXXXXXXXXX',
    },
    meta_pixel: {
        enabled: true,
        pixel_id: '123456789012345',
    },
    clarity: {
        enabled: false,
        project_id: '',
    },
    hotjar: {
        enabled: false,
        site_id: '',
    },
}

// ============================================
// HELPER FUNCTIONS
// ============================================

/**
 * Verifica se deve usar dados mockados (em preview mode)
 */
export function shouldUseMockData(isInPreviewMode: boolean, hasRealData: boolean): boolean {
    return isInPreviewMode && !hasRealData
}

/**
 * Retorna dados mockados ou reais baseado no preview mode
 */
export function getMockOrReal<T>(isInPreviewMode: boolean, hasRealData: boolean, mockData: T, realData: T): T {
    return shouldUseMockData(isInPreviewMode, hasRealData) ? mockData : realData
}
