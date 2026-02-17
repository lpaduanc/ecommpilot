<script setup lang="ts">
import { computed } from 'vue';
import {
    ClockIcon,
    CheckCircleIcon,
    CubeIcon,
    WrenchScrewdriverIcon,
    ChartBarIcon,
    CurrencyDollarIcon,
} from '@heroicons/vue/24/outline';

interface ParsedStep {
    stepNumber: number;
    title: string;
    period: string;
    description: string;
    what: string;
    how: string;
    result: string;
    resources: string;
    isLegacy: boolean;
}

const props = defineProps<{
    recommendedAction: string | string[];
}>();

function resolveContent(): string {
    if (!props.recommendedAction) return '';
    if (Array.isArray(props.recommendedAction)) {
        return props.recommendedAction.join('\n\n');
    }
    try {
        const parsed = JSON.parse(props.recommendedAction);
        if (Array.isArray(parsed)) return parsed.join('\n\n');
    } catch { /* not JSON */ }
    return props.recommendedAction;
}

/**
 * Parse new format: **PASSO X: Title (Period)**
 */
function parseNewFormat(content: string): ParsedStep[] {
    const stepRegex = /\*\*PASSO (\d+):\s*([^(]+)\s*\(([^)]+)\)\*\*/g;
    const matches: { index: number; stepNumber: number; title: string; period: string }[] = [];
    let match;

    while ((match = stepRegex.exec(content)) !== null) {
        matches.push({
            index: match.index,
            stepNumber: parseInt(match[1]),
            title: match[2].trim(),
            period: match[3].trim(),
        });
    }

    if (matches.length === 0) return [];

    return matches.map((m, idx) => {
        const endIdx = idx < matches.length - 1 ? matches[idx + 1].index : content.length;
        const stepContent = content.substring(m.index, endIdx);

        const whatMatch = stepContent.match(/•\s*O QUE:\s*([^\n•]+)/);
        const howMatch = stepContent.match(/•\s*COMO:\s*([^•]+?)(?=•|\*\*|$)/s);
        const resultMatch = stepContent.match(/•\s*RESULTADO(?:\s+ESPERADO)?:\s*([^•]+?)(?=•|\*\*|$)/s);
        const resourcesMatch = stepContent.match(/•\s*RECURSOS:\s*([^•\n]+)/);

        return {
            stepNumber: m.stepNumber,
            title: m.title,
            period: m.period,
            description: '',
            what: whatMatch ? whatMatch[1].trim() : '',
            how: howMatch ? howMatch[1].trim().replace(/\n/g, ' ').replace(/\s+/g, ' ') : '',
            result: resultMatch ? resultMatch[1].trim() : '',
            resources: resourcesMatch ? resourcesMatch[1].trim() : '',
            isLegacy: false,
        };
    });
}

/**
 * Parse legacy format: "1. Action text\n2. Action text" or "1. Action 2. Action"
 */
function parseLegacyFormat(content: string): ParsedStep[] {
    // Try multiline first: "1. text\n2. text"
    let items = content.split(/\n/).map(l => l.trim()).filter(Boolean);
    const numbered = items.filter(l => /^\d+[\.\)]\s/.test(l));

    // If multiline didn't work well, try inline: "1. text 2. text 3. text"
    if (numbered.length < 2) {
        const inlineSplit = content.split(/(?=\d+[\.\)]\s)/).map(s => s.trim()).filter(Boolean);
        const inlineNumbered = inlineSplit.filter(l => /^\d+[\.\)]\s/.test(l));
        if (inlineNumbered.length >= 2) {
            return inlineNumbered.map((item, idx) => ({
                stepNumber: idx + 1,
                title: `Passo ${idx + 1}`,
                period: '',
                description: item.replace(/^\d+[\.\)]\s*/, '').trim(),
                what: '', how: '', result: '', resources: '',
                isLegacy: true,
            }));
        }
    }

    if (numbered.length < 2) return [];

    return numbered.map((item, idx) => ({
        stepNumber: idx + 1,
        title: `Passo ${idx + 1}`,
        period: '',
        description: item.replace(/^\d+[\.\)]\s*/, '').trim(),
        what: '', how: '', result: '', resources: '',
        isLegacy: true,
    }));
}

const parsedSteps = computed<ParsedStep[]>(() => {
    const content = resolveContent();
    if (!content) return [];

    // Try new format first
    const newSteps = parseNewFormat(content);
    if (newSteps.length > 0) return newSteps;

    // Fallback to legacy numbered list
    return parseLegacyFormat(content);
});
</script>

<template>
    <div v-if="parsedSteps.length > 0" class="relative">
        <!-- Timeline Line (hidden on mobile) -->
        <div class="hidden sm:block absolute left-6 top-8 bottom-8 w-0.5 bg-gradient-to-b from-primary-200 via-primary-300 to-primary-200 dark:from-primary-800 dark:via-primary-700 dark:to-primary-800"></div>

        <!-- Steps -->
        <div class="space-y-6 sm:space-y-8">
            <div
                v-for="(step, index) in parsedSteps"
                :key="step.stepNumber"
                class="relative"
            >
                <!-- Step Number Circle -->
                <div class="absolute left-0 top-0 z-10 flex items-center justify-center w-10 h-10 sm:w-12 sm:h-12 rounded-full bg-gradient-to-br from-primary-500 to-secondary-500 shadow-lg shadow-primary-500/30 ring-4 ring-white dark:ring-gray-800">
                    <span class="text-base sm:text-lg font-bold text-white">{{ step.stepNumber }}</span>
                </div>

                <!-- Step Content -->
                <div class="ml-14 sm:ml-20 bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm hover:shadow-md transition-shadow">
                    <!-- Header -->
                    <div class="px-4 sm:px-6 py-3 sm:py-4 bg-gradient-to-r from-primary-50 to-secondary-50 dark:from-primary-900/20 dark:to-secondary-900/20 border-b border-gray-200 dark:border-gray-700 rounded-t-2xl">
                        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-2 sm:gap-4">
                            <h4 class="text-base sm:text-lg font-semibold text-gray-900 dark:text-gray-100 flex-1">
                                {{ step.title }}
                            </h4>
                            <div v-if="step.period" class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-white/60 dark:bg-gray-800/60 border border-primary-200 dark:border-primary-800 backdrop-blur-sm self-start">
                                <ClockIcon class="w-4 h-4 text-primary-600 dark:text-primary-400" />
                                <span class="text-sm font-medium text-primary-700 dark:text-primary-300 whitespace-nowrap">
                                    {{ step.period }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Legacy: simple description -->
                    <div v-if="step.isLegacy" class="p-4 sm:p-6">
                        <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">{{ step.description }}</p>
                    </div>

                    <!-- New format: detailed sub-items -->
                    <div v-else class="p-4 sm:p-6 space-y-3 sm:space-y-4">
                        <!-- O QUE -->
                        <div v-if="step.what" class="flex items-start gap-3 sm:gap-4">
                            <div class="flex-shrink-0 w-8 h-8 sm:w-10 sm:h-10 rounded-xl bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                                <CubeIcon class="w-4 h-4 sm:w-5 sm:h-5 text-blue-600 dark:text-blue-400" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <h5 class="text-xs sm:text-sm font-semibold text-blue-900 dark:text-blue-200 mb-1">O QUE</h5>
                                <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">{{ step.what }}</p>
                            </div>
                        </div>

                        <!-- COMO -->
                        <div v-if="step.how" class="flex items-start gap-3 sm:gap-4">
                            <div class="flex-shrink-0 w-8 h-8 sm:w-10 sm:h-10 rounded-xl bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center">
                                <WrenchScrewdriverIcon class="w-4 h-4 sm:w-5 sm:h-5 text-purple-600 dark:text-purple-400" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <h5 class="text-xs sm:text-sm font-semibold text-purple-900 dark:text-purple-200 mb-1">COMO</h5>
                                <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">{{ step.how }}</p>
                            </div>
                        </div>

                        <!-- RESULTADO -->
                        <div v-if="step.result" class="flex items-start gap-3 sm:gap-4">
                            <div class="flex-shrink-0 w-8 h-8 sm:w-10 sm:h-10 rounded-xl bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center">
                                <ChartBarIcon class="w-4 h-4 sm:w-5 sm:h-5 text-emerald-600 dark:text-emerald-400" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <h5 class="text-xs sm:text-sm font-semibold text-emerald-900 dark:text-emerald-200 mb-1">RESULTADO</h5>
                                <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">{{ step.result }}</p>
                            </div>
                        </div>

                        <!-- RECURSOS -->
                        <div v-if="step.resources" class="flex items-start gap-3 sm:gap-4">
                            <div class="flex-shrink-0 w-8 h-8 sm:w-10 sm:h-10 rounded-xl bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center">
                                <CurrencyDollarIcon class="w-4 h-4 sm:w-5 sm:h-5 text-amber-600 dark:text-amber-400" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <h5 class="text-xs sm:text-sm font-semibold text-amber-900 dark:text-amber-200 mb-1">RECURSOS</h5>
                                <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">{{ step.resources }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Completion Indicator -->
        <div class="relative mt-6 sm:mt-8">
            <div class="absolute left-0 z-10 flex items-center justify-center w-10 h-10 sm:w-12 sm:h-12 rounded-full bg-gray-200 dark:bg-gray-700 ring-4 ring-white dark:ring-gray-800">
                <CheckCircleIcon class="w-5 h-5 sm:w-6 sm:h-6 text-gray-400 dark:text-gray-500" />
            </div>
            <div class="ml-14 sm:ml-20 flex items-center h-10 sm:h-12">
                <span class="text-xs sm:text-sm font-medium text-gray-500 dark:text-gray-400">
                    Pronto para começar
                </span>
            </div>
        </div>
    </div>

    <!-- Fallback for unparsed format -->
    <div v-else-if="recommendedAction" class="bg-gray-50 dark:bg-gray-900/50 rounded-xl p-4 border border-gray-200 dark:border-gray-700">
        <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap leading-relaxed">
            {{ Array.isArray(recommendedAction) ? recommendedAction.join('\n\n') : recommendedAction }}
        </p>
    </div>
</template>
