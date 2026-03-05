<script setup lang="ts">
import BaseModal from '../common/BaseModal.vue';
import { computed } from 'vue';

const props = defineProps<{
    show: boolean;
    section: string;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
}>();

const sectionTitles: Record<string, string> = {
    'health-score': 'Saúde da Loja (Health Score)',
    'strategic-summary': 'Resumo Estratégico — Como Ler',
    'insight-cards': 'Cards de Insights (Gargalo, Oportunidade, Risco)',
    'growth-scores': 'Scores de Crescimento',
    'action-plan-90': 'Plano de Ação 90 Dias',
    'financial-impact': 'Impacto Financeiro — Potenciais e Cálculos',
    'growth-scenarios': 'Cenários de Crescimento',
    'alerts': 'Alertas',
    'opportunities': 'Oportunidades',
    'strategic-suggestions': 'Sugestões Estratégicas',
    'priority-levels': 'Níveis de Prioridade (Alta, Média, Baixa)',
};

const title = computed(() => sectionTitles[props.section] || 'Guia da Análise');
</script>

<template>
    <BaseModal
        :show="show"
        :title="title"
        size="2xl"
        @close="emit('close')"
    >
        <div>
            <div class="prose prose-sm dark:prose-invert max-w-none">

                <!-- HEALTH SCORE -->
                <template v-if="section === 'health-score'">
                    <p>
                        O Health Score é um indicador de <strong>0 a 100</strong> que resume se a loja está com a "base" pronta para crescer.
                        Ele não mede apenas vendas — ele mede <strong>saúde operacional e eficiência</strong>.
                    </p>

                    <h4>Como o score é calculado</h4>
                    <p>O Health Score é composto por <strong>5 componentes com pesos definidos</strong>, somando até 100 pontos:</p>
                    <table>
                        <thead>
                            <tr>
                                <th>Componente</th>
                                <th>Peso</th>
                                <th>Como pontua</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>Ticket vs Benchmark</strong></td>
                                <td>25 pts</td>
                                <td>Compara seu ticket médio com o benchmark do nicho. ≥100% do benchmark = 25 pts; 80-99% = 20 pts; 60-79% = 15 pts; &lt;60% = 10 pts</td>
                            </tr>
                            <tr>
                                <td><strong>Estoque disponível</strong></td>
                                <td>25 pts</td>
                                <td>Mede % de produtos com estoque zerado. ≤10% zerado = 25 pts; 11-20% = 20 pts; 21-35% = 15 pts; &gt;35% = 10 pts</td>
                            </tr>
                            <tr>
                                <td><strong>Taxa de cancelamento</strong></td>
                                <td>15 pts</td>
                                <td>Percentual de pedidos cancelados. ≤3% = 15 pts; 4-7% = 12 pts; 8-12% = 8 pts; &gt;12% = 4 pts</td>
                            </tr>
                            <tr>
                                <td><strong>Saúde de cupons</strong></td>
                                <td>15 pts</td>
                                <td>Uso de cupons &lt;50% das vendas E impacto &lt;15% no ticket = 15 pts; acima disso, pontuação proporcional</td>
                            </tr>
                            <tr>
                                <td><strong>Tendência de vendas</strong></td>
                                <td>20 pts</td>
                                <td>Compara com período anterior. Crescendo = 20 pts; Estável = 15 pts; Queda leve = 10 pts; Queda forte = 5 pts</td>
                            </tr>
                        </tbody>
                    </table>

                    <h4>Sistema de penalidades (overrides)</h4>
                    <p>Após calcular o score base, o sistema aplica <strong>penalidades graduais</strong> para situações graves. As penalidades são cumulativas:</p>
                    <table>
                        <thead>
                            <tr>
                                <th>Situação</th>
                                <th>Faixa</th>
                                <th>Penalidade</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td rowspan="4"><strong>Estoque zerado</strong></td>
                                <td>20-30% dos produtos</td>
                                <td>-10 pontos</td>
                            </tr>
                            <tr>
                                <td>30-40%</td>
                                <td>-20 pontos (mínimo 30)</td>
                            </tr>
                            <tr>
                                <td>40-50%</td>
                                <td>-30 pontos (mínimo 20)</td>
                            </tr>
                            <tr>
                                <td>&gt;50%</td>
                                <td>Score forçado para máximo 15 (crítico)</td>
                            </tr>
                            <tr>
                                <td rowspan="3"><strong>Cancelamento</strong></td>
                                <td>8-12%</td>
                                <td>-10 pontos</td>
                            </tr>
                            <tr>
                                <td>12-18%</td>
                                <td>-20 pontos (mínimo 25)</td>
                            </tr>
                            <tr>
                                <td>&gt;18%</td>
                                <td>Score forçado para máximo 15 (crítico)</td>
                            </tr>
                            <tr>
                                <td rowspan="3"><strong>Queda de vendas</strong></td>
                                <td>20-30% vs histórico</td>
                                <td>-5 pontos</td>
                            </tr>
                            <tr>
                                <td>30-45%</td>
                                <td>-15 pontos (mínimo 25)</td>
                            </tr>
                            <tr>
                                <td>&gt;45%</td>
                                <td>Score forçado para máximo 20 (crítico)</td>
                            </tr>
                            <tr>
                                <td rowspan="3"><strong>Dependência de cupom</strong></td>
                                <td>60-75% das vendas com cupom</td>
                                <td>-5 pontos</td>
                            </tr>
                            <tr>
                                <td>75-90%</td>
                                <td>-15 pontos (mínimo 30)</td>
                            </tr>
                            <tr>
                                <td>&gt;90%</td>
                                <td>-25 pontos (mínimo 20)</td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-4 rounded-lg not-prose my-4">
                        <p class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Exemplo de cálculo:</p>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Score base = 25 + 20 + 12 + 15 + 15 = <strong>87</strong><br>
                            Estoque 35% zerado → penalidade -20<br>
                            Cancelamento 10% → penalidade -10<br>
                            Score final = 87 - 20 - 10 = <strong>57 (Saudável)</strong>
                        </p>
                    </div>

                    <h4>O que significa o número</h4>
                    <table>
                        <thead>
                            <tr>
                                <th>Faixa</th>
                                <th>Leitura</th>
                                <th>O que fazer primeiro</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>0–25</strong></td>
                                <td class="text-red-600 dark:text-red-400 font-semibold">Crítico</td>
                                <td>Corrigir base (estoque, cancelamento, operação) antes de escalar marketing.</td>
                            </tr>
                            <tr>
                                <td><strong>26–50</strong></td>
                                <td class="text-amber-600 dark:text-amber-400 font-semibold">Atenção</td>
                                <td>Atacar os gargalos principais com ações de curto prazo + 1 ou 2 mudanças estruturais.</td>
                            </tr>
                            <tr>
                                <td><strong>51–75</strong></td>
                                <td class="text-blue-600 dark:text-blue-400 font-semibold">Saudável</td>
                                <td>Otimizar conversão e ticket, profissionalizar aquisição e começar a escalar com controle.</td>
                            </tr>
                            <tr>
                                <td><strong>76–100</strong></td>
                                <td class="text-emerald-600 dark:text-emerald-400 font-semibold">Excelente</td>
                                <td>Foco em crescimento: aquisição, retenção, expansão de mix e ganho de participação de mercado.</td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="bg-amber-50 dark:bg-amber-900/20 border-l-4 border-amber-500 p-4 rounded-r-lg not-prose">
                        <p class="text-sm text-gray-700 dark:text-gray-300">
                            <strong>Como usar:</strong> pense no Health Score como "prontidão para crescer". Se ele está baixo,
                            crescer com tráfego pago pode "vazar" (você paga para trazer gente, mas perde por estoque/checkout/cancelamento).
                        </p>
                    </div>
                </template>

                <!-- STRATEGIC SUMMARY -->
                <template v-if="section === 'strategic-summary'">
                    <p>
                        O Resumo Estratégico é a <strong>visão executiva</strong> da análise. Ele organiza a situação em tópicos fixos
                        para você entender, em poucos minutos, o que está travando o crescimento e o que fazer em seguida.
                    </p>

                    <h4>Como é construído</h4>
                    <p>O Resumo Estratégico é gerado por uma IA especializada em estratégia de e-commerce (o "Estrategista"). Ela recebe:</p>
                    <ul>
                        <li><strong>Dados reais da loja</strong> — pedidos, faturamento, ticket, estoque, cancelamentos, cupons dos últimos 15 dias</li>
                        <li><strong>Diagnóstico do Analista</strong> — os 5 principais problemas identificados com dados numéricos</li>
                        <li><strong>Benchmarks do nicho</strong> — referências de mercado para o seu segmento</li>
                        <li><strong>Dados de concorrentes</strong> — quando disponíveis, informações de preço e posicionamento</li>
                        <li><strong>Histórico de análises</strong> — sugestões anteriores para evitar repetições</li>
                    </ul>

                    <h4>O que contém</h4>
                    <ul>
                        <li><strong>Resumo Direto</strong> — "Você NÃO precisa..." / "Você precisa..." / "Potencial real..." (corta ruído e direciona foco)</li>
                        <li><strong>Growth Score</strong> — 4 notas (0-100) por área: Overall, Eficiência, Margem, Retenção</li>
                        <li><strong>Diagnóstico Quantitativo</strong> — Métricas comparadas com benchmarks</li>
                        <li><strong>Oportunidades Financeiras</strong> — Impacto estimado mensal e anual por tipo</li>
                        <li><strong>Roadmap 30/60/90 dias</strong> — Sequência do que fazer: quick wins → estruturação → escala</li>
                        <li><strong>Matriz Impacto × Esforço</strong> — Quick wins, High impact, Fill-ins, Avoid</li>
                        <li><strong>Cenários de Crescimento</strong> — Projeções conservadora, base e agressiva</li>
                        <li><strong>Veredito Final</strong> — Estágio atual e próximo degrau para avançar</li>
                    </ul>

                    <h4>Diagnóstico quantitativo — o que é comparado</h4>
                    <p>O diagnóstico usa uma <strong>tripla comparação</strong> para cada métrica:</p>
                    <ul>
                        <li><strong>Sua loja</strong> — o valor real dos seus dados</li>
                        <li><strong>Benchmark do nicho</strong> — média esperada para o seu segmento</li>
                        <li><strong>Concorrentes</strong> — quando disponível, média dos competidores identificados</li>
                    </ul>
                    <p>As 5 dimensões analisadas:</p>
                    <ul>
                        <li><strong>Ticket vs benchmark:</strong> se está abaixo, existe alavanca de valor percebido (kits, precificação, mix)</li>
                        <li><strong>Dependência de desconto:</strong> se cupom aparece demais, pode haver risco de margem e de "viciar" a compra</li>
                        <li><strong>Risco de margem:</strong> custos de desconto e frete grátis vs receita gerada</li>
                        <li><strong>Estrutura de catálogo:</strong> muitos itens sem estoque ou poucos produtos "vencedores" podem travar o crescimento</li>
                        <li><strong>Potencial de retenção:</strong> falta de pós-compra ou recorrência reduz LTV e aumenta custo para crescer</li>
                    </ul>

                    <h4>Níveis de confiança</h4>
                    <p>Cada informação no resumo tem um nível de confiança implícito:</p>
                    <ul>
                        <li><strong>Alta:</strong> baseada em dados diretos da loja (pedidos, estoque, ticket)</li>
                        <li><strong>Média:</strong> inferência lógica a partir dos dados (ex.: margem estimada)</li>
                        <li><strong>Baixa:</strong> estimativa ou benchmark genérico do setor — trate como hipótese para testar</li>
                    </ul>

                    <div class="bg-blue-50 dark:bg-blue-900/20 border-l-4 border-blue-500 p-4 rounded-r-lg not-prose">
                        <p class="text-sm text-gray-700 dark:text-gray-300">
                            <strong>Dica:</strong> use o Resumo Estratégico para alinhar expectativas, escolher onde investir energia e dinheiro
                            e entender o "porquê" por trás das sugestões.
                        </p>
                    </div>
                </template>

                <!-- INSIGHT CARDS -->
                <template v-if="section === 'insight-cards'">
                    <p>
                        Esta seção mostra <strong>3 cards</strong> que destacam os pontos mais críticos identificados pela IA.
                        Serve para cortar ruído e direcionar o foco nas decisões mais importantes.
                    </p>

                    <h4>Os 3 cards</h4>
                    <ul>
                        <li>
                            <strong>"Você NÃO precisa"</strong> — Ações que parecem intuitivas, mas não atacam o gargalo principal.
                            Exemplo: "Você NÃO precisa baixar preços". Se o gargalo é ruptura, baixar preços só aumenta a demanda por itens que você não consegue entregar.
                        </li>
                        <li>
                            <strong>"Você precisa"</strong> — 3 a 5 decisões estratégicas que movem o negócio. São as prioridades reais.
                        </li>
                        <li>
                            <strong>"Potencial real"</strong> — 3 a 4 áreas onde existe dinheiro "na mesa" (ticket, conversão, retenção, margem).
                        </li>
                    </ul>

                    <h4>Os cards de Gargalo, Oportunidade e Risco</h4>
                    <ul>
                        <li><strong>Maior Gargalo:</strong> O problema principal que trava o crescimento da loja agora</li>
                        <li><strong>Maior Oportunidade:</strong> A alavanca com maior potencial de resultado imediato</li>
                        <li><strong>Maior Risco:</strong> O risco mais relevante que precisa de atenção para não piorar</li>
                    </ul>

                    <h4>Como são determinados</h4>
                    <p>Os 3 cards são extraídos da análise completa pela IA estrategista. Cada um identifica:</p>
                    <ul>
                        <li><strong>Maior Gargalo:</strong> identificado a partir dos 5 principais problemas do Analista — é a causa-raiz (não o sintoma) que mais impacta o crescimento. Ex.: "55% dos produtos sem estoque" (causa-raiz) e não "vendas caindo" (sintoma).</li>
                        <li><strong>Maior Oportunidade:</strong> o gap financeiro mais significativo entre seus dados e o benchmark. Sempre tem R$ associado.</li>
                        <li><strong>Maior Risco:</strong> uma ameaça que pode piorar se não for monitorada — inclui riscos de margem, dependência, sazonalidade, etc.</li>
                    </ul>

                    <h4>O Resumo Direto</h4>
                    <ul>
                        <li><strong>"Você NÃO precisa":</strong> baseado na análise dos gargalos. Identifica ações que parecem intuitivas mas não atacam o problema real. Ex.: se o gargalo é estoque, "vender mais barato" não ajuda — só aumenta demanda para o que você não tem.</li>
                        <li><strong>"Você precisa":</strong> as 3-5 ações que movem o negócio, derivadas das sugestões de alta prioridade</li>
                        <li><strong>"Potencial real":</strong> as 3-4 áreas onde existe dinheiro "na mesa", derivadas das oportunidades financeiras</li>
                    </ul>

                    <div class="bg-amber-50 dark:bg-amber-900/20 border-l-4 border-amber-500 p-4 rounded-r-lg not-prose">
                        <p class="text-sm text-gray-700 dark:text-gray-300">
                            <strong>Exemplo:</strong> Se o gargalo é ruptura e baixa disponibilidade, baixar preços só aumenta a demanda por itens
                            que você não consegue entregar. A ação correta pode ser repor best-sellers e ajustar mix.
                        </p>
                    </div>
                </template>

                <!-- GROWTH SCORES -->
                <template v-if="section === 'growth-scores'">
                    <p>
                        O Growth Score traz <strong>quatro números (0 a 100)</strong> e um nível de maturidade.
                        Pense como uma "nota" por área de crescimento da loja.
                    </p>

                    <h4>Os 4 indicadores</h4>
                    <table>
                        <thead>
                            <tr>
                                <th>Indicador</th>
                                <th>O que mede</th>
                                <th>Principais métricas consideradas</th>
                                <th>Quando tende a cair</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>Overall</strong></td>
                                <td>Prontidão geral para crescer com consistência</td>
                                <td>Média ponderada dos outros 3 scores + fatores estruturais</td>
                                <td>Vários gargalos relevantes ao mesmo tempo</td>
                            </tr>
                            <tr>
                                <td><strong>Eficiência</strong></td>
                                <td>Capacidade de transformar tráfego em pedido sem "vazamento"</td>
                                <td>Taxa de cancelamento, % estoque zerado, conversão estimada, estabilidade operacional</td>
                                <td>Cancelamento alto, ruptura, baixa conversão, operação instável</td>
                            </tr>
                            <tr>
                                <td><strong>Margem</strong></td>
                                <td>Saúde financeira e disciplina de descontos/custos</td>
                                <td>% vendas com cupom, impacto do desconto no ticket, dependência de frete grátis, ticket vs benchmark</td>
                                <td>Dependência de cupom/frete grátis corroendo ticket e margem</td>
                            </tr>
                            <tr>
                                <td><strong>Retenção</strong></td>
                                <td>Potencial de recompra e fidelização</td>
                                <td>Taxa de recompra, existência de pós-compra, relacionamento com cliente, LTV estimado</td>
                                <td>Ausência de pós-compra, baixa recompra, relacionamento fraco</td>
                            </tr>
                        </tbody>
                    </table>

                    <h4>Nível de maturidade</h4>
                    <p>O nível traduz se a loja está pronta para escalar. Ele é determinado pela combinação dos 4 scores:</p>
                    <table>
                        <thead>
                            <tr>
                                <th>Nível</th>
                                <th>Descrição</th>
                                <th>Foco recomendado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="font-semibold">Operacional</td>
                                <td>Base operacional funcionando, mas com gargalos</td>
                                <td>Fortalecer processos, corrigir estoque e cancelamento</td>
                            </tr>
                            <tr>
                                <td class="font-semibold">Estruturada</td>
                                <td>Presença estabelecida, múltiplos canais</td>
                                <td>Otimizar conversão e ticket, iniciar retenção</td>
                            </tr>
                            <tr>
                                <td class="font-semibold">Escalável</td>
                                <td>Sistemas prontos, base sólida</td>
                                <td>Investir em aquisição com segurança, escalar mídia</td>
                            </tr>
                            <tr>
                                <td class="font-semibold">Otimizada</td>
                                <td>Eficiência otimizada em todas as áreas</td>
                                <td>Expansão de mix, novos mercados, automação</td>
                            </tr>
                            <tr>
                                <td class="font-semibold">Dominante</td>
                                <td>Posição de liderança no nicho</td>
                                <td>Ganho de market share, defesa de posição, inovação</td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="bg-blue-50 dark:bg-blue-900/20 border-l-4 border-blue-500 p-4 rounded-r-lg not-prose">
                        <p class="text-sm text-gray-700 dark:text-gray-300">
                            <strong>Como interpretar:</strong> se Eficiência está baixa mas Margem está alta, a loja ganha bem por venda mas "vaza" por cancelamento/estoque. O foco deve ser operacional. Se Margem está baixa mas Eficiência alta, o problema é precificação ou dependência de desconto.
                        </p>
                    </div>
                </template>

                <!-- ACTION PLAN 90 -->
                <template v-if="section === 'action-plan-90'">
                    <p>
                        O roadmap transforma as ideias em sequência: o que fazer primeiro (<strong>30 dias</strong>),
                        depois (<strong>60 dias</strong>) e por fim (<strong>90 dias</strong>).
                    </p>

                    <h4>As 3 fases</h4>
                    <ul>
                        <li><strong>30 dias (Quick Wins):</strong> Ganhos rápidos para estabilizar a base e gerar resultados imediatos</li>
                        <li><strong>60 dias (Estruturação):</strong> Melhorias estruturais que sustentam o crescimento a médio prazo</li>
                        <li><strong>90 dias (Escala):</strong> Ações para escalar e acelerar resultados com base sólida</li>
                    </ul>

                    <h4>Matriz Impacto × Esforço</h4>
                    <p>A matriz ajuda a identificar rapidamente o que vale a pena priorizar:</p>
                    <table>
                        <thead>
                            <tr>
                                <th>Quadrante</th>
                                <th>Descrição</th>
                                <th>Recomendação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="text-emerald-600 dark:text-emerald-400 font-semibold">Quick Wins</td>
                                <td>Alto impacto + baixo esforço</td>
                                <td>Fazer primeiro! Resultados rápidos com pouco investimento</td>
                            </tr>
                            <tr>
                                <td class="text-blue-600 dark:text-blue-400 font-semibold">High Impact</td>
                                <td>Alto impacto + maior esforço</td>
                                <td>Projetos importantes, planejar e executar com disciplina</td>
                            </tr>
                            <tr>
                                <td class="text-gray-600 dark:text-gray-400 font-semibold">Fill-ins</td>
                                <td>Baixo impacto + baixo esforço</td>
                                <td>Melhorias complementares, executar quando sobrar tempo</td>
                            </tr>
                            <tr>
                                <td class="text-red-600 dark:text-red-400 font-semibold">Avoid</td>
                                <td>Baixo impacto + alto esforço</td>
                                <td>Não priorizar agora — custo-benefício não compensa</td>
                            </tr>
                        </tbody>
                    </table>

                    <h4>Como as ações são distribuídas</h4>
                    <p>A distribuição no roadmap segue a lógica das sugestões de alta prioridade:</p>
                    <ul>
                        <li><strong>0-30 dias:</strong> Sugestões de prioridade 1-2 — alto impacto e baixo esforço, ROI imediato</li>
                        <li><strong>31-60 dias:</strong> Sugestões de prioridade 3-4 — esforço médio, melhorias estruturais</li>
                        <li><strong>61-90 dias:</strong> Sugestões de prioridade 5-6 — alto esforço, efeito composto de longo prazo</li>
                    </ul>
                    <p>As ações são ordenadas por <strong>impacto financeiro</strong> (maior primeiro) e formuladas de forma direta e mensurável.</p>

                    <div class="bg-blue-50 dark:bg-blue-900/20 border-l-4 border-blue-500 p-4 rounded-r-lg not-prose">
                        <p class="text-sm text-gray-700 dark:text-gray-300">
                            <strong>Dica:</strong> use o Roadmap 30/60/90 como ritmo. Se uma ação "do 90" virar urgência,
                            normalmente é sinal de que a base (do 30) ainda não está firme.
                        </p>
                    </div>
                </template>

                <!-- FINANCIAL IMPACT -->
                <template v-if="section === 'financial-impact'">
                    <p>
                        Os potenciais de impacto são estimativas baseadas em uma lógica simples:
                        <strong>uma base real (seus dados) × uma melhoria provável (premissa) = resultado</strong>.
                    </p>

                    <h4>Passo 1: Definir o baseline</h4>
                    <table>
                        <thead>
                            <tr>
                                <th>Como estimamos</th>
                                <th>Exemplo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Receita no período = ticket médio × pedidos pagos</td>
                                <td>R$ 85 × 120 pedidos = R$ 10.200 (em 15 dias)</td>
                            </tr>
                            <tr>
                                <td>Receita mensal = (receita do período ÷ dias) × 30</td>
                                <td>(10.200 ÷ 15) × 30 = R$ 20.400/mês</td>
                            </tr>
                        </tbody>
                    </table>

                    <h4>Passo 2: Escolher a alavanca</h4>
                    <table>
                        <thead>
                            <tr>
                                <th>Alavanca</th>
                                <th>Exemplo de premissa</th>
                                <th>Como calcular</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>Ticket</strong></td>
                                <td>+R$ 20 no ticket médio</td>
                                <td>Pedidos/mês × (+R$ no ticket)</td>
                            </tr>
                            <tr>
                                <td><strong>Conversão</strong></td>
                                <td>de 1,2% para 1,5%</td>
                                <td>Visitas × (taxa nova − taxa atual) × ticket</td>
                            </tr>
                            <tr>
                                <td><strong>Retenção</strong></td>
                                <td>+5% de recompra</td>
                                <td>Clientes × (% adicional) × ticket (ou LTV)</td>
                            </tr>
                            <tr>
                                <td><strong>Margem</strong></td>
                                <td>+2 p.p. de margem</td>
                                <td>Receita × (aumento de margem)</td>
                            </tr>
                        </tbody>
                    </table>

                    <h4>Por que às vezes o potencial vem como faixa?</h4>
                    <p>
                        Quando a confiança na premissa é menor (ex.: falta informação sobre tráfego, custo de mídia, margem real),
                        o potencial é apresentado como faixa (ex.: "R$ 1.000–3.000/mês"). Isso indica que
                        <strong>você deve testar antes de escalar</strong>.
                    </p>

                    <h4>Oportunidades financeiras (mensal e anual)</h4>
                    <ul>
                        <li><strong>Tipo de impacto:</strong> ticket, conversão, retenção ou margem</li>
                        <li><strong>Impacto mensal:</strong> quanto pode aumentar no mês, dadas as premissas</li>
                        <li><strong>Impacto anual:</strong> projeção do mensal × 12</li>
                    </ul>

                    <h4>Exemplos práticos de cálculo por tipo de alavanca</h4>

                    <div class="bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-4 rounded-lg not-prose my-4">
                        <p class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Ticket — Exemplo:</p>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Ticket atual: R$ 85 | Meta: R$ 105 (+R$ 20)<br>
                            Pedidos/mês: 240<br>
                            Impacto mensal: 240 × R$ 20 = <strong>R$ 4.800/mês</strong><br>
                            Impacto anual: R$ 4.800 × 12 = <strong>R$ 57.600/ano</strong>
                        </p>
                    </div>

                    <div class="bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-4 rounded-lg not-prose my-4">
                        <p class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Retenção — Exemplo:</p>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Clientes com potencial de recompra: 1.500<br>
                            Taxa atual: 8% | Meta: 12% (+4 p.p.)<br>
                            Novos retornos: 1.500 × 4% = 60 clientes<br>
                            Ticket médio: R$ 120<br>
                            Impacto mensal: 60 × R$ 120 = <strong>R$ 7.200/mês</strong><br>
                            Impacto anual: R$ 7.200 × 12 = <strong>R$ 86.400/ano</strong>
                        </p>
                    </div>

                    <h4>Níveis de confiança nos cálculos</h4>
                    <ul>
                        <li><strong>Alta:</strong> dados diretos da loja usados no cálculo — estimativa mais confiável</li>
                        <li><strong>Média:</strong> inferência lógica (ex.: margem estimada sem dado direto) — provável</li>
                        <li><strong>Baixa:</strong> benchmark genérico — apresentado como faixa (ex.: "R$ 1.000–3.000/mês"), trate como hipótese</li>
                    </ul>

                    <div class="bg-amber-50 dark:bg-amber-900/20 border-l-4 border-amber-500 p-4 rounded-r-lg not-prose">
                        <p class="text-sm text-gray-700 dark:text-gray-300">
                            <strong>Como interpretar:</strong> oportunidades financeiras são "alvos" para priorização.
                            Se você executar parte do plano, o impacto tende a ser menor; se executar com excelência,
                            tende a se aproximar dos cenários base/agressivo.
                        </p>
                    </div>
                </template>

                <!-- GROWTH SCENARIOS -->
                <template v-if="section === 'growth-scenarios'">
                    <p>
                        Os cenários mostram onde você pode chegar se executar as recomendações com
                        diferentes níveis de intensidade e risco. Eles usam <strong>percentuais fixos de crescimento</strong> sobre o seu faturamento atual.
                    </p>

                    <h4>Os percentuais de cada cenário</h4>
                    <table>
                        <thead>
                            <tr>
                                <th>Cenário</th>
                                <th>Crescimento aplicado</th>
                                <th>O que significa</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="font-semibold">Conservador</td>
                                <td class="text-center"><strong>+10%</strong></td>
                                <td>Execução parcial — aplica quick wins e corrige 1 gargalo principal</td>
                            </tr>
                            <tr>
                                <td class="font-semibold">Base</td>
                                <td class="text-center"><strong>+25%</strong></td>
                                <td>Execução consistente — implementa o roadmap com acompanhamento semanal</td>
                            </tr>
                            <tr>
                                <td class="font-semibold">Agressivo</td>
                                <td class="text-center"><strong>+50%</strong></td>
                                <td>Execução total — corrige base, acelera aquisição e otimiza funil</td>
                            </tr>
                        </tbody>
                    </table>

                    <h4>Fórmula de cálculo</h4>
                    <table>
                        <thead>
                            <tr>
                                <th>Etapa</th>
                                <th>Fórmula</th>
                                <th>Exemplo (Cenário Base)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>1) Baseline mensal</strong></td>
                                <td>(receita do período ÷ dias analisados) × 30</td>
                                <td>(R$ 10.200 ÷ 15) × 30 = R$ 20.400/mês</td>
                            </tr>
                            <tr>
                                <td><strong>2) Projeção mensal</strong></td>
                                <td>baseline × (1 + crescimento%)</td>
                                <td>R$ 20.400 × 1,25 = R$ 25.500/mês</td>
                            </tr>
                            <tr>
                                <td><strong>3) Projeção anual</strong></td>
                                <td>projeção mensal × 12</td>
                                <td>R$ 25.500 × 12 = R$ 306.000/ano</td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-4 rounded-lg not-prose my-4">
                        <p class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Exemplo completo com os 3 cenários:</p>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Baseline mensal: R$ 20.400<br><br>
                            Conservador (+10%): R$ 20.400 × 1,10 = <strong>R$ 22.440/mês</strong> → R$ 269.280/ano<br>
                            Base (+25%): R$ 20.400 × 1,25 = <strong>R$ 25.500/mês</strong> → R$ 306.000/ano<br>
                            Agressivo (+50%): R$ 20.400 × 1,50 = <strong>R$ 30.600/mês</strong> → R$ 367.200/ano
                        </p>
                    </div>

                    <div class="bg-red-50 dark:bg-red-900/20 border-l-4 border-red-500 p-4 rounded-r-lg not-prose">
                        <p class="text-sm text-gray-700 dark:text-gray-300">
                            <strong>Regra de ouro:</strong> o cenário "agressivo" só é saudável quando a base está sólida.
                            Se estoque e cancelamento estiverem problemáticos, escalar aquisição pode aumentar estresse operacional
                            e frustração do cliente.
                        </p>
                    </div>

                    <h4>Por que os cenários não são garantia</h4>
                    <ul>
                        <li>Porque dependem de execução (tempo, equipe, orçamento, consistência)</li>
                        <li>Porque premissas variam: sazonalidade, concorrência, custos de mídia, mudanças no mix</li>
                        <li>Porque resultados reais exigem medir e iterar (não é "apertar um botão")</li>
                    </ul>
                </template>

                <!-- ALERTS -->
                <template v-if="section === 'alerts'">
                    <p>
                        Alertas destacam <strong>riscos imediatos</strong> identificados pela IA nos dados da sua loja.
                        São problemas que podem estar causando perda de receita agora.
                    </p>

                    <h4>Como os alertas são gerados</h4>
                    <p>A IA analisa seus dados e gera no máximo <strong>5 alertas por análise</strong>. Cada alerta precisa obrigatoriamente de:</p>
                    <ul>
                        <li><strong>Evidência numérica</strong> — dados reais que comprovam o problema (nunca alertas sem números)</li>
                        <li><strong>Causa-raiz</strong> — a IA identifica a causa real, não apenas o sintoma</li>
                        <li><strong>Impacto estimado</strong> — quanto o problema pode estar custando (em R$ ou %)</li>
                        <li><strong>Ação recomendada</strong> — o que fazer para resolver</li>
                    </ul>

                    <h4>Níveis de severidade</h4>
                    <table>
                        <thead>
                            <tr>
                                <th>Severidade</th>
                                <th>Significado</th>
                                <th>Critério</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="text-red-600 dark:text-red-400 font-semibold">Crítico</td>
                                <td>Ameaça direta à operação — agir imediatamente</td>
                                <td>Somente quando baseado em dados diretos (confiança alta)</td>
                            </tr>
                            <tr>
                                <td class="text-amber-600 dark:text-amber-400 font-semibold">Atenção</td>
                                <td>Problema relevante que precisa de ação no curto prazo</td>
                                <td>Dados diretos ou inferência lógica (confiança alta ou média)</td>
                            </tr>
                            <tr>
                                <td class="text-blue-600 dark:text-blue-400 font-semibold">Monitorar</td>
                                <td>Sinal que merece acompanhamento</td>
                                <td>Pode ser usado com confiança baixa — trate como hipótese</td>
                            </tr>
                        </tbody>
                    </table>

                    <h4>Tipos de alerta e seus gatilhos</h4>
                    <ul>
                        <li><strong>Estoque:</strong> produtos populares sem estoque (best-sellers zerados), % alto de catálogo indisponível</li>
                        <li><strong>Cancelamento:</strong> taxa acima do esperado para o nicho (&gt;3% já acende alerta, &gt;12% é crítico)</li>
                        <li><strong>Pricing:</strong> ticket muito abaixo do benchmark, preços inconsistentes no catálogo</li>
                        <li><strong>Cupons:</strong> dependência excessiva (&gt;60% das vendas com desconto), erosão de margem</li>
                        <li><strong>Vendas:</strong> queda significativa vs período anterior (&gt;20% acende alerta)</li>
                    </ul>

                    <h4>Detecção de anomalias</h4>
                    <p>A IA considera como anomalia qualquer <strong>variação acima de 20%</strong> em relação à média histórica, após ajustar por sazonalidade (ex.: queda de 20-30% em janeiro pós-festas é considerada normal).</p>

                    <div class="bg-amber-50 dark:bg-amber-900/20 border-l-4 border-amber-500 p-4 rounded-r-lg not-prose">
                        <p class="text-sm text-gray-700 dark:text-gray-300">
                            <strong>Regra importante:</strong> alertas com confiança baixa nunca podem ter severidade "Crítico". Se a IA não tem certeza do problema, ele aparece como "Monitorar" para que você investigue.
                        </p>
                    </div>
                </template>

                <!-- OPPORTUNITIES -->
                <template v-if="section === 'opportunities'">
                    <p>
                        Oportunidades apontam <strong>alavancas de crescimento</strong> que a IA identificou nos seus dados.
                        São áreas onde existe potencial de receita extra a ser explorado.
                    </p>

                    <h4>Como são identificadas</h4>
                    <p>A IA gera até <strong>5 oportunidades por análise</strong>, cada uma com potencial estimado em R$. O processo usa uma tripla comparação:</p>
                    <ul>
                        <li><strong>Sua loja</strong> — métricas atuais (ticket, conversão, estoque, recompra)</li>
                        <li><strong>Benchmark do nicho</strong> — referência esperada para o seu segmento</li>
                        <li><strong>Concorrentes</strong> — quando disponíveis, dados de preço e posicionamento dos competidores</li>
                    </ul>
                    <p>Quando há <strong>gap</strong> entre o seu número atual e a referência, a IA calcula o potencial de ganho.</p>

                    <h4>Cálculo do potencial</h4>
                    <p>Cada oportunidade segue a fórmula: <strong>base real (seus dados) × melhoria provável (premissa) = potencial</strong></p>

                    <div class="bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-4 rounded-lg not-prose my-4">
                        <p class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Exemplo — Oportunidade de ticket:</p>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Ticket atual: R$ 85 | Benchmark do nicho: R$ 120<br>
                            Gap: R$ 35 por pedido<br>
                            Pedidos/mês: 240<br>
                            Potencial: 240 × R$ 35 = <strong>R$ 8.400/mês</strong>
                        </p>
                    </div>

                    <h4>Níveis de confiança</h4>
                    <ul>
                        <li><strong>Alta:</strong> baseado em dados diretos da loja — estimativa mais confiável</li>
                        <li><strong>Média:</strong> inferência lógica — provável mas depende de variáveis</li>
                        <li><strong>Baixa:</strong> benchmark genérico — trate como hipótese e apresentado como faixa (ex.: "R$ 1.000–3.000/mês")</li>
                    </ul>

                    <div class="bg-emerald-50 dark:bg-emerald-900/20 border-l-4 border-emerald-500 p-4 rounded-r-lg not-prose">
                        <p class="text-sm text-gray-700 dark:text-gray-300">
                            <strong>Transparência:</strong> recomendações com baixa certeza devem ser tratadas como hipóteses para testar.
                            Já recomendações baseadas em números diretos da loja tendem a ser mais confiáveis.
                        </p>
                    </div>
                </template>

                <!-- STRATEGIC SUGGESTIONS -->
                <template v-if="section === 'strategic-suggestions'">
                    <p>
                        As sugestões são criadas para resolver <strong>problemas reais</strong> identificados nos dados e para
                        explorar oportunidades do nicho. Elas passam por um processo rigoroso de geração, validação e seleção.
                    </p>

                    <h4>O pipeline de sugestões</h4>
                    <p>Cada sugestão passa por <strong>4 etapas de IA</strong> antes de chegar até você:</p>
                    <ol>
                        <li><strong>Coletor</strong> — reúne todos os dados da loja, benchmarks e dados de mercado</li>
                        <li><strong>Analista</strong> — identifica os 5 principais problemas com dados numéricos e gera o Health Score</li>
                        <li><strong>Estrategista</strong> — cria 18 sugestões (6 alta + 6 média + 6 baixa prioridade) com base nos problemas e oportunidades</li>
                        <li><strong>Crítico</strong> — valida cada sugestão com 7 critérios rigorosos e seleciona as <strong>9 melhores</strong> (3 alta + 3 média + 3 baixa)</li>
                    </ol>

                    <h4>Os 7 critérios de validação</h4>
                    <p>Cada sugestão é verificada em 7 pontos antes de ser aprovada:</p>
                    <table>
                        <thead>
                            <tr>
                                <th>Critério</th>
                                <th>O que verifica</th>
                                <th>Se falhar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>V1 — Números</strong></td>
                                <td>Os dados citados batem com os dados reais da loja?</td>
                                <td>Corrige os números</td>
                            </tr>
                            <tr>
                                <td><strong>V2 — Originalidade</strong></td>
                                <td>O tema já foi sugerido muitas vezes antes?</td>
                                <td>Rejeitada (evita repetição)</td>
                            </tr>
                            <tr>
                                <td><strong>V3 — Especificidade</strong></td>
                                <td>A sugestão serve para QUALQUER loja ou é específica para a sua?</td>
                                <td>Rejeitada ou adaptada com dados específicos</td>
                            </tr>
                            <tr>
                                <td><strong>V4 — Viabilidade</strong></td>
                                <td>É possível implementar na sua plataforma? Qual o custo real?</td>
                                <td>Rejeitada ou ajustada</td>
                            </tr>
                            <tr>
                                <td><strong>V5 — Impacto</strong></td>
                                <td>O cálculo de impacto é verificável? (base × premissa = resultado)</td>
                                <td>Completa o cálculo</td>
                            </tr>
                            <tr>
                                <td><strong>V6 — Alinhamento</strong></td>
                                <td>Resolve um dos 5 problemas identificados pelo Analista?</td>
                                <td>Obrigatório para alta prioridade</td>
                            </tr>
                            <tr>
                                <td><strong>V7 — Qualidade dos passos</strong></td>
                                <td>Os passos de execução são detalhados e acionáveis?</td>
                                <td>Melhora ou rejeita</td>
                            </tr>
                        </tbody>
                    </table>

                    <h4>Como evitamos sugestões repetidas</h4>
                    <p>O sistema rastreia temas já sugeridos em análises anteriores com um mecanismo de saturação:</p>
                    <ul>
                        <li><strong>3+ vezes sugerido</strong> — tema BLOQUEADO, não pode aparecer novamente</li>
                        <li><strong>2 vezes</strong> — permitido apenas com abordagem completamente diferente</li>
                        <li><strong>1 vez</strong> — pode usar com cautela</li>
                        <li><strong>Nunca usado</strong> — preferido (maximiza diversidade)</li>
                    </ul>

                    <h4>De onde vêm os dados</h4>
                    <ul>
                        <li><strong>Pedidos e faturamento:</strong> volume de pedidos e receita nos últimos 15 dias</li>
                        <li><strong>Ticket médio:</strong> valor médio por pedido</li>
                        <li><strong>Cancelamentos:</strong> taxa de cancelamento no período</li>
                        <li><strong>Catálogo e estoque:</strong> produtos ativos, quantos estão sem estoque, best-sellers</li>
                        <li><strong>Cupons:</strong> uso, impacto no ticket, dependência</li>
                        <li><strong>Benchmarks:</strong> referências do nicho para ticket, margem, conversão</li>
                        <li><strong>Concorrentes:</strong> dados de preço e posicionamento (quando disponíveis)</li>
                        <li><strong>Tendências de mercado:</strong> Google Trends e sinais de busca (quando disponíveis)</li>
                    </ul>

                    <h4>Formato de cada sugestão</h4>
                    <p>Cada sugestão inclui:</p>
                    <ul>
                        <li><strong>Problema:</strong> com dados numéricos específicos da sua loja</li>
                        <li><strong>Ação:</strong> passos detalhados com caminhos exatos na plataforma</li>
                        <li><strong>Resultado esperado:</strong> impacto em R$ ou % com base verificável</li>
                        <li><strong>Implementação:</strong> tipo (nativo, app ou terceiro), complexidade e custo</li>
                    </ul>

                    <h4>Boas práticas para executar</h4>
                    <ol>
                        <li><strong>Comece pequeno:</strong> execute 1–3 sugestões e meça por 7–14 dias</li>
                        <li><strong>Escolha 1 métrica principal por ciclo:</strong> ticket, conversão, cancelamento, ruptura ou recompra</li>
                        <li><strong>Registre aprendizados:</strong> o que funcionou, o que não funcionou e por quê</li>
                        <li><strong>Evite "promoção por ansiedade":</strong> descontos sem estratégia podem destruir margem e percepção de valor</li>
                        <li><strong>Priorize base antes de escalar mídia:</strong> estoque e operação precisam aguentar o crescimento</li>
                    </ol>
                </template>

                <!-- PRIORITY LEVELS -->
                <template v-if="section === 'priority-levels'">
                    <p>
                        As sugestões são organizadas em 3 níveis de prioridade. Cada nível tem <strong>regras específicas</strong>
                        sobre que tipos de ação podem aparecer e como são validadas.
                    </p>

                    <h4>Os 3 níveis</h4>
                    <table>
                        <thead>
                            <tr>
                                <th>Nível</th>
                                <th>Natureza</th>
                                <th>Categorias permitidas</th>
                                <th>Requisito de dados</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="text-red-600 dark:text-red-400 font-semibold">Alta (HIGH)</td>
                                <td><strong>Estratégicas</strong> — visão de negócio, posicionamento, investimento</td>
                                <td>Estratégia, Investimento, Mercado, Crescimento, Financeiro, Posicionamento</td>
                                <td>Obrigatório usar dados de concorrentes, mercado ou benchmarks</td>
                            </tr>
                            <tr>
                                <td class="text-amber-600 dark:text-amber-400 font-semibold">Média (MEDIUM)</td>
                                <td><strong>Táticas com dados</strong> — ações operacionais com evidência numérica</td>
                                <td>Estoque, Precificação, Produtos, Clientes, Conversão, Marketing, Cupons, Operacional</td>
                                <td>Obrigatório citar dados específicos da loja (se não tiver dados, vira LOW)</td>
                            </tr>
                            <tr>
                                <td class="text-emerald-600 dark:text-emerald-400 font-semibold">Baixa (LOW)</td>
                                <td><strong>Quick wins e boas práticas</strong> — melhorias rápidas e acionáveis</td>
                                <td>Estoque, Precificação, Produtos, Clientes, Conversão, Marketing, Cupons, Operacional</td>
                                <td>Não exige evidência numérica, mas deve ser acionável</td>
                            </tr>
                        </tbody>
                    </table>

                    <h4>Processo de seleção (18 → 9)</h4>
                    <p>A IA gera <strong>18 sugestões</strong> (6 de cada nível) e um sistema de validação seleciona as <strong>9 melhores</strong> (3 de cada). Os critérios de seleção:</p>
                    <ul>
                        <li><strong>Alta prioridade:</strong> precisa ter visão estratégica com dados de mercado/concorrentes, resolver um dos 5 problemas principais e ter cálculo de impacto verificável</li>
                        <li><strong>Média prioridade:</strong> precisa citar dados específicos da loja (não pode ser genérica) e ter impacto mensurável</li>
                        <li><strong>Baixa prioridade:</strong> deve ser facilmente acionável, com resultado rápido</li>
                    </ul>

                    <h4>Diversidade garantida</h4>
                    <p>Para garantir que as sugestões cubram diferentes áreas:</p>
                    <ul>
                        <li>Mínimo de <strong>10 categorias diferentes</strong> entre as 18 sugestões geradas</li>
                        <li>Máximo de <strong>3 sugestões por categoria</strong></li>
                        <li><strong>Nenhuma repetição temática</strong> entre as 9 selecionadas</li>
                    </ul>

                    <h4>Mapeamento com o Roadmap</h4>
                    <p>As sugestões de alta prioridade são automaticamente distribuídas no roadmap:</p>
                    <ul>
                        <li><strong>Prioridades 1-2:</strong> ações para os primeiros 30 dias (quick wins)</li>
                        <li><strong>Prioridades 3-4:</strong> ações para 31-60 dias (estruturação)</li>
                        <li><strong>Prioridades 5-6:</strong> ações para 61-90 dias (escala)</li>
                    </ul>

                    <h4>Impacto estimado</h4>
                    <p>
                        O impacto de cada sugestão segue o cálculo: <strong>base real × melhoria provável = resultado estimado</strong>.
                        Toda sugestão precisa ter o resultado em R$ ou %, com o cálculo explícito para ser verificável.
                    </p>

                    <div class="bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-4 rounded-lg not-prose my-4">
                        <p class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Exemplo de cálculo de impacto:</p>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Base: 120 clientes com potencial de recompra<br>
                            Premissa: 15% vão retornar com incentivo de fidelidade<br>
                            Cálculo: 120 × 15% = 18 compras adicionais<br>
                            Ticket médio: R$ 120<br>
                            Impacto mensal: 18 × R$ 120 = <strong>R$ 2.160/mês</strong>
                        </p>
                    </div>

                    <div class="bg-blue-50 dark:bg-blue-900/20 border-l-4 border-blue-500 p-4 rounded-r-lg not-prose">
                        <p class="text-sm text-gray-700 dark:text-gray-300">
                            <strong>Recomendação:</strong> comece pelas 1–3 sugestões de alta prioridade, execute por 7–14 dias,
                            meça o resultado e depois avance para as de média e baixa prioridade.
                        </p>
                    </div>
                </template>

            </div>
        </div>
    </BaseModal>
</template>
