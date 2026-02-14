<script setup>
import { ref, computed, onMounted } from 'vue';
import { useFormatters } from '../../composables/useFormatters';
import {
    RocketLaunchIcon,
    CheckCircleIcon,
    ExclamationTriangleIcon,
    ArrowTrendingUpIcon,
    ShieldExclamationIcon,
    CalendarDaysIcon,
    BanknotesIcon,
    BoltIcon,
    ChartBarIcon,
    XMarkIcon,
    PresentationChartBarIcon,
    ArrowRightIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    premiumSummary: {
        type: Object,
        required: true,
    },
});

const { formatCurrency } = useFormatters();
const isVisible = ref(false);

// Computed helpers
const executiveSummary = computed(() => props.premiumSummary?.executive_summary || {});
const growthScore = computed(() => props.premiumSummary?.growth_score || {});
const financialOpportunities = computed(() => props.premiumSummary?.financial_opportunities || []);
const prioritizedRoadmap = computed(() => props.premiumSummary?.prioritized_roadmap || {});
const impactEffortMatrix = computed(() => props.premiumSummary?.impact_effort_matrix || {});
const growthScenarios = computed(() => props.premiumSummary?.growth_scenarios || {});
const strategicRisks = computed(() => props.premiumSummary?.strategic_risks || []);
const finalVerdict = computed(() => props.premiumSummary?.final_verdict || {});

// Total de impacto financeiro
const totalMonthlyImpact = computed(() => {
    return financialOpportunities.value.reduce((sum, opp) => sum + (opp.estimated_monthly_impact || 0), 0);
});

const totalAnnualImpact = computed(() => {
    return financialOpportunities.value.reduce((sum, opp) => sum + (opp.estimated_annual_impact || 0), 0);
});

// Labels para tipos de impacto
const impactTypeLabels = {
    ticket: { label: 'Ticket', color: 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300' },
    retention: { label: 'Retenção', color: 'bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300' },
    conversion: { label: 'Conversão', color: 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300' },
    margin: { label: 'Margem', color: 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300' },
};

// Helper para cor da barra de progresso
function getProgressBarColor(value) {
    if (value >= 70) return 'bg-emerald-500';
    if (value >= 50) return 'bg-blue-500';
    if (value >= 30) return 'bg-amber-500';
    return 'bg-rose-500';
}

// Animação de entrada
onMounted(() => {
    setTimeout(() => {
        isVisible.value = true;
    }, 100);
});
</script>

<template>
    <div
        :class="[
            'bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden transition-all duration-700',
            isVisible ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'
        ]"
    >
        <!-- Header Banner -->
        <div class="relative px-6 py-5 bg-gradient-to-r from-purple-600 to-indigo-600 overflow-hidden">
            <!-- Background Pattern -->
            <div class="absolute inset-0" style="background-image: radial-gradient(rgba(255,255,255,0.1) 1px, transparent 1px); background-size: 20px 20px;"></div>

            <div class="relative flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-white/20 backdrop-blur-sm flex items-center justify-center">
                        <RocketLaunchIcon class="w-5 h-5 text-white" />
                    </div>
                    <div>
                        <h3 class="text-lg font-display font-bold text-white">Resumo Estratégico</h3>
                        <p class="text-purple-100 text-sm">Growth Intelligence</p>
                    </div>
                </div>


            </div>
        </div>

        <!-- Section 1: Resumo Executivo -->
        <div v-if="executiveSummary" class="p-6 space-y-6">
            <!-- Resumo Direto -->
            <div v-if="executiveSummary.resumo_direto" class="space-y-4">
                <div class="text-xl text-gray-900 dark:text-gray-100 leading-relaxed">
                    Você <span class="font-bold text-rose-600 dark:text-rose-400">NÃO</span> precisa
                    <span class="font-bold">{{ executiveSummary.resumo_direto.nao_precisa }}</span>.
                </div>

                <div v-if="executiveSummary.resumo_direto.precisa?.length" class="space-y-2">
                    <div class="text-base font-semibold text-gray-700 dark:text-gray-300">Você precisa:</div>
                    <div class="space-y-2">
                        <div
                            v-for="(item, index) in executiveSummary.resumo_direto.precisa"
                            :key="index"
                            class="flex items-start gap-2"
                        >
                            <CheckCircleIcon class="w-5 h-5 text-primary-500 flex-shrink-0 mt-0.5" />
                            <span class="text-base text-gray-700 dark:text-gray-300">{{ item }}</span>
                        </div>
                    </div>
                </div>

                <div v-if="executiveSummary.resumo_direto.potencial_real?.length" class="space-y-2">
                    <div class="text-base font-semibold text-gray-700 dark:text-gray-300">Seu potencial real está em:</div>
                    <div class="flex flex-wrap gap-2">
                        <span
                            v-for="(item, index) in executiveSummary.resumo_direto.potencial_real"
                            :key="index"
                            class="bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 px-3 py-1 rounded-full text-sm font-medium"
                        >
                            {{ item }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Fallback: Diagnóstico Principal -->
            <div v-else-if="executiveSummary.diagnostico_principal" class="border-l-4 border-purple-500 pl-4 py-2">
                <p class="text-gray-700 dark:text-gray-300 leading-relaxed">
                    {{ executiveSummary.diagnostico_principal }}
                </p>
            </div>

            <!-- 3 Insight Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
                <!-- Gargalo -->
                <div v-if="executiveSummary.maior_gargalo" class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl p-4">
                    <div class="flex items-start gap-3">
                        <ExclamationTriangleIcon class="w-5 h-5 text-amber-500 flex-shrink-0" />
                        <div>
                            <div class="text-sm font-medium text-amber-600 dark:text-amber-400 mb-1">Maior Gargalo</div>
                            <div class="text-base font-medium text-gray-900 dark:text-gray-100">
                                {{ executiveSummary.maior_gargalo }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Oportunidade -->
                <div v-if="executiveSummary.maior_oportunidade" class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-xl p-4">
                    <div class="flex items-start gap-3">
                        <ArrowTrendingUpIcon class="w-5 h-5 text-emerald-500 flex-shrink-0" />
                        <div>
                            <div class="text-sm font-medium text-emerald-600 dark:text-emerald-400 mb-1">Maior Oportunidade</div>
                            <div class="text-base font-medium text-gray-900 dark:text-gray-100">
                                {{ executiveSummary.maior_oportunidade }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Risco -->
                <div v-if="executiveSummary.risco_mais_relevante" class="bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-800 rounded-xl p-4">
                    <div class="flex items-start gap-3">
                        <ShieldExclamationIcon class="w-5 h-5 text-rose-500 flex-shrink-0" />
                        <div>
                            <div class="text-sm font-medium text-rose-600 dark:text-rose-400 mb-1">Maior Risco</div>
                            <div class="text-base font-medium text-gray-900 dark:text-gray-100">
                                {{ executiveSummary.risco_mais_relevante }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Growth Score Bars -->
            <div v-if="growthScore.overall_score" class="mt-6 space-y-3">
                <!-- Eficiência -->
                <div v-if="growthScore.efficiency_score !== undefined" class="space-y-1">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-600 dark:text-gray-400">Eficiência</span>
                        <span class="font-semibold text-gray-900 dark:text-gray-100">{{ growthScore.efficiency_score }}</span>
                    </div>
                    <div class="h-2 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden">
                        <div
                            :class="['h-full rounded-full transition-all duration-1000', getProgressBarColor(growthScore.efficiency_score)]"
                            :style="{ width: `${growthScore.efficiency_score}%` }"
                        ></div>
                    </div>
                </div>

                <!-- Margem -->
                <div v-if="growthScore.margin_health !== undefined" class="space-y-1">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-600 dark:text-gray-400">Margem</span>
                        <span class="font-semibold text-gray-900 dark:text-gray-100">{{ growthScore.margin_health }}</span>
                    </div>
                    <div class="h-2 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden">
                        <div
                            :class="['h-full rounded-full transition-all duration-1000', getProgressBarColor(growthScore.margin_health)]"
                            :style="{ width: `${growthScore.margin_health}%` }"
                        ></div>
                    </div>
                </div>

                <!-- Retenção -->
                <div v-if="growthScore.retention_score !== undefined" class="space-y-1">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-600 dark:text-gray-400">Retenção</span>
                        <span class="font-semibold text-gray-900 dark:text-gray-100">{{ growthScore.retention_score }}</span>
                    </div>
                    <div class="h-2 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden">
                        <div
                            :class="['h-full rounded-full transition-all duration-1000', getProgressBarColor(growthScore.retention_score)]"
                            :style="{ width: `${growthScore.retention_score}%` }"
                        ></div>
                    </div>
                </div>

                <!-- Crescimento -->
                <div v-if="executiveSummary.potencial_crescimento_estimado_percentual !== undefined" class="space-y-1">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-600 dark:text-gray-400">Crescimento</span>
                        <span class="font-semibold text-gray-900 dark:text-gray-100">{{ executiveSummary.potencial_crescimento_estimado_percentual }}</span>
                    </div>
                    <div class="h-2 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden">
                        <div
                            :class="['h-full rounded-full transition-all duration-1000', getProgressBarColor(executiveSummary.potencial_crescimento_estimado_percentual)]"
                            :style="{ width: `${executiveSummary.potencial_crescimento_estimado_percentual}%` }"
                        ></div>
                    </div>
                </div>

                <!-- Badges de Maturidade e Crescimento -->
                <div class="flex flex-wrap gap-2 pt-2">
                    <div v-if="growthScore.scale_readiness" class="px-3 py-1 rounded-full bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 text-sm font-medium">
                        Maturidade: {{ growthScore.scale_readiness }}
                    </div>
                    <div v-if="executiveSummary.potencial_crescimento_estimado_percentual" class="px-3 py-1 rounded-full bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 text-sm font-medium">
                        Crescimento potencial: +{{ executiveSummary.potencial_crescimento_estimado_percentual }}%
                    </div>
                </div>
            </div>

            <!-- Final Verdict -->
            <div v-if="finalVerdict.conclusao_estrategica" class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700 space-y-3">
                <p class="text-gray-700 dark:text-gray-300 leading-relaxed">
                    {{ finalVerdict.conclusao_estrategica }}
                </p>

                <div v-if="finalVerdict.current_stage && finalVerdict.next_stage_requirement" class="flex items-center gap-2 text-sm">
                    <span class="px-3 py-1 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 font-medium">
                        {{ finalVerdict.current_stage }}
                    </span>
                    <ArrowRightIcon class="w-4 h-4 text-gray-400" />
                    <span class="text-gray-500 dark:text-gray-400">{{ finalVerdict.next_stage_requirement }}</span>
                </div>
            </div>
        </div>

        <!-- Section 2: Plano de Ação 90 Dias -->
        <div v-if="prioritizedRoadmap['30_dias'] || prioritizedRoadmap['60_dias'] || prioritizedRoadmap['90_dias']" class="px-6 py-6 border-t border-gray-200 dark:border-gray-700">
            <div class="flex items-center gap-2 mb-4">
                <CalendarDaysIcon class="w-5 h-5 text-gray-500" />
                <h4 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Plano de Ação 90 Dias</h4>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- 30 Dias -->
                <div v-if="prioritizedRoadmap['30_dias']?.length" class="bg-gray-50 dark:bg-gray-900/50 rounded-xl p-4 space-y-3">
                    <div class="h-1 w-full rounded bg-emerald-500"></div>
                    <div class="flex items-center justify-between">
                        <span class="px-2 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300 text-xs font-bold">
                            30 DIAS
                        </span>
                        <span class="text-sm text-gray-500 dark:text-gray-400">Quick Wins</span>
                    </div>
                    <div class="space-y-2">
                        <div
                            v-for="(item, index) in prioritizedRoadmap['30_dias']"
                            :key="index"
                            class="flex items-start gap-2"
                        >
                            <CheckCircleIcon class="w-4 h-4 text-emerald-400 flex-shrink-0 mt-0.5" />
                            <span class="text-base text-gray-700 dark:text-gray-300">{{ item }}</span>
                        </div>
                    </div>
                </div>

                <!-- 60 Dias -->
                <div v-if="prioritizedRoadmap['60_dias']?.length" class="bg-gray-50 dark:bg-gray-900/50 rounded-xl p-4 space-y-3">
                    <div class="h-1 w-full rounded bg-blue-500"></div>
                    <div class="flex items-center justify-between">
                        <span class="px-2 py-0.5 rounded-full bg-blue-100 dark:bg-blue-900/50 text-blue-700 dark:text-blue-300 text-xs font-bold">
                            60 DIAS
                        </span>
                        <span class="text-sm text-gray-500 dark:text-gray-400">Estruturação</span>
                    </div>
                    <div class="space-y-2">
                        <div
                            v-for="(item, index) in prioritizedRoadmap['60_dias']"
                            :key="index"
                            class="flex items-start gap-2"
                        >
                            <CheckCircleIcon class="w-4 h-4 text-blue-400 flex-shrink-0 mt-0.5" />
                            <span class="text-base text-gray-700 dark:text-gray-300">{{ item }}</span>
                        </div>
                    </div>
                </div>

                <!-- 90 Dias -->
                <div v-if="prioritizedRoadmap['90_dias']?.length" class="bg-gray-50 dark:bg-gray-900/50 rounded-xl p-4 space-y-3">
                    <div class="h-1 w-full rounded bg-purple-500"></div>
                    <div class="flex items-center justify-between">
                        <span class="px-2 py-0.5 rounded-full bg-purple-100 dark:bg-purple-900/50 text-purple-700 dark:text-purple-300 text-xs font-bold">
                            90 DIAS
                        </span>
                        <span class="text-sm text-gray-500 dark:text-gray-400">Escala</span>
                    </div>
                    <div class="space-y-2">
                        <div
                            v-for="(item, index) in prioritizedRoadmap['90_dias']"
                            :key="index"
                            class="flex items-start gap-2"
                        >
                            <CheckCircleIcon class="w-4 h-4 text-purple-400 flex-shrink-0 mt-0.5" />
                            <span class="text-base text-gray-700 dark:text-gray-300">{{ item }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 3: Impacto Financeiro -->
        <div v-if="impactEffortMatrix.quick_wins || financialOpportunities.length" class="px-6 py-6 border-t border-gray-200 dark:border-gray-700">
            <div class="flex items-center gap-2 mb-4">
                <BanknotesIcon class="w-5 h-5 text-gray-500" />
                <h4 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Impacto Financeiro</h4>
            </div>

            <!-- Matriz Impacto x Esforço -->
            <div v-if="impactEffortMatrix.quick_wins" class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-6">
                <!-- Quick Wins -->
                <div v-if="impactEffortMatrix.quick_wins?.length" class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-700 rounded-xl p-4 space-y-2">
                    <div class="flex items-center gap-2">
                        <BoltIcon class="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                        <div>
                            <div class="font-semibold text-emerald-900 dark:text-emerald-100">Quick Wins</div>
                            <div class="text-xs text-emerald-600 dark:text-emerald-400">Alto impacto, baixo esforço</div>
                        </div>
                    </div>
                    <ul class="space-y-1 text-sm text-gray-700 dark:text-gray-300">
                        <li v-for="(item, index) in impactEffortMatrix.quick_wins" :key="index" class="flex items-start gap-1">
                            <span class="text-emerald-500 mt-0.5">•</span>
                            <span>{{ item }}</span>
                        </li>
                    </ul>
                </div>

                <!-- High Impact -->
                <div v-if="impactEffortMatrix.high_impact?.length" class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-xl p-4 space-y-2">
                    <div class="flex items-center gap-2">
                        <ChartBarIcon class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                        <div>
                            <div class="font-semibold text-blue-900 dark:text-blue-100">High Impact</div>
                            <div class="text-xs text-blue-600 dark:text-blue-400">Alto impacto, alto esforço</div>
                        </div>
                    </div>
                    <ul class="space-y-1 text-sm text-gray-700 dark:text-gray-300">
                        <li v-for="(item, index) in impactEffortMatrix.high_impact" :key="index" class="flex items-start gap-1">
                            <span class="text-blue-500 mt-0.5">•</span>
                            <span>{{ item }}</span>
                        </li>
                    </ul>
                </div>

                <!-- Fill-ins -->
                <div v-if="impactEffortMatrix.fill_ins?.length" class="bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 space-y-2">
                    <div class="flex items-center gap-2">
                        <CheckCircleIcon class="w-5 h-5 text-gray-600 dark:text-gray-400" />
                        <div>
                            <div class="font-semibold text-gray-900 dark:text-gray-100">Fill-ins</div>
                            <div class="text-xs text-gray-600 dark:text-gray-400">Baixo impacto, baixo esforço</div>
                        </div>
                    </div>
                    <ul class="space-y-1 text-sm text-gray-700 dark:text-gray-300">
                        <li v-for="(item, index) in impactEffortMatrix.fill_ins" :key="index" class="flex items-start gap-1">
                            <span class="text-gray-500 mt-0.5">•</span>
                            <span>{{ item }}</span>
                        </li>
                    </ul>
                </div>

                <!-- Avoid -->
                <div v-if="impactEffortMatrix.avoid?.length" class="bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-700 rounded-xl p-4 space-y-2">
                    <div class="flex items-center gap-2">
                        <XMarkIcon class="w-5 h-5 text-rose-600 dark:text-rose-400" />
                        <div>
                            <div class="font-semibold text-rose-900 dark:text-rose-100">Avoid</div>
                            <div class="text-xs text-rose-600 dark:text-rose-400">Baixo impacto, alto esforço</div>
                        </div>
                    </div>
                    <ul class="space-y-1 text-sm text-gray-700 dark:text-gray-300">
                        <li v-for="(item, index) in impactEffortMatrix.avoid" :key="index" class="flex items-start gap-1">
                            <span class="text-rose-500 mt-0.5">•</span>
                            <span>{{ item }}</span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Financial Opportunities Table -->
            <div v-if="financialOpportunities.length" class="overflow-x-auto">
                <table class="w-full min-w-[600px] text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-900/50">
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Ação</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Tipo</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-700 dark:text-gray-300">Impacto Mensal</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-700 dark:text-gray-300">Impacto Anual</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        <tr v-for="(opp, index) in financialOpportunities" :key="index" class="hover:bg-gray-50 dark:hover:bg-gray-900/30">
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ opp.action }}</td>
                            <td class="px-4 py-3">
                                <span
                                    :class="[
                                        'px-2 py-0.5 rounded text-xs font-medium',
                                        impactTypeLabels[opp.impact_type]?.color || 'bg-gray-100 text-gray-700'
                                    ]"
                                >
                                    {{ impactTypeLabels[opp.impact_type]?.label || opp.impact_type }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right font-medium text-gray-900 dark:text-gray-100">
                                {{ formatCurrency(opp.estimated_monthly_impact) }}
                            </td>
                            <td class="px-4 py-3 text-right font-medium text-gray-900 dark:text-gray-100">
                                {{ formatCurrency(opp.estimated_annual_impact) }}
                            </td>
                        </tr>
                        <!-- Total Row -->
                        <tr class="bg-purple-50 dark:bg-purple-900/20 font-bold">
                            <td colspan="2" class="px-4 py-3 text-purple-900 dark:text-purple-100">Total</td>
                            <td class="px-4 py-3 text-right text-purple-900 dark:text-purple-100">
                                {{ formatCurrency(totalMonthlyImpact) }}
                            </td>
                            <td class="px-4 py-3 text-right text-purple-900 dark:text-purple-100">
                                {{ formatCurrency(totalAnnualImpact) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Section 4: Cenários de Crescimento -->
        <div v-if="growthScenarios.conservador || growthScenarios.base || growthScenarios.agressivo" class="px-6 py-6 border-t border-gray-200 dark:border-gray-700">
            <div class="flex items-center gap-2 mb-4">
                <PresentationChartBarIcon class="w-5 h-5 text-gray-500" />
                <h4 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Cenários de Crescimento</h4>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Conservador -->
                <div v-if="growthScenarios.conservador" class="bg-gray-50 dark:bg-gray-900/50 rounded-xl p-5 border border-gray-200 dark:border-gray-700">
                    <div class="space-y-3">
                        <div class="text-sm font-semibold text-gray-700 dark:text-gray-300">Conservador</div>

                        <div v-if="growthScenarios.conservador.receita_mensal_projetada > 0" class="flex items-baseline gap-2">
                            <div class="text-3xl font-bold text-gray-600 dark:text-gray-400">
                                +{{ growthScenarios.conservador.crescimento_percentual }}%
                            </div>
                            <ArrowTrendingUpIcon class="w-4 h-4 text-gray-500" />
                        </div>
                        <div v-else class="text-gray-500 dark:text-gray-400 text-sm">Sem dados suficientes</div>

                        <div v-if="growthScenarios.conservador.receita_mensal_projetada > 0" class="space-y-1 text-sm">
                            <div class="text-gray-600 dark:text-gray-400">
                                {{ formatCurrency(growthScenarios.conservador.receita_mensal_projetada) }}/mês
                            </div>
                            <div class="text-gray-600 dark:text-gray-400">
                                {{ formatCurrency(growthScenarios.conservador.receita_anual_projetada) }}/ano
                            </div>
                        </div>

                        <div v-if="growthScenarios.conservador.o_que_precisa_melhorar" class="pt-3 border-t border-gray-200 dark:border-gray-700">
                            <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">O que precisa:</div>
                            <div class="text-sm text-gray-700 dark:text-gray-300">
                                {{ growthScenarios.conservador.o_que_precisa_melhorar }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Base (Recomendado) -->
                <div v-if="growthScenarios.base" class="bg-blue-50 dark:bg-blue-900/20 rounded-xl p-5 border-2 border-blue-300 dark:border-blue-700 relative">
                    <span class="absolute top-2 right-2 bg-blue-500 text-white text-xs px-2 py-0.5 rounded-full font-medium">
                        Recomendado
                    </span>

                    <div class="space-y-3">
                        <div class="text-sm font-semibold text-blue-700 dark:text-blue-300">Base</div>

                        <div v-if="growthScenarios.base.receita_mensal_projetada > 0" class="flex items-baseline gap-2">
                            <div class="text-3xl font-bold text-blue-600 dark:text-blue-400">
                                +{{ growthScenarios.base.crescimento_percentual }}%
                            </div>
                            <ArrowTrendingUpIcon class="w-4 h-4 text-blue-500" />
                        </div>
                        <div v-else class="text-blue-500 dark:text-blue-400 text-sm">Sem dados suficientes</div>

                        <div v-if="growthScenarios.base.receita_mensal_projetada > 0" class="space-y-1 text-sm">
                            <div class="text-blue-600 dark:text-blue-400">
                                {{ formatCurrency(growthScenarios.base.receita_mensal_projetada) }}/mês
                            </div>
                            <div class="text-blue-600 dark:text-blue-400">
                                {{ formatCurrency(growthScenarios.base.receita_anual_projetada) }}/ano
                            </div>
                        </div>

                        <div v-if="growthScenarios.base.o_que_precisa_melhorar" class="pt-3 border-t border-blue-200 dark:border-blue-700">
                            <div class="text-xs text-blue-500 dark:text-blue-400 mb-1">O que precisa:</div>
                            <div class="text-sm text-blue-700 dark:text-blue-300">
                                {{ growthScenarios.base.o_que_precisa_melhorar }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Agressivo -->
                <div v-if="growthScenarios.agressivo" class="bg-emerald-50 dark:bg-emerald-900/20 rounded-xl p-5 border border-emerald-200 dark:border-emerald-700">
                    <div class="space-y-3">
                        <div class="text-sm font-semibold text-emerald-700 dark:text-emerald-300">Agressivo</div>

                        <div v-if="growthScenarios.agressivo.receita_mensal_projetada > 0" class="flex items-baseline gap-2">
                            <div class="text-3xl font-bold text-emerald-600 dark:text-emerald-400">
                                +{{ growthScenarios.agressivo.crescimento_percentual }}%
                            </div>
                            <ArrowTrendingUpIcon class="w-4 h-4 text-emerald-500" />
                        </div>
                        <div v-else class="text-emerald-500 dark:text-emerald-400 text-sm">Sem dados suficientes</div>

                        <div v-if="growthScenarios.agressivo.receita_mensal_projetada > 0" class="space-y-1 text-sm">
                            <div class="text-emerald-600 dark:text-emerald-400">
                                {{ formatCurrency(growthScenarios.agressivo.receita_mensal_projetada) }}/mês
                            </div>
                            <div class="text-emerald-600 dark:text-emerald-400">
                                {{ formatCurrency(growthScenarios.agressivo.receita_anual_projetada) }}/ano
                            </div>
                        </div>

                        <div v-if="growthScenarios.agressivo.o_que_precisa_melhorar" class="pt-3 border-t border-emerald-200 dark:border-emerald-700">
                            <div class="text-xs text-emerald-500 dark:text-emerald-400 mb-1">O que precisa:</div>
                            <div class="text-sm text-emerald-700 dark:text-emerald-300">
                                {{ growthScenarios.agressivo.o_que_precisa_melhorar }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
