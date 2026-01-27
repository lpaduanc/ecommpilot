<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/authStore';
import { useDashboardStore } from '../stores/dashboardStore';
import { useNotificationStore } from '../stores/notificationStore';
import api from '../services/api';
import BaseCard from '../components/common/BaseCard.vue';
import BaseButton from '../components/common/BaseButton.vue';
import BaseInput from '../components/common/BaseInput.vue';
import LoadingSpinner from '../components/common/LoadingSpinner.vue';
import {
    Cog6ToothIcon,
    BuildingStorefrontIcon,
    CurrencyDollarIcon,
    ChartBarIcon,
    GlobeAltIcon,
    PlusIcon,
    TrashIcon,
    CheckCircleIcon,
} from '@heroicons/vue/24/outline';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const dashboardStore = useDashboardStore();
const notificationStore = useNotificationStore();

const isLoading = ref(true);
const isSaving = ref(false);
const niches = ref([]);

const form = reactive({
    niche: '',
    niche_subcategory: '',
    monthly_goal: null,
    annual_goal: null,
    target_ticket: null,
    monthly_revenue: null,
    monthly_visits: null,
    competitors: [],
});

const errors = reactive({
    niche: '',
    niche_subcategory: '',
    monthly_goal: '',
    annual_goal: '',
    target_ticket: '',
    monthly_revenue: '',
    monthly_visits: '',
    competitors: '',
});

const storeId = computed(() => {
    return route.params.id || dashboardStore.activeStore?.id;
});

const storeName = computed(() => {
    return dashboardStore.activeStore?.name || 'Loja';
});

const selectedNiche = computed(() => {
    return niches.value.find(n => n.value === form.niche);
});

const subcategories = computed(() => {
    return selectedNiche.value?.subcategories || [];
});

const canEdit = computed(() => {
    return authStore.hasPermission('integrations.manage');
});

watch(() => form.niche, () => {
    // Reset subcategory when niche changes
    if (!selectedNiche.value?.subcategories.some(s => s.value === form.niche_subcategory)) {
        form.niche_subcategory = '';
    }
});

async function loadNiches() {
    try {
        const response = await api.get('/niches');
        niches.value = response.data.data;
    } catch {
        notificationStore.error('Erro ao carregar lista de nichos');
    }
}

async function loadConfig() {
    if (!storeId.value) {
        notificationStore.error('Nenhuma loja selecionada');
        router.push({ name: 'integrations' });
        return;
    }

    isLoading.value = true;
    try {
        const response = await api.get(`/stores/${storeId.value}/config`);
        const data = response.data.data;

        form.niche = data.niche || '';
        form.niche_subcategory = data.niche_subcategory || '';
        form.monthly_goal = data.monthly_goal;
        form.annual_goal = data.annual_goal;
        form.target_ticket = data.target_ticket;
        form.monthly_revenue = data.monthly_revenue;
        form.monthly_visits = data.monthly_visits;
        form.competitors = data.competitors || [];
    } catch {
        notificationStore.error('Erro ao carregar configurações da loja');
    } finally {
        isLoading.value = false;
    }
}

function clearErrors() {
    Object.keys(errors).forEach(key => {
        errors[key] = '';
    });
}

function validate() {
    let valid = true;
    clearErrors();

    if (form.niche && !form.niche_subcategory) {
        errors.niche_subcategory = 'Selecione uma subcategoria';
        valid = false;
    }

    return valid;
}

async function saveConfig() {
    if (!validate()) return;

    isSaving.value = true;
    clearErrors();

    try {
        await api.put(`/stores/${storeId.value}/config`, form);
        notificationStore.success('Configurações salvas com sucesso!');
    } catch (error) {
        if (error.response?.data?.errors) {
            Object.keys(error.response.data.errors).forEach(key => {
                if (errors[key] !== undefined) {
                    errors[key] = error.response.data.errors[key][0];
                }
            });
        }

        notificationStore.error(error.response?.data?.message || 'Erro ao salvar configurações');
    } finally {
        isSaving.value = false;
    }
}

function addCompetitor() {
    if (form.competitors.length >= 10) {
        notificationStore.warning('Limite máximo de 10 concorrentes atingido');
        return;
    }
    form.competitors.push({ url: '', name: '' });
}

function removeCompetitor(index) {
    form.competitors.splice(index, 1);
}

function formatCurrency(value) {
    if (value === null || value === undefined || value === '') return '';
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    }).format(value);
}

function parseCurrency(value) {
    if (!value) return null;
    // Remove currency symbol, thousand separators, and convert decimal separator
    const cleaned = String(value)
        .replace(/[R$\s]/g, '')
        .replace(/\./g, '')
        .replace(',', '.');
    const parsed = parseFloat(cleaned);
    return isNaN(parsed) ? null : parsed;
}

onMounted(async () => {
    await loadNiches();
    await loadConfig();
});
</script>

<template>
    <div class="min-h-screen -m-8 -mt-8">
        <!-- Hero Header with Gradient -->
        <div class="relative overflow-hidden bg-gradient-to-br from-slate-900 via-primary-950 to-secondary-950 dark:from-gray-950 dark:via-gray-900 dark:to-gray-950 px-8 py-12">
            <!-- Background Elements -->
            <div class="absolute inset-0 overflow-hidden">
                <div class="absolute -top-40 -right-40 w-80 h-80 bg-primary-500/20 rounded-full blur-3xl"></div>
                <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-secondary-500/20 rounded-full blur-3xl"></div>
                <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-96 h-96 bg-accent-500/10 rounded-full blur-3xl"></div>
                <!-- Grid Pattern -->
                <div class="absolute inset-0" style="background-image: radial-gradient(rgba(255,255,255,0.03) 1px, transparent 1px); background-size: 40px 40px;"></div>
            </div>

            <div class="relative z-10 max-w-7xl mx-auto">
                <div class="flex items-center gap-3">
                    <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-primary-400 to-secondary-500 flex items-center justify-center shadow-lg shadow-primary-500/30">
                        <BuildingStorefrontIcon class="w-7 h-7 text-white" />
                    </div>
                    <div>
                        <h1 class="text-3xl lg:text-4xl font-display font-bold text-white">
                            Configurar Loja
                        </h1>
                        <p class="text-primary-200/80 text-sm lg:text-base">
                            {{ storeName }} - Defina seu nicho, metas e concorrentes
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="px-8 py-8 bg-gradient-to-b from-gray-100 to-gray-50 dark:from-gray-900 dark:to-gray-950 min-h-[calc(100vh-200px)]">
            <!-- Loading State -->
            <div v-if="isLoading" class="flex items-center justify-center py-32">
                <div class="relative">
                    <div class="w-20 h-20 rounded-full bg-gradient-to-r from-primary-500 to-secondary-500"></div>
                    <LoadingSpinner size="xl" class="absolute inset-0 m-auto text-white" />
                </div>
            </div>

            <template v-else>
                <div class="max-w-4xl mx-auto space-y-8">
                    <!-- Niche Section -->
                    <BaseCard padding="lg">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-6 flex items-center gap-2">
                            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-purple-500 to-pink-600 flex items-center justify-center">
                                <Cog6ToothIcon class="w-4 h-4 text-white" />
                            </div>
                            Nicho de Mercado
                        </h2>
                        <p class="text-gray-500 dark:text-gray-400 mb-6">
                            Selecione o nicho da sua loja para obter análises e benchmarks personalizados.
                        </p>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Niche Select -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                    Nicho Principal
                                </label>
                                <select
                                    v-model="form.niche"
                                    :disabled="!canEdit"
                                    class="w-full px-4 py-2.5 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all disabled:opacity-60 disabled:cursor-not-allowed"
                                    :class="errors.niche ? 'border-danger-500' : ''"
                                >
                                    <option value="">Selecione um nicho</option>
                                    <option v-for="niche in niches" :key="niche.value" :value="niche.value">
                                        {{ niche.label }}
                                    </option>
                                </select>
                                <p v-if="errors.niche" class="text-sm text-danger-500 mt-1">{{ errors.niche }}</p>
                            </div>

                            <!-- Subcategory Select -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                    Subcategoria
                                </label>
                                <select
                                    v-model="form.niche_subcategory"
                                    :disabled="!canEdit || !form.niche"
                                    class="w-full px-4 py-2.5 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all disabled:opacity-60 disabled:cursor-not-allowed"
                                    :class="errors.niche_subcategory ? 'border-danger-500' : ''"
                                >
                                    <option value="">Selecione uma subcategoria</option>
                                    <option v-for="sub in subcategories" :key="sub.value" :value="sub.value">
                                        {{ sub.label }}
                                    </option>
                                </select>
                                <p v-if="errors.niche_subcategory" class="text-sm text-danger-500 mt-1">{{ errors.niche_subcategory }}</p>
                                <p v-else-if="!form.niche" class="text-sm text-gray-400 mt-1">
                                    Selecione um nicho primeiro
                                </p>
                            </div>
                        </div>
                    </BaseCard>

                    <!-- Goals Section -->
                    <BaseCard padding="lg">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-6 flex items-center gap-2">
                            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-green-500 to-emerald-600 flex items-center justify-center">
                                <CurrencyDollarIcon class="w-4 h-4 text-white" />
                            </div>
                            Metas e Faturamento
                        </h2>
                        <p class="text-gray-500 dark:text-gray-400 mb-6">
                            Defina suas metas financeiras para acompanhamento na análise.
                        </p>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <BaseInput
                                v-model="form.monthly_goal"
                                type="number"
                                label="Meta Mensal (R$)"
                                placeholder="Ex: 50000"
                                :error="errors.monthly_goal"
                                :disabled="!canEdit"
                                step="0.01"
                                min="0"
                            />

                            <BaseInput
                                v-model="form.annual_goal"
                                type="number"
                                label="Meta Anual (R$)"
                                placeholder="Ex: 600000"
                                :error="errors.annual_goal"
                                :disabled="!canEdit"
                                step="0.01"
                                min="0"
                            />

                            <BaseInput
                                v-model="form.target_ticket"
                                type="number"
                                label="Ticket Médio Alvo (R$)"
                                placeholder="Ex: 150"
                                :error="errors.target_ticket"
                                :disabled="!canEdit"
                                step="0.01"
                                min="0"
                            />

                            <BaseInput
                                v-model="form.monthly_revenue"
                                type="number"
                                label="Faturamento Mensal Atual (R$)"
                                placeholder="Ex: 35000"
                                :error="errors.monthly_revenue"
                                :disabled="!canEdit"
                                step="0.01"
                                min="0"
                            />
                        </div>
                    </BaseCard>

                    <!-- Traffic Section -->
                    <BaseCard padding="lg">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-6 flex items-center gap-2">
                            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center">
                                <ChartBarIcon class="w-4 h-4 text-white" />
                            </div>
                            Tráfego
                        </h2>
                        <p class="text-gray-500 dark:text-gray-400 mb-6">
                            Informe a quantidade de visitas mensais da sua loja.
                        </p>

                        <div class="max-w-md">
                            <BaseInput
                                v-model="form.monthly_visits"
                                type="number"
                                label="Visitas Mensais"
                                placeholder="Ex: 15000"
                                :error="errors.monthly_visits"
                                :disabled="!canEdit"
                                min="0"
                            />
                        </div>
                    </BaseCard>

                    <!-- Competitors Section -->
                    <BaseCard padding="lg">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4 flex items-center gap-2">
                            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-amber-500 to-orange-600 flex items-center justify-center">
                                <GlobeAltIcon class="w-4 h-4 text-white" />
                            </div>
                            Concorrentes
                        </h2>
                        <p class="text-gray-500 dark:text-gray-400 mb-6">
                            Liste os principais concorrentes da sua loja para análise competitiva. Máximo de 10 concorrentes.
                        </p>

                        <div class="space-y-4">
                            <div
                                v-for="(competitor, index) in form.competitors"
                                :key="index"
                                class="flex items-start gap-4 p-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50"
                            >
                                <div class="flex-1 grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                            URL do Site
                                        </label>
                                        <input
                                            v-model="competitor.url"
                                            type="url"
                                            placeholder="https://concorrente.com.br"
                                            :disabled="!canEdit"
                                            class="w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all disabled:opacity-60"
                                        />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                            Nome (opcional)
                                        </label>
                                        <input
                                            v-model="competitor.name"
                                            type="text"
                                            placeholder="Nome do concorrente"
                                            :disabled="!canEdit"
                                            class="w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all disabled:opacity-60"
                                        />
                                    </div>
                                </div>
                                <button
                                    v-if="canEdit"
                                    @click="removeCompetitor(index)"
                                    class="mt-6 p-2 rounded-lg text-danger-500 hover:bg-danger-50 dark:hover:bg-danger-900/20 transition-colors"
                                    title="Remover concorrente"
                                >
                                    <TrashIcon class="w-5 h-5" />
                                </button>
                            </div>

                            <button
                                v-if="canEdit && form.competitors.length < 10"
                                @click="addCompetitor"
                                class="flex items-center gap-2 px-4 py-2 rounded-xl border-2 border-dashed border-gray-300 dark:border-gray-600 text-gray-500 dark:text-gray-400 hover:border-primary-500 hover:text-primary-500 transition-colors w-full justify-center"
                            >
                                <PlusIcon class="w-5 h-5" />
                                Adicionar Concorrente
                            </button>

                            <p v-if="form.competitors.length === 0" class="text-center text-gray-400 py-4">
                                Nenhum concorrente adicionado
                            </p>
                        </div>

                        <p v-if="errors.competitors" class="text-sm text-danger-500 mt-2">{{ errors.competitors }}</p>
                    </BaseCard>

                    <!-- Save Button -->
                    <div class="flex justify-end gap-4">
                        <BaseButton
                            variant="secondary"
                            @click="router.push({ name: 'integrations' })"
                        >
                            Voltar
                        </BaseButton>
                        <BaseButton
                            v-if="canEdit"
                            @click="saveConfig"
                            :loading="isSaving"
                        >
                            <CheckCircleIcon class="w-5 h-5" />
                            Salvar Configurações
                        </BaseButton>
                    </div>

                    <p v-if="!canEdit" class="text-center text-sm text-gray-500 dark:text-gray-400 italic">
                        Você não possui permissão para editar as configurações da loja.
                    </p>
                </div>
            </template>
        </div>
    </div>
</template>
