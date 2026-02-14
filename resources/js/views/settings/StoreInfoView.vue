<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue';
import { useAuthStore } from '../../stores/authStore';
import { useNotificationStore } from '../../stores/notificationStore';
import api from '../../services/api';
import BaseCard from '../../components/common/BaseCard.vue';
import BaseButton from '../../components/common/BaseButton.vue';
import BaseInput from '../../components/common/BaseInput.vue';
import LoadingSpinner from '../../components/common/LoadingSpinner.vue';
import {
    BuildingStorefrontIcon,
    Cog6ToothIcon,
    CurrencyDollarIcon,
    ChartBarIcon,
    GlobeAltIcon,
    PlusIcon,
    TrashIcon,
    EnvelopeIcon,
    CalendarIcon,
    ServerIcon,
} from '@heroicons/vue/24/outline';

const authStore = useAuthStore();
const notificationStore = useNotificationStore();

const isLoadingStore = ref(false);
const isLoadingConfig = ref(false);
const isSaving = ref(false);
const storeInfo = ref(null);
const niches = ref([]);

const form = reactive({
    niche: '',
    niche_subcategory: '',
    website_url: '',
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
    website_url: '',
    monthly_goal: '',
    annual_goal: '',
    target_ticket: '',
    monthly_revenue: '',
    monthly_visits: '',
    competitors: '',
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

async function loadStoreInfo() {
    isLoadingStore.value = true;
    try {
        const response = await api.get('/integrations/my-stores');
        const activeStoreId = response.data.active_store_id;
        if (activeStoreId) {
            storeInfo.value = response.data.stores.find(s => s.id === activeStoreId);
            // Load config for this store
            await loadConfig(activeStoreId);
        }
    } catch {
        storeInfo.value = null;
    } finally {
        isLoadingStore.value = false;
    }
}

async function loadNiches() {
    try {
        const response = await api.get('/niches');
        niches.value = response.data.data;
    } catch {
        notificationStore.error('Erro ao carregar lista de nichos');
    }
}

async function loadConfig(storeId) {
    if (!storeId) return;

    isLoadingConfig.value = true;
    try {
        const response = await api.get(`/stores/${storeId}/config`);
        const data = response.data.data;

        form.niche = data.niche || '';
        form.niche_subcategory = data.niche_subcategory || '';
        form.website_url = data.website_url || '';
        form.monthly_goal = data.monthly_goal;
        form.annual_goal = data.annual_goal;
        form.target_ticket = data.target_ticket;
        form.monthly_revenue = data.monthly_revenue;
        form.monthly_visits = data.monthly_visits;
        form.competitors = data.competitors || [];
    } catch {
        notificationStore.error('Erro ao carregar configurações da loja');
    } finally {
        isLoadingConfig.value = false;
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
    if (!storeInfo.value?.id) {
        notificationStore.error('Nenhuma loja conectada');
        return;
    }

    if (!validate()) return;

    isSaving.value = true;
    clearErrors();

    try {
        await api.put(`/stores/${storeInfo.value.id}/config`, form);
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

onMounted(async () => {
    await loadNiches();
    await loadStoreInfo();
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
                <div class="flex items-center gap-2 sm:gap-3">
                    <div class="w-10 sm:w-12 lg:w-14 h-10 sm:h-12 lg:h-14 rounded-xl sm:rounded-2xl bg-gradient-to-br from-green-400 to-emerald-500 flex items-center justify-center shadow-lg shadow-green-500/30 flex-shrink-0">
                        <BuildingStorefrontIcon class="w-5 sm:w-6 lg:w-7 h-5 sm:h-6 lg:h-7 text-white" />
                    </div>
                    <div class="min-w-0">
                        <h1 class="text-xl sm:text-2xl lg:text-4xl font-display font-bold text-white">
                            Configuração da Loja
                        </h1>
                        <p class="text-primary-200/80 text-xs sm:text-sm lg:text-base">
                            <template v-if="storeInfo && !isLoadingStore">
                                {{ storeInfo.name }}
                            </template>
                            <template v-else>
                                Informações, metas e personalização da sua loja
                            </template>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="px-8 py-8 bg-gradient-to-b from-gray-100 to-gray-50 dark:from-gray-900 dark:to-gray-950 min-h-[calc(100vh-200px)]">

            <!-- Loading State -->
            <div v-if="isLoadingStore" class="flex items-center justify-center py-32">
                <LoadingSpinner size="xl" />
            </div>

            <!-- No Store Connected -->
            <div v-else-if="!storeInfo" class="max-w-4xl mx-auto">
                <BaseCard padding="lg">
                    <div class="text-center py-12">
                        <div class="w-16 h-16 rounded-2xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center mx-auto mb-4">
                            <BuildingStorefrontIcon class="w-8 h-8 text-gray-400" />
                        </div>
                        <p class="text-gray-500 dark:text-gray-400 mb-4">
                            Nenhuma loja conectada
                        </p>
                        <BaseButton @click="$router.push({ name: 'integrations' })">
                            Conectar Loja
                        </BaseButton>
                    </div>
                </BaseCard>
            </div>

            <!-- Store Content -->
            <div v-else class="max-w-4xl mx-auto space-y-8">
            <!-- Store Info Section -->
            <BaseCard padding="lg">
                <div class="mb-6">
                    <div class="flex items-center gap-2 mb-2">
                        <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-green-500 to-emerald-600 flex items-center justify-center">
                            <BuildingStorefrontIcon class="w-4 h-4 text-white" />
                        </div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            Informações da Loja
                        </h2>
                    </div>
                    <p class="text-gray-500 dark:text-gray-400 text-sm">
                        Dados sincronizados automaticamente da plataforma
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Store Name -->
                    <div class="p-5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gradient-to-br from-white to-gray-50 dark:from-gray-800/50 dark:to-gray-800/30 hover:shadow-md transition-shadow">
                        <div class="flex items-start gap-3">
                            <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-green-500 to-emerald-600 flex items-center justify-center flex-shrink-0">
                                <BuildingStorefrontIcon class="w-5 h-5 text-white" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                                    Nome da Loja
                                </label>
                                <p class="text-gray-900 dark:text-gray-100 font-semibold truncate">
                                    {{ storeInfo.name || '-' }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Store Domain -->
                    <div class="p-5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gradient-to-br from-white to-gray-50 dark:from-gray-800/50 dark:to-gray-800/30 hover:shadow-md transition-shadow">
                        <div class="flex items-start gap-3">
                            <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center flex-shrink-0">
                                <GlobeAltIcon class="w-5 h-5 text-white" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                                    Domínio
                                </label>
                                <p class="text-gray-900 dark:text-gray-100 font-semibold truncate">
                                    {{ storeInfo.domain || '-' }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Store Email -->
                    <div class="p-5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gradient-to-br from-white to-gray-50 dark:from-gray-800/50 dark:to-gray-800/30 hover:shadow-md transition-shadow">
                        <div class="flex items-start gap-3">
                            <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-purple-500 to-pink-600 flex items-center justify-center flex-shrink-0">
                                <EnvelopeIcon class="w-5 h-5 text-white" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                                    E-mail da Loja
                                </label>
                                <p class="text-gray-900 dark:text-gray-100 font-semibold truncate">
                                    {{ storeInfo.email || '-' }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Platform -->
                    <div class="p-5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gradient-to-br from-white to-gray-50 dark:from-gray-800/50 dark:to-gray-800/30 hover:shadow-md transition-shadow">
                        <div class="flex items-start gap-3">
                            <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-amber-500 to-orange-600 flex items-center justify-center flex-shrink-0">
                                <ServerIcon class="w-5 h-5 text-white" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                                    Plataforma
                                </label>
                                <p class="text-gray-900 dark:text-gray-100 font-semibold capitalize">
                                    {{ storeInfo.platform || '-' }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Last Sync -->
                    <div class="p-5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gradient-to-br from-white to-gray-50 dark:from-gray-800/50 dark:to-gray-800/30 hover:shadow-md transition-shadow md:col-span-2">
                        <div class="flex items-start gap-3">
                            <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-cyan-500 to-blue-600 flex items-center justify-center flex-shrink-0">
                                <CalendarIcon class="w-5 h-5 text-white" />
                            </div>
                            <div class="flex-1">
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                                    Última Sincronização
                                </label>
                                <p class="text-gray-900 dark:text-gray-100 font-semibold">
                                    {{ storeInfo.last_sync_at ? new Date(storeInfo.last_sync_at).toLocaleString('pt-BR') : 'Nunca' }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Button -->
                <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                    <BaseButton @click="$router.push({ name: 'integrations' })">
                        Gerenciar Integrações
                    </BaseButton>
                </div>
            </BaseCard>

            <!-- Niche Section -->
            <BaseCard padding="lg">
                <div class="mb-6">
                    <div class="flex items-center gap-2 mb-2">
                        <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-purple-500 to-pink-600 flex items-center justify-center">
                            <Cog6ToothIcon class="w-4 h-4 text-white" />
                        </div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            Nicho de Mercado
                        </h2>
                    </div>
                    <p class="text-gray-500 dark:text-gray-400 text-sm">
                        Selecione o nicho da sua loja para obter análises e benchmarks personalizados
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Niche Select -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                            Nicho Principal
                        </label>
                        <select
                            v-model="form.niche"
                            :disabled="!canEdit || isLoadingConfig"
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
                            :disabled="!canEdit || !form.niche || isLoadingConfig"
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

            <!-- Website URL Section -->
            <BaseCard padding="lg">
                <div class="mb-6">
                    <div class="flex items-center gap-2 mb-2">
                        <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center">
                            <GlobeAltIcon class="w-4 h-4 text-white" />
                        </div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            Site da Loja
                        </h2>
                    </div>
                    <p class="text-gray-500 dark:text-gray-400 text-sm">
                        Informe o endereço do site real/customizado da sua loja
                    </p>
                </div>

                <div class="max-w-2xl">
                    <BaseInput
                        v-model="form.website_url"
                        type="url"
                        label="URL do Site"
                        placeholder="https://www.minhaloja.com.br"
                        hint="Este é o site onde seus clientes fazem compras (diferente do domínio Nuvemshop)"
                        :error="errors.website_url"
                        :disabled="!canEdit || isLoadingConfig"
                    />
                </div>
            </BaseCard>

            <!-- Goals Section -->
            <BaseCard padding="lg">
                <div class="mb-6">
                    <div class="flex items-center gap-2 mb-2">
                        <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-green-500 to-emerald-600 flex items-center justify-center">
                            <CurrencyDollarIcon class="w-4 h-4 text-white" />
                        </div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            Metas e Faturamento
                        </h2>
                    </div>
                    <p class="text-gray-500 dark:text-gray-400 text-sm">
                        Defina suas metas financeiras para acompanhamento na análise
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <BaseInput
                        v-model="form.monthly_goal"
                        type="number"
                        label="Meta Mensal (R$)"
                        placeholder="Ex: 50000"
                        :error="errors.monthly_goal"
                        :disabled="!canEdit || isLoadingConfig"
                        step="0.01"
                        min="0"
                    />

                    <BaseInput
                        v-model="form.annual_goal"
                        type="number"
                        label="Meta Anual (R$)"
                        placeholder="Ex: 600000"
                        :error="errors.annual_goal"
                        :disabled="!canEdit || isLoadingConfig"
                        step="0.01"
                        min="0"
                    />

                    <BaseInput
                        v-model="form.target_ticket"
                        type="number"
                        label="Ticket Médio Alvo (R$)"
                        placeholder="Ex: 150"
                        :error="errors.target_ticket"
                        :disabled="!canEdit || isLoadingConfig"
                        step="0.01"
                        min="0"
                    />

                    <BaseInput
                        v-model="form.monthly_revenue"
                        type="number"
                        label="Faturamento Mensal Atual (R$)"
                        placeholder="Ex: 35000"
                        :error="errors.monthly_revenue"
                        :disabled="!canEdit || isLoadingConfig"
                        step="0.01"
                        min="0"
                    />
                </div>
            </BaseCard>

            <!-- Traffic Section -->
            <BaseCard padding="lg">
                <div class="mb-6">
                    <div class="flex items-center gap-2 mb-2">
                        <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center">
                            <ChartBarIcon class="w-4 h-4 text-white" />
                        </div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            Tráfego
                        </h2>
                    </div>
                    <p class="text-gray-500 dark:text-gray-400 text-sm">
                        Informe a quantidade de visitas mensais da sua loja
                    </p>
                </div>

                <div class="max-w-md">
                    <BaseInput
                        v-model="form.monthly_visits"
                        type="number"
                        label="Visitas Mensais"
                        placeholder="Ex: 15000"
                        :error="errors.monthly_visits"
                        :disabled="!canEdit || isLoadingConfig"
                        min="0"
                    />
                </div>
            </BaseCard>

            <!-- Competitors Section -->
            <BaseCard padding="lg">
                <div class="mb-6">
                    <div class="flex items-center gap-2 mb-2">
                        <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-amber-500 to-orange-600 flex items-center justify-center">
                            <GlobeAltIcon class="w-4 h-4 text-white" />
                        </div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            Concorrentes
                        </h2>
                    </div>
                    <p class="text-gray-500 dark:text-gray-400 text-sm">
                        Liste os principais concorrentes da sua loja para análise competitiva (máximo de 10)
                    </p>
                </div>

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
                                    :disabled="!canEdit || isLoadingConfig"
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
                                    :disabled="!canEdit || isLoadingConfig"
                                    class="w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all disabled:opacity-60"
                                />
                            </div>
                        </div>
                        <button
                            v-if="canEdit && !isLoadingConfig"
                            @click="removeCompetitor(index)"
                            class="mt-6 p-2 rounded-lg text-danger-500 hover:bg-danger-50 dark:hover:bg-danger-900/20 transition-colors"
                            title="Remover concorrente"
                        >
                            <TrashIcon class="w-5 h-5" />
                        </button>
                    </div>

                    <button
                        v-if="canEdit && !isLoadingConfig && form.competitors.length < 10"
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
            <div class="flex justify-end">
                <BaseButton
                    v-if="canEdit"
                    @click="saveConfig"
                    :loading="isSaving"
                    :disabled="isLoadingConfig"
                >
                    Salvar Configurações
                </BaseButton>
            </div>

            <p v-if="!canEdit" class="text-center text-sm text-gray-500 dark:text-gray-400 italic">
                Você não possui permissão para editar as configurações da loja.
            </p>
            </div>
        </div>
    </div>
</template>
