<script setup>
import { computed } from 'vue';
import {
    XMarkIcon,
    ArrowTrendingUpIcon,
    CurrencyDollarIcon,
    ChatBubbleLeftRightIcon,
    LightBulbIcon,
    RocketLaunchIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    show: { type: Boolean, default: false },
    opportunity: { type: Object, default: null },
});

const emit = defineEmits(['close', 'ask-ai']);

// Mapeamento de títulos com underscore para português
const titleLabels = {
    gestao_estoque: 'Gestão de Estoque',
    sazonalidade_inicio_ano: 'Sazonalidade Início do Ano',
    dependencia_cupons: 'Dependência de Cupons',
    produtos_estrela: 'Produtos Estrela',
    coupon_dependency: 'Dependência de Cupons',
    bestseller_dominance: 'Dominância de Bestsellers',
    inventory_imbalance: 'Desequilíbrio de Estoque',
    ticket_medio_positivo: 'Ticket Médio Positivo',
    cross_sell: 'Venda Cruzada',
    upsell: 'Upsell',
    seasonal_trend: 'Tendência Sazonal',
    customer_retention: 'Retenção de Clientes',
    price_optimization: 'Otimização de Preços',
    bundle_opportunity: 'Oportunidade de Combo',
    reactivation: 'Reativação de Clientes',
    high_margin: 'Alta Margem',
    growth_potential: 'Potencial de Crescimento',
    market_expansion: 'Expansão de Mercado',
    repeat_purchase: 'Compra Recorrente',
};

// Função para formatar título removendo underscores
function formatTitle(title) {
    if (!title) return 'Oportunidade';
    // Se existe no mapeamento, usa o label
    if (titleLabels[title]) return titleLabels[title];
    // Se contém underscore, transforma em título capitalizado
    if (title.includes('_')) {
        return title.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase()).join(' ');
    }
    return title;
}

// Título formatado para exibição
const formattedTitle = computed(() => formatTitle(props.opportunity?.title));

// Map opportunity types to icons and colors
const opportunityConfig = {
    'Dependência de Cupons': { icon: '🏷️', color: 'from-indigo-500 to-blue-500' },
    'Dominância de Bestsellers': { icon: '🏆', color: 'from-amber-500 to-yellow-500' },
    'Desequilíbrio de Estoque': { icon: '📦', color: 'from-sky-500 to-cyan-500' },
    'Ticket Médio Positivo': { icon: '📈', color: 'from-emerald-500 to-teal-500' },
    'Venda Cruzada': { icon: '🔗', color: 'from-purple-500 to-violet-500' },
    'Upsell': { icon: '⬆️', color: 'from-orange-500 to-red-500' },
    'Tendência Sazonal': { icon: '📅', color: 'from-pink-500 to-rose-500' },
    'Retenção de Clientes': { icon: '🤝', color: 'from-teal-500 to-green-500' },
    'Otimização de Preços': { icon: '💰', color: 'from-yellow-500 to-amber-500' },
    'Oportunidade de Combo': { icon: '🎁', color: 'from-fuchsia-500 to-pink-500' },
    'Reativação de Clientes': { icon: '🔄', color: 'from-cyan-500 to-blue-500' },
    'Alta Margem': { icon: '💎', color: 'from-violet-500 to-purple-500' },
    'Potencial de Crescimento': { icon: '🌱', color: 'from-green-500 to-emerald-500' },
    'Expansão de Mercado': { icon: '🌍', color: 'from-blue-500 to-indigo-500' },
    'Compra Recorrente': { icon: '🔁', color: 'from-rose-500 to-red-500' },
    'Gestão de Estoque': { icon: '📦', color: 'from-sky-500 to-cyan-500' },
    'Sazonalidade Início do Ano': { icon: '📅', color: 'from-orange-500 to-amber-500' },
    'Produtos Estrela': { icon: '⭐', color: 'from-yellow-500 to-amber-500' },
};

const config = computed(() => {
    return opportunityConfig[formattedTitle.value] || { icon: '✨', color: 'from-emerald-500 to-teal-500' };
});

// Generate action suggestions based on opportunity type
const suggestedActions = computed(() => {
    const title = formattedTitle.value;

    const actionsByType = {
        'Dependência de Cupons': [
            'Reduza gradualmente o percentual de desconto dos cupons',
            'Crie campanhas de valor agregado ao invés de desconto',
            'Implemente programa de fidelidade como alternativa',
        ],
        'Dominância de Bestsellers': [
            'Identifique características comuns dos produtos campeões',
            'Crie bundles combinando bestsellers com produtos menos vendidos',
            'Desenvolva variações dos produtos mais vendidos',
        ],
        'Desequilíbrio de Estoque': [
            'Revise a curva ABC de produtos',
            'Crie promoções para itens com excesso de estoque',
            'Ajuste pedidos de reposição baseado em giro real',
        ],
        'Ticket Médio Positivo': [
            'Identifique os produtos que elevam o ticket',
            'Sugira produtos complementares no checkout',
            'Crie faixas de frete grátis progressivas',
        ],
        'Venda Cruzada': [
            'Configure produtos relacionados nas páginas',
            'Crie emails de pós-venda com sugestões',
            'Implemente "Clientes também compraram"',
        ],
        'Upsell': [
            'Destaque versões premium dos produtos',
            'Mostre comparativo de benefícios',
            'Ofereça upgrade com desconto no checkout',
        ],
        'Tendência Sazonal': [
            'Prepare estoque com antecedência',
            'Crie campanhas temáticas',
            'Ajuste preços conforme demanda sazonal',
        ],
        'Retenção de Clientes': [
            'Implemente programa de pontos/cashback',
            'Crie comunicação personalizada pós-compra',
            'Ofereça benefícios exclusivos para clientes recorrentes',
        ],
        'Otimização de Preços': [
            'Analise preços da concorrência',
            'Teste diferentes faixas de preço',
            'Implemente precificação dinâmica',
        ],
        'Oportunidade de Combo': [
            'Crie kits com produtos complementares',
            'Ofereça desconto progressivo por quantidade',
            'Monte combos temáticos ou sazonais',
        ],
        'Reativação de Clientes': [
            'Envie campanhas de "sentimos sua falta"',
            'Ofereça cupom exclusivo de retorno',
            'Mostre novidades desde última compra',
        ],
        'Gestão de Estoque': [
            'Implemente sistema de alerta de estoque mínimo',
            'Analise o giro de cada produto para otimizar compras',
            'Crie promoções para produtos com estoque parado',
        ],
        'Sazonalidade Início do Ano': [
            'Planeje campanhas de volta às aulas',
            'Prepare estoque para datas comemorativas do período',
            'Ajuste preços conforme a demanda sazonal',
        ],
        'Produtos Estrela': [
            'Destaque os produtos mais vendidos na home',
            'Crie variações ou kits com esses produtos',
            'Use-os como âncora em campanhas de marketing',
        ],
    };

    return actionsByType[title] || [
        'Analise os dados detalhados desta oportunidade',
        'Discuta com a IA para obter insights personalizados',
        'Crie um plano de ação específico',
    ];
});

function askAI() {
    emit('ask-ai', props.opportunity);
}
</script>

<template>
    <Teleport to="body">
        <Transition name="modal">
            <div
                v-if="show && opportunity"
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
            >
                <!-- Backdrop -->
                <div
                    class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm"
                    @click="emit('close')"
                ></div>

                <!-- Modal -->
                <div class="relative w-full max-w-2xl max-h-[90vh] overflow-hidden bg-white dark:bg-gray-800 rounded-3xl shadow-2xl">
                    <!-- Header with Gradient -->
                    <div class="relative px-8 py-6 bg-gradient-to-r overflow-hidden" :class="config.color">
                        <!-- Background Pattern -->
                        <div class="absolute inset-0 opacity-20" style="background-image: radial-gradient(rgba(255,255,255,0.3) 1px, transparent 1px); background-size: 20px 20px;"></div>

                        <!-- Close Button -->
                        <button
                            @click="emit('close')"
                            class="absolute top-4 right-4 z-10 w-10 h-10 flex items-center justify-center rounded-full bg-white/20 hover:bg-white/30 transition-colors cursor-pointer"
                            aria-label="Fechar"
                        >
                            <XMarkIcon class="w-5 h-5 text-white" />
                        </button>

                        <div class="relative flex items-start gap-4">
                            <div class="w-16 h-16 rounded-2xl bg-white/20 backdrop-blur-sm flex items-center justify-center text-3xl">
                                {{ config.icon }}
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="text-white/80 text-sm font-medium uppercase tracking-wider">
                                        Oportunidade
                                    </span>
                                    <span v-if="opportunity.potential_revenue" class="px-3 py-1 rounded-full bg-white/20 text-white text-xs font-semibold flex items-center gap-1">
                                        <CurrencyDollarIcon class="w-3.5 h-3.5" />
                                        {{ opportunity.potential_revenue }}
                                    </span>
                                </div>
                                <h2 class="text-xl lg:text-2xl font-display font-bold text-white pr-8">
                                    {{ formattedTitle }}
                                </h2>
                            </div>
                        </div>
                    </div>

                    <!-- Content -->
                    <div class="px-8 py-6 max-h-[60vh] overflow-y-auto scrollbar-thin space-y-6">
                        <!-- Description -->
                        <div>
                            <h3 class="flex items-center gap-2 font-semibold text-gray-900 dark:text-gray-100 mb-3">
                                <LightBulbIcon class="w-5 h-5 text-amber-500" />
                                Sobre esta Oportunidade
                            </h3>
                            <p class="text-gray-600 dark:text-gray-400 leading-relaxed">{{ opportunity.description }}</p>
                        </div>

                        <!-- Potential Revenue Highlight -->
                        <div v-if="opportunity.potential_revenue" class="bg-gradient-to-r from-emerald-50 to-teal-50 dark:from-emerald-900/30 dark:to-teal-900/30 rounded-2xl p-5 border border-emerald-100 dark:border-emerald-800">
                            <h3 class="flex items-center gap-2 font-semibold text-emerald-900 dark:text-emerald-200 mb-2">
                                <ArrowTrendingUpIcon class="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                                Potencial de Receita
                            </h3>
                            <p class="text-2xl font-display font-bold text-emerald-700 dark:text-emerald-300">
                                {{ opportunity.potential_revenue }}
                            </p>
                            <p class="text-sm text-emerald-600 dark:text-emerald-400 mt-1">
                                Estimativa baseada nos dados da sua loja
                            </p>
                        </div>

                        <!-- Suggested Actions -->
                        <div>
                            <h3 class="flex items-center gap-2 font-semibold text-gray-900 dark:text-gray-100 mb-4">
                                <RocketLaunchIcon class="w-5 h-5 text-primary-500" />
                                Ações Sugeridas
                            </h3>
                            <div class="space-y-3">
                                <div
                                    v-for="(action, index) in suggestedActions"
                                    :key="index"
                                    class="flex items-start gap-4 p-4 rounded-xl bg-gray-50 dark:bg-gray-900 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                                >
                                    <div class="flex-shrink-0 w-8 h-8 rounded-full bg-gradient-to-br from-primary-500 to-secondary-500 text-white flex items-center justify-center text-sm font-bold shadow-lg shadow-primary-500/30">
                                        {{ index + 1 }}
                                    </div>
                                    <span class="text-gray-700 dark:text-gray-300 pt-1">{{ action }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="px-8 py-5 bg-gray-50 dark:bg-gray-900 border-t border-gray-100 dark:border-gray-700">
                        <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-end gap-3">
                            <button
                                @click="emit('close')"
                                class="px-5 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 font-medium hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                            >
                                Fechar
                            </button>
                            <button
                                @click="askAI"
                                class="flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-gradient-to-r from-primary-500 to-secondary-500 text-white font-semibold shadow-lg shadow-primary-500/30 hover:shadow-xl transition-all"
                            >
                                <ChatBubbleLeftRightIcon class="w-5 h-5" />
                                Discutir com IA
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.modal-enter-active,
.modal-leave-active {
    transition: opacity 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}
</style>
