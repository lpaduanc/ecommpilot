<script setup>
import { 
    ArrowTrendingUpIcon,
    CurrencyDollarIcon,
    SparklesIcon,
    ChevronRightIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    opportunities: { type: Array, default: () => [] },
});
</script>

<template>
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <!-- Header -->
        <div class="relative px-6 py-5 bg-gradient-to-r from-emerald-500 to-teal-500 overflow-hidden">
            <!-- Background Pattern -->
            <div class="absolute inset-0" style="background-image: radial-gradient(rgba(255,255,255,0.1) 1px, transparent 1px); background-size: 20px 20px;"></div>
            
            <div class="relative flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-white/20 backdrop-blur-sm flex items-center justify-center">
                    <ArrowTrendingUpIcon class="w-5 h-5 text-white" />
                </div>
                <div>
                    <h3 class="text-lg font-display font-bold text-white">Oportunidades</h3>
                    <p class="text-emerald-100 text-sm">Potencial de receita identificado</p>
                </div>
            </div>
        </div>
        
        <!-- Opportunities List -->
        <div class="divide-y divide-gray-100">
            <div
                v-for="(opportunity, index) in opportunities"
                :key="index"
                class="group p-5 hover:bg-gradient-to-r hover:from-emerald-50/50 hover:to-transparent transition-all duration-300 cursor-pointer"
            >
                <div class="flex items-start gap-4">
                    <!-- Icon -->
                    <div class="flex-shrink-0 w-12 h-12 rounded-xl bg-gradient-to-br from-emerald-100 to-teal-100 flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
                        <SparklesIcon class="w-6 h-6 text-emerald-600" />
                    </div>
                    
                    <!-- Content -->
                    <div class="flex-1 min-w-0">
                        <div class="flex items-start justify-between gap-3 mb-2">
                            <h4 class="font-semibold text-gray-900 group-hover:text-emerald-700 transition-colors">
                                {{ opportunity.title }}
                            </h4>
                            <div class="flex items-center gap-1 px-3 py-1 rounded-full bg-emerald-100 text-emerald-700 font-bold text-sm flex-shrink-0">
                                <CurrencyDollarIcon class="w-4 h-4" />
                                {{ opportunity.potential_revenue }}
                            </div>
                        </div>
                        <p class="text-sm text-gray-500 line-clamp-2 leading-relaxed">
                            {{ opportunity.description }}
                        </p>
                        
                        <!-- Action Hint -->
                        <div class="flex items-center gap-1 mt-3 text-sm text-emerald-600 font-medium opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                            <span>Explorar oportunidade</span>
                            <ChevronRightIcon class="w-4 h-4 transform group-hover:translate-x-1 transition-transform" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Potential -->
        <div class="px-6 py-4 bg-gradient-to-r from-gray-50 to-emerald-50/50 border-t border-gray-100">
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-gray-600">Potencial total identificado</span>
                <div class="flex items-center gap-2">
                    <div class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></div>
                    <span class="font-display font-bold text-emerald-700">
                        {{ opportunities.length }} oportunidades
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>
