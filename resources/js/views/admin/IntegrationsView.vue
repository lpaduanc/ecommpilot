<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useNotificationStore } from '../../stores/notificationStore';
import api from '../../services/api';
import BaseCard from '../../components/common/BaseCard.vue';
import BaseButton from '../../components/common/BaseButton.vue';
import LoadingSpinner from '../../components/common/LoadingSpinner.vue';
import {
    CubeTransparentIcon,
    GlobeAltIcon,
    ChartBarIcon,
    UserGroupIcon,
    CheckCircleIcon,
    EyeIcon,
    EyeSlashIcon,
    ArrowPathIcon,
    ShieldCheckIcon,
} from '@heroicons/vue/24/outline';

const notificationStore = useNotificationStore();

// Tab Management
const activeTab = ref('external-data');
const tabs = [
    { id: 'external-data', name: 'Trends e Web Scraping', icon: GlobeAltIcon },
];

const isLoading = ref(true);
const isSaving = ref(false);
const isTesting = ref(false);
const isTestingDecodo = ref(false);
const showApiKey = ref(false);
const showDecodoPassword = ref(false);

// Form data
const formData = reactive({
    enabled: false,
    serpapi_key: '',
    serpapi_key_configured: false,
    trends: {
        enabled: true,
    },
    market: {
        enabled: true,
    },
    competitors: {
        enabled: true,
        max_per_store: 5,
        scrape_timeout: 15,
    },
    decodo: {
        enabled: false,
        username: '',
        username_configured: false,
        password: '',
        password_configured: false,
        headless: 'html',
        js_rendering: false,
        timeout: 30,
    },
});

async function loadSettings() {
    isLoading.value = true;
    try {
        const response = await api.get('/admin/integrations/external-data');
        const data = response.data.data;

        formData.enabled = data.enabled;
        formData.serpapi_key = ''; // Don't show masked key in input
        formData.serpapi_key_configured = data.serpapi_key_configured;
        formData.trends.enabled = data.trends.enabled;
        formData.market.enabled = data.market.enabled;
        formData.competitors.enabled = data.competitors.enabled;
        formData.competitors.max_per_store = data.competitors.max_per_store;
        formData.competitors.scrape_timeout = data.competitors.scrape_timeout;

        // Decodo settings
        formData.decodo.enabled = data.decodo?.enabled ?? false;
        formData.decodo.username = ''; // Don't show masked username in input
        formData.decodo.username_configured = data.decodo?.username_configured ?? false;
        formData.decodo.password = ''; // Don't show password in input
        formData.decodo.password_configured = data.decodo?.password_configured ?? false;
        formData.decodo.headless = data.decodo?.headless ?? 'html';
        formData.decodo.js_rendering = data.decodo?.js_rendering ?? false;
        formData.decodo.timeout = data.decodo?.timeout ?? 30;
    } catch (error) {
        console.error('Error loading settings:', error);
        notificationStore.error('Erro ao carregar configurações');
    } finally {
        isLoading.value = false;
    }
}

async function saveSettings() {
    isSaving.value = true;
    try {
        await api.put('/admin/integrations/external-data', formData);
        notificationStore.success('Configurações salvas com sucesso');

        // Reload to get updated state
        await loadSettings();
    } catch (error) {
        console.error('Error saving settings:', error);
        if (error.response?.data?.message) {
            notificationStore.error(error.response.data.message);
        } else {
            notificationStore.error('Erro ao salvar configurações');
        }
    } finally {
        isSaving.value = false;
    }
}

async function testConnection() {
    isTesting.value = true;
    try {
        const response = await api.post('/admin/integrations/external-data/test', {
            serpapi_key: formData.serpapi_key || null,
        });

        if (response.data.success) {
            notificationStore.success(response.data.message);
        } else {
            notificationStore.error(response.data.message);
        }
    } catch (error) {
        console.error('Error testing connection:', error);
        if (error.response?.data?.message) {
            notificationStore.error(error.response.data.message);
        } else {
            notificationStore.error('Erro ao testar conexão');
        }
    } finally {
        isTesting.value = false;
    }
}

async function testDecodoConnection() {
    isTestingDecodo.value = true;
    try {
        const response = await api.post('/admin/integrations/external-data/test-decodo', {
            username: formData.decodo.username || null,
            password: formData.decodo.password || null,
        });

        if (response.data.success) {
            const ip = response.data.ip ? ` (IP: ${response.data.ip})` : '';
            const country = response.data.country ? ` - ${response.data.country}` : '';
            notificationStore.success(response.data.message + ip + country);
        } else {
            notificationStore.error(response.data.message);
        }
    } catch (error) {
        console.error('Error testing Decodo connection:', error);
        if (error.response?.data?.message) {
            notificationStore.error(error.response.data.message);
        } else {
            notificationStore.error('Erro ao testar conexão Decodo');
        }
    } finally {
        isTestingDecodo.value = false;
    }
}

onMounted(() => {
    loadSettings();
});
</script>

<template>
    <div class="space-y-6">
        <!-- Page Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-display font-bold text-gray-900 dark:text-gray-100 flex items-center gap-3">
                    <CubeTransparentIcon class="w-8 h-8 text-primary-500" />
                    Integrações
                </h1>
                <p class="text-gray-500 dark:text-gray-400 mt-1">
                    Configure integrações com serviços externos para enriquecer as análises
                </p>
            </div>
        </div>

        <!-- Loading -->
        <div v-if="isLoading" class="flex items-center justify-center py-20">
            <LoadingSpinner size="lg" class="text-primary-500" />
        </div>

        <!-- Content -->
        <div v-else class="space-y-6">
            <!-- Tabs Navigation -->
            <div class="border-b border-gray-200 dark:border-gray-700">
                <nav class="-mb-px flex space-x-8">
                    <button
                        v-for="tab in tabs"
                        :key="tab.id"
                        @click="activeTab = tab.id"
                        :class="[
                            activeTab === tab.id
                                ? 'border-primary-500 text-primary-600 dark:text-primary-400'
                                : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600',
                            'group inline-flex items-center gap-2 py-4 px-1 border-b-2 font-medium text-sm transition-colors'
                        ]"
                    >
                        <component
                            :is="tab.icon"
                            :class="[
                                activeTab === tab.id ? 'text-primary-500' : 'text-gray-400 group-hover:text-gray-500 dark:group-hover:text-gray-300',
                                'w-5 h-5'
                            ]"
                        />
                        {{ tab.name }}
                    </button>
                </nav>
            </div>

            <!-- External Data Tab -->
            <div v-if="activeTab === 'external-data'" class="space-y-6">
                <!-- Main Toggle Card -->
                <BaseCard>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <div class="p-3 bg-primary-100 dark:bg-primary-900/30 rounded-xl">
                                <CubeTransparentIcon class="h-6 w-6 text-primary-600 dark:text-primary-400" />
                            </div>
                            <div>
                                <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                                    Dados Externos de Mercado
                                </h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                                    Habilita a coleta de dados externos durante as análises
                                </p>
                            </div>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input
                                type="checkbox"
                                v-model="formData.enabled"
                                class="sr-only peer"
                            />
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary-300 dark:peer-focus:ring-primary-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-primary-600"></div>
                        </label>
                    </div>
                </BaseCard>

                <!-- API Configuration Card -->
                <BaseCard>
                    <template #header>
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                            Configuração da API
                        </h3>
                    </template>

                    <div class="space-y-5">
                        <!-- SerpAPI Key -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                SerpAPI Key
                            </label>
                            <div class="flex items-center gap-3">
                                <div class="relative flex-1">
                                    <input
                                        :type="showApiKey ? 'text' : 'password'"
                                        v-model="formData.serpapi_key"
                                        :placeholder="formData.serpapi_key_configured ? '••••••••••••••••' : 'Cole sua chave da API aqui'"
                                        class="w-full px-4 py-2.5 pr-10 rounded-lg border bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 border-gray-200 dark:border-gray-600 focus:outline-none focus:ring-2 focus:border-primary-500 dark:focus:border-primary-400 focus:ring-primary-500/20 text-sm"
                                    />
                                    <button
                                        type="button"
                                        @click="showApiKey = !showApiKey"
                                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-500"
                                    >
                                        <EyeIcon v-if="!showApiKey" class="h-5 w-5" />
                                        <EyeSlashIcon v-else class="h-5 w-5" />
                                    </button>
                                </div>
                                <BaseButton
                                    @click="testConnection"
                                    :disabled="isTesting"
                                    variant="secondary"
                                    size="sm"
                                >
                                    <ArrowPathIcon v-if="isTesting" class="h-4 w-4 animate-spin" />
                                    <span v-else>Testar</span>
                                </BaseButton>
                            </div>
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400 flex items-center gap-2">
                                <span>Obtenha sua chave em <a href="https://serpapi.com" target="_blank" class="text-primary-600 hover:underline">serpapi.com</a></span>
                                <span v-if="formData.serpapi_key_configured" class="inline-flex items-center gap-1 text-green-600 dark:text-green-400">
                                    <CheckCircleIcon class="h-4 w-4" />
                                    Configurada
                                </span>
                            </p>
                        </div>
                    </div>
                </BaseCard>

                <!-- Services Card -->
                <BaseCard>
                    <template #header>
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                            Serviços Disponíveis
                        </h3>
                    </template>

                    <div class="space-y-4">
                        <!-- Google Trends -->
                        <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-gray-100 dark:border-gray-700/50">
                            <div class="flex items-center gap-3">
                                <div class="p-2.5 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                                    <ChartBarIcon class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                                </div>
                                <div>
                                    <h4 class="text-sm font-medium text-gray-900 dark:text-white">Google Trends</h4>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                        Tendências de busca para categorias e produtos
                                    </p>
                                </div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input
                                    type="checkbox"
                                    v-model="formData.trends.enabled"
                                    :disabled="!formData.enabled"
                                    class="sr-only peer"
                                />
                                <div class="w-9 h-5 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-600 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600 peer-disabled:opacity-50 peer-disabled:cursor-not-allowed"></div>
                            </label>
                        </div>

                        <!-- Google Shopping / Market Data -->
                        <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-gray-100 dark:border-gray-700/50">
                            <div class="flex items-center gap-3">
                                <div class="p-2.5 bg-green-100 dark:bg-green-900/30 rounded-lg">
                                    <GlobeAltIcon class="h-5 w-5 text-green-600 dark:text-green-400" />
                                </div>
                                <div>
                                    <h4 class="text-sm font-medium text-gray-900 dark:text-white">Preços de Mercado</h4>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                        Preços de referência no Google Shopping
                                    </p>
                                </div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input
                                    type="checkbox"
                                    v-model="formData.market.enabled"
                                    :disabled="!formData.enabled"
                                    class="sr-only peer"
                                />
                                <div class="w-9 h-5 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-green-300 dark:peer-focus:ring-green-800 rounded-full peer dark:bg-gray-600 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all dark:border-gray-600 peer-checked:bg-green-600 peer-disabled:opacity-50 peer-disabled:cursor-not-allowed"></div>
                            </label>
                        </div>

                        <!-- Competitor Analysis -->
                        <div class="p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-gray-100 dark:border-gray-700/50">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="p-2.5 bg-purple-100 dark:bg-purple-900/30 rounded-lg">
                                        <UserGroupIcon class="h-5 w-5 text-purple-600 dark:text-purple-400" />
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-medium text-gray-900 dark:text-white">Análise de Concorrentes</h4>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                            Analisa sites de concorrentes informados pelo cliente
                                        </p>
                                    </div>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input
                                        type="checkbox"
                                        v-model="formData.competitors.enabled"
                                        :disabled="!formData.enabled"
                                        class="sr-only peer"
                                    />
                                    <div class="w-9 h-5 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-purple-300 dark:peer-focus:ring-purple-800 rounded-full peer dark:bg-gray-600 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all dark:border-gray-600 peer-checked:bg-purple-600 peer-disabled:opacity-50 peer-disabled:cursor-not-allowed"></div>
                                </label>
                            </div>

                            <!-- Competitor Settings (shown when enabled) -->
                            <div
                                v-if="formData.competitors.enabled && formData.enabled"
                                class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4 pt-4 border-t border-gray-200 dark:border-gray-700"
                            >
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5">
                                        Máx. concorrentes por loja
                                    </label>
                                    <input
                                        type="number"
                                        v-model.number="formData.competitors.max_per_store"
                                        min="1"
                                        max="10"
                                        class="w-full px-4 py-2.5 rounded-lg border bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 border-gray-200 dark:border-gray-600 focus:outline-none focus:ring-2 focus:border-primary-500 dark:focus:border-primary-400 focus:ring-primary-500/20 text-sm"
                                    />
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5">
                                        Timeout (segundos)
                                    </label>
                                    <input
                                        type="number"
                                        v-model.number="formData.competitors.scrape_timeout"
                                        min="5"
                                        max="60"
                                        class="w-full px-4 py-2.5 rounded-lg border bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 border-gray-200 dark:border-gray-600 focus:outline-none focus:ring-2 focus:border-primary-500 dark:focus:border-primary-400 focus:ring-primary-500/20 text-sm"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </BaseCard>

                <!-- Decodo Proxy Card -->
                <BaseCard>
                    <template #header>
                        <div class="flex items-center justify-between">
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                                Decodo Proxy (Web Scraping Avançado)
                            </h3>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input
                                    type="checkbox"
                                    v-model="formData.decodo.enabled"
                                    :disabled="!formData.enabled || !formData.competitors.enabled"
                                    class="sr-only peer"
                                />
                                <div class="w-9 h-5 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary-300 dark:peer-focus:ring-primary-800 rounded-full peer dark:bg-gray-600 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all dark:border-gray-600 peer-checked:bg-primary-600 peer-disabled:opacity-50 peer-disabled:cursor-not-allowed"></div>
                            </label>
                        </div>
                    </template>

                    <div class="space-y-5">
                        <!-- Info Banner -->
                        <div class="flex items-start gap-3 p-4 bg-amber-50 dark:bg-amber-900/20 rounded-xl border border-amber-200 dark:border-amber-800/50">
                            <ShieldCheckIcon class="h-5 w-5 text-amber-600 dark:text-amber-400 flex-shrink-0 mt-0.5" />
                            <div class="text-sm">
                                <p class="font-medium text-amber-800 dark:text-amber-300">Proxy Residencial Rotativo</p>
                                <p class="text-amber-700 dark:text-amber-400 mt-1">
                                    O Decodo melhora a taxa de sucesso do scraping de concorrentes usando IPs residenciais rotativos.
                                    <a href="https://dashboard.decodo.com/" target="_blank" class="underline hover:text-amber-900 dark:hover:text-amber-200">
                                        Obtenha suas credenciais aqui
                                    </a>
                                </p>
                            </div>
                        </div>

                        <!-- Credentials -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Username -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Username
                                </label>
                                <div class="relative">
                                    <input
                                        type="text"
                                        v-model="formData.decodo.username"
                                        :placeholder="formData.decodo.username_configured ? '••••••••' : 'Seu usuário Decodo'"
                                        class="w-full px-4 py-2.5 rounded-lg border bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 border-gray-200 dark:border-gray-600 focus:outline-none focus:ring-2 focus:border-primary-500 dark:focus:border-primary-400 focus:ring-primary-500/20 text-sm"
                                    />
                                </div>
                                <p v-if="formData.decodo.username_configured" class="mt-1.5 text-xs text-green-600 dark:text-green-400 flex items-center gap-1">
                                    <CheckCircleIcon class="h-3.5 w-3.5" />
                                    Configurado
                                </p>
                            </div>

                            <!-- Password -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Password
                                </label>
                                <div class="relative">
                                    <input
                                        :type="showDecodoPassword ? 'text' : 'password'"
                                        v-model="formData.decodo.password"
                                        :placeholder="formData.decodo.password_configured ? '••••••••' : 'Sua senha Decodo'"
                                        class="w-full px-4 py-2.5 pr-10 rounded-lg border bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 border-gray-200 dark:border-gray-600 focus:outline-none focus:ring-2 focus:border-primary-500 dark:focus:border-primary-400 focus:ring-primary-500/20 text-sm"
                                    />
                                    <button
                                        type="button"
                                        @click="showDecodoPassword = !showDecodoPassword"
                                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-500"
                                    >
                                        <EyeIcon v-if="!showDecodoPassword" class="h-5 w-5" />
                                        <EyeSlashIcon v-else class="h-5 w-5" />
                                    </button>
                                </div>
                                <p v-if="formData.decodo.password_configured" class="mt-1.5 text-xs text-green-600 dark:text-green-400 flex items-center gap-1">
                                    <CheckCircleIcon class="h-3.5 w-3.5" />
                                    Configurado
                                </p>
                            </div>
                        </div>

                        <!-- Test Connection Button -->
                        <div class="flex items-center gap-3">
                            <BaseButton
                                @click="testDecodoConnection"
                                :disabled="isTestingDecodo || (!formData.decodo.username && !formData.decodo.username_configured)"
                                variant="secondary"
                                size="sm"
                            >
                                <ArrowPathIcon v-if="isTestingDecodo" class="h-4 w-4 mr-2 animate-spin" />
                                {{ isTestingDecodo ? 'Testando...' : 'Testar Conexão' }}
                            </BaseButton>
                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                Verifica se as credenciais estão funcionando
                            </span>
                        </div>

                        <!-- Advanced Settings (collapsible) -->
                        <details class="group">
                            <summary class="text-sm font-medium text-gray-600 dark:text-gray-400 cursor-pointer hover:text-gray-800 dark:hover:text-gray-200 flex items-center gap-2">
                                <span>Configurações avançadas</span>
                                <svg class="w-4 h-4 transition-transform group-open:rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </summary>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5">
                                        Modo Headless
                                    </label>
                                    <select
                                        v-model="formData.decodo.headless"
                                        class="w-full px-4 py-2 rounded-lg border bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 border-gray-200 dark:border-gray-600 focus:outline-none focus:ring-2 focus:border-primary-500 dark:focus:border-primary-400 focus:ring-primary-500/20 text-sm cursor-pointer"
                                    >
                                        <option value="html">HTML (mais rápido)</option>
                                        <option value="true">Full (renderiza JS)</option>
                                    </select>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        HTML é mais rápido, Full renderiza JavaScript
                                    </p>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5">
                                        JS Rendering
                                    </label>
                                    <label class="relative inline-flex items-center cursor-pointer mt-2">
                                        <input
                                            type="checkbox"
                                            v-model="formData.decodo.js_rendering"
                                            class="sr-only peer"
                                        />
                                        <div class="w-9 h-5 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary-300 dark:peer-focus:ring-primary-800 rounded-full peer dark:bg-gray-600 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all dark:border-gray-600 peer-checked:bg-primary-600"></div>
                                        <span class="ml-2 text-xs text-gray-500 dark:text-gray-400">Habilitar</span>
                                    </label>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        Necessário para sites com conteúdo dinâmico
                                    </p>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5">
                                        Timeout (segundos)
                                    </label>
                                    <input
                                        type="number"
                                        v-model.number="formData.decodo.timeout"
                                        min="5"
                                        max="120"
                                        class="w-full px-4 py-2 rounded-lg border bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 border-gray-200 dark:border-gray-600 focus:outline-none focus:ring-2 focus:border-primary-500 dark:focus:border-primary-400 focus:ring-primary-500/20 text-sm"
                                    />
                                </div>
                            </div>
                        </details>
                    </div>
                </BaseCard>

                <!-- Save Button -->
                <div class="flex justify-end pt-2">
                    <BaseButton
                        @click="saveSettings"
                        :disabled="isSaving"
                        variant="primary"
                    >
                        <ArrowPathIcon v-if="isSaving" class="h-4 w-4 mr-2 animate-spin" />
                        {{ isSaving ? 'Salvando...' : 'Salvar Configurações' }}
                    </BaseButton>
                </div>
            </div>
        </div>
    </div>
</template>
