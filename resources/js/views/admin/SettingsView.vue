<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useNotificationStore } from '../../stores/notificationStore';
import api from '../../services/api';
import BaseCard from '../../components/common/BaseCard.vue';
import BaseButton from '../../components/common/BaseButton.vue';
import BaseInput from '../../components/common/BaseInput.vue';
import LoadingSpinner from '../../components/common/LoadingSpinner.vue';
import BaseModal from '../../components/common/BaseModal.vue';
import {
    Cog6ToothIcon,
    SparklesIcon,
    CheckCircleIcon,
    XCircleIcon,
    BeakerIcon,
    EyeIcon,
    EyeSlashIcon,
    MapPinIcon,
    ArrowPathIcon,
    ChartBarIcon,
    EnvelopeIcon,
    PlusIcon,
    PencilIcon,
    TrashIcon,
} from '@heroicons/vue/24/outline';

const notificationStore = useNotificationStore();

// Tab Management
const activeTab = ref('analysis');
const tabs = [
    { id: 'analysis', name: 'Análise', icon: ChartBarIcon },
    { id: 'ai', name: 'Configurações de IA', icon: SparklesIcon },
    { id: 'email', name: 'E-mail', icon: EnvelopeIcon },
    { id: 'sync', name: 'Sincronização', icon: MapPinIcon },
];

const isLoading = ref(true);
const isSaving = ref(false);
const isTesting = ref(false);
const testingProvider = ref(null);

const showOpenAIKey = ref(false);
const showGeminiKey = ref(false);
const showAnthropicKey = ref(false);

// Email settings visibility
const showSmtpPassword = ref(false);
const showMailgunKey = ref(false);
const showSesSecret = ref(false);
const showPostmarkToken = ref(false);
const showResendKey = ref(false);
const showEmailApiKey = ref(false);
const showEmailSecret = ref(false);

const settings = reactive({
    provider: 'openai',
    openai: {
        api_key: '',
        model: 'gpt-4o',
        temperature: 0.7,
        max_tokens: 4000,
        is_configured: false,
    },
    gemini: {
        api_key: '',
        model: 'gemini-1.5-pro',
        temperature: 0.7,
        max_tokens: 4000,
        is_configured: false,
    },
    anthropic: {
        api_key: '',
        model: 'claude-sonnet-4-20250514',
        temperature: 0.7,
        max_tokens: 8192,
        is_configured: false,
    },
});

// Email Settings - Múltiplas configurações
const emailConfigs = ref([]);
const availableEmailProviders = ref([]);
const isLoadingEmails = ref(false);
const isSavingEmail = ref(false);
const isTestingEmail = ref(false);
const isDeletingEmail = ref(false);

// Modal de adicionar/editar
const showEmailModal = ref(false);
const editingEmailConfig = ref(null);
const emailForm = reactive({
    id: null,
    name: '',
    identifier: '',
    provider: 'smtp',
    is_active: true,
    config: {
        // Campos gerais
        api_key: '',
        secret: '',
        api_url: '',
        // SMTP
        host: '',
        port: 587,
        username: '',
        password: '',
        encryption: 'tls',
        // Mailgun
        domain: '',
        // Amazon SES
        key: '',
        region: 'us-east-1',
        // Postmark
        token: '',
        // Comum
        from_address: '',
        from_name: '',
    },
});

const availableProviders = ref([]);
const availableModels = ref({});
const testResults = reactive({
    openai: null,
    gemini: null,
    anthropic: null,
});

// Brazil Locations Sync
const isSyncingLocations = ref(false);
const locationsStatus = reactive({
    last_sync: null,
    states_count: 0,
    cities_count: 0,
    needs_sync: true,
});

// Analysis Format Settings
const analysisFormat = reactive({
    format_version: 'v1',
    v2_options: {
        validate_field_lengths: true,
        use_markdown_tables: true,
        use_history_summary: true,
    },
});
const availableFormats = ref([]);
const isSavingFormat = ref(false);

const selectedProviderConfig = computed(() => {
    return settings.provider === 'openai' ? settings.openai : settings.gemini;
});

async function fetchSettings() {
    isLoading.value = true;
    try {
        const response = await api.get('/admin/settings/ai');
        Object.assign(settings, response.data.settings);
        availableProviders.value = response.data.available_providers;
        availableModels.value = response.data.available_models;
    } catch (error) {
        notificationStore.error('Erro ao carregar configurações');
    } finally {
        isLoading.value = false;
    }
}

async function saveSettings() {
    isSaving.value = true;
    try {
        await api.put('/admin/settings/ai', settings);
        notificationStore.success('Configurações salvas com sucesso!');
        // Refresh settings to get updated masked values
        await fetchSettings();
    } catch (error) {
        notificationStore.error('Erro ao salvar configurações');
    } finally {
        isSaving.value = false;
    }
}

async function testProvider(provider) {
    isTesting.value = true;
    testingProvider.value = provider;
    testResults[provider] = null;
    
    try {
        const response = await api.post('/admin/settings/ai/test', { provider });
        testResults[provider] = response.data;
        
        if (response.data.success) {
            notificationStore.success(`${provider.toUpperCase()} conectado com sucesso!`);
        } else {
            notificationStore.error(response.data.message);
        }
    } catch (error) {
        testResults[provider] = {
            success: false,
            message: error.response?.data?.message || 'Erro ao testar conexão',
        };
        notificationStore.error('Erro ao testar conexão');
    } finally {
        isTesting.value = false;
        testingProvider.value = null;
    }
}

function getProviderIcon(providerId) {
    const icons = {
        openai: '🤖',
        gemini: '✨',
        anthropic: '🧠',
    };
    return icons[providerId] || '🤖';
}

async function fetchLocationsStatus() {
    try {
        const response = await api.get('/admin/locations/sync-status');
        Object.assign(locationsStatus, response.data);
    } catch (error) {
        console.error('Erro ao buscar status de localidades:', error);
    }
}

async function syncLocations() {
    isSyncingLocations.value = true;
    try {
        await api.post('/admin/locations/sync');
        notificationStore.success('Sincronização de localidades iniciada! Isso pode levar alguns segundos.');
        // Poll for completion
        setTimeout(async () => {
            await fetchLocationsStatus();
            if (!locationsStatus.needs_sync) {
                notificationStore.success('Sincronização concluída com sucesso!');
            }
            isSyncingLocations.value = false;
        }, 15000);
    } catch (error) {
        notificationStore.error('Erro ao iniciar sincronização de localidades');
        isSyncingLocations.value = false;
    }
}

function formatSyncDate(dateString) {
    if (!dateString) return 'Nunca sincronizado';
    const date = new Date(dateString);
    return date.toLocaleString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

// Analysis Format Functions
async function fetchAnalysisFormat() {
    try {
        const response = await api.get('/admin/settings/analysis-format');
        Object.assign(analysisFormat, response.data.settings);
        availableFormats.value = response.data.available_formats;
    } catch (error) {
        console.error('Erro ao buscar formato de análise:', error);
    }
}

async function saveAnalysisFormat() {
    isSavingFormat.value = true;
    try {
        await api.put('/admin/settings/analysis-format', analysisFormat);
        notificationStore.success('Formato de análise atualizado!');
    } catch (error) {
        notificationStore.error('Erro ao salvar formato de análise');
    } finally {
        isSavingFormat.value = false;
    }
}

// Email Functions
async function fetchEmailConfigs() {
    isLoadingEmails.value = true;
    try {
        const response = await api.get('/admin/settings/email');
        emailConfigs.value = response.data.configs || [];
        availableEmailProviders.value = response.data.available_providers || [];
    } catch (error) {
        console.error('Erro ao buscar configurações de e-mail:', error);
        notificationStore.error('Erro ao carregar configurações de e-mail');
    } finally {
        isLoadingEmails.value = false;
    }
}

function openEmailModal(config = null) {
    if (config) {
        // Editar configuração existente
        editingEmailConfig.value = config;
        emailForm.id = config.id;
        emailForm.name = config.name;
        emailForm.identifier = config.identifier;
        emailForm.provider = config.provider;
        emailForm.is_active = config.is_active;
        Object.assign(emailForm.config, config.config);
    } else {
        // Nova configuração
        editingEmailConfig.value = null;
        emailForm.id = null;
        emailForm.name = '';
        emailForm.identifier = '';
        emailForm.provider = 'smtp';
        emailForm.is_active = true;
        // Reset config
        Object.keys(emailForm.config).forEach(key => {
            if (typeof emailForm.config[key] === 'number') {
                emailForm.config[key] = key === 'port' ? 587 : 0;
            } else {
                emailForm.config[key] = key === 'encryption' ? 'tls' : key === 'region' ? 'us-east-1' : '';
            }
        });
    }
    showEmailModal.value = true;
}

function closeEmailModal() {
    showEmailModal.value = false;
    editingEmailConfig.value = null;
}

async function saveEmailConfig() {
    isSavingEmail.value = true;
    try {
        const payload = {
            name: emailForm.name,
            identifier: emailForm.identifier,
            provider: emailForm.provider,
            is_active: emailForm.is_active,
            config: emailForm.config,
        };

        if (emailForm.id) {
            // Atualizar
            await api.put(`/admin/settings/email/${emailForm.id}`, payload);
            notificationStore.success('Configuração atualizada com sucesso!');
        } else {
            // Criar
            await api.post('/admin/settings/email', payload);
            notificationStore.success('Configuração criada com sucesso!');
        }

        await fetchEmailConfigs();
        closeEmailModal();
    } catch (error) {
        const message = error.response?.data?.message || 'Erro ao salvar configuração de e-mail';
        notificationStore.error(message);
    } finally {
        isSavingEmail.value = false;
    }
}

async function deleteEmailConfig(id) {
    if (!confirm('Tem certeza que deseja excluir esta configuração de e-mail?')) {
        return;
    }

    isDeletingEmail.value = true;
    try {
        await api.delete(`/admin/settings/email/${id}`);
        notificationStore.success('Configuração excluída com sucesso!');
        await fetchEmailConfigs();
    } catch (error) {
        const message = error.response?.data?.message || 'Erro ao excluir configuração';
        notificationStore.error(message);
    } finally {
        isDeletingEmail.value = false;
    }
}

async function testEmailConfig(id) {
    isTestingEmail.value = true;
    try {
        const response = await api.post(`/admin/settings/email/${id}/test`);
        if (response.data.success) {
            notificationStore.success('E-mail de teste enviado com sucesso!');
        } else {
            notificationStore.error(response.data.message || 'Falha ao enviar e-mail de teste');
        }
    } catch (error) {
        const message = error.response?.data?.message || 'Erro ao testar configuração de e-mail';
        notificationStore.error(message);
    } finally {
        isTestingEmail.value = false;
    }
}

function getEmailProviderIcon(providerId) {
    const icons = {
        smtp: '📧',
        mailgun: '📮',
        ses: '📨',
        postmark: '✉️',
        resend: '🚀',
    };
    return icons[providerId] || '📧';
}

function getEmailProviderName(providerId) {
    const provider = availableEmailProviders.value.find(p => p.id === providerId);
    return provider ? provider.name : providerId.toUpperCase();
}

onMounted(() => {
    fetchSettings();
    fetchLocationsStatus();
    fetchAnalysisFormat();
    fetchEmailConfigs();
});
</script>

<template>
    <div class="space-y-6">
        <!-- Page Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-display font-bold text-gray-900 dark:text-gray-100 flex items-center gap-3">
                    <Cog6ToothIcon class="w-8 h-8 text-primary-500" />
                    Configurações do Sistema
                </h1>
                <p class="text-gray-500 dark:text-gray-400 mt-1">Gerencie as configurações de IA e integrações</p>
            </div>
        </div>

        <!-- Loading -->
        <div v-if="isLoading" class="flex items-center justify-center py-20">
            <LoadingSpinner size="lg" class="text-primary-500" />
        </div>

        <!-- Tabs Navigation -->
        <div v-else class="space-y-6">
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

            <!-- Analysis Tab -->
            <div v-show="activeTab === 'analysis'">
                <BaseCard>
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center gap-3">
                            <BeakerIcon class="w-6 h-6 text-primary-500" />
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Formato de Análise</h2>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Escolha o formato de análise de IA para teste</p>
                            </div>
                        </div>
                        <span
                            :class="[
                                'px-3 py-1 text-xs font-medium rounded-full',
                                analysisFormat.format_version === 'v2'
                                    ? 'bg-success-100 text-success-700 dark:bg-success-900/30 dark:text-success-400'
                                    : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300'
                            ]"
                        >
                            {{ analysisFormat.format_version === 'v2' ? 'Otimizado (v2)' : 'Detalhado (v1)' }}
                        </span>
                    </div>

                    <div class="space-y-6">
                        <!-- Format Selection -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <label
                                v-for="format in availableFormats"
                                :key="format.id"
                                :class="[
                                    'flex items-start gap-4 p-4 rounded-xl border-2 cursor-pointer transition-all',
                                    analysisFormat.format_version === format.id
                                        ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20'
                                        : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'
                                ]"
                            >
                                <input
                                    type="radio"
                                    :value="format.id"
                                    v-model="analysisFormat.format_version"
                                    class="mt-1"
                                />
                                <div class="flex-1">
                                    <span class="font-semibold text-gray-900 dark:text-gray-100">{{ format.name }}</span>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ format.description }}</p>
                                </div>
                            </label>
                        </div>

                        <!-- V2 Options (visible only when v2 selected) -->
                        <div
                            v-if="analysisFormat.format_version === 'v2'"
                            class="p-4 bg-gray-50 dark:bg-gray-800 rounded-xl space-y-3"
                        >
                            <h3 class="font-medium text-gray-900 dark:text-gray-100 mb-3">Opções do Formato v2</h3>

                            <label class="flex items-center gap-3 cursor-pointer">
                                <input
                                    type="checkbox"
                                    v-model="analysisFormat.v2_options.use_markdown_tables"
                                    class="rounded border-gray-300 text-primary-500 focus:ring-primary-500"
                                />
                                <div>
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Usar tabelas Markdown</span>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Economia de tokens ao formatar produtos</p>
                                </div>
                            </label>

                            <label class="flex items-center gap-3 cursor-pointer">
                                <input
                                    type="checkbox"
                                    v-model="analysisFormat.v2_options.use_history_summary"
                                    class="rounded border-gray-300 text-primary-500 focus:ring-primary-500"
                                />
                                <div>
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Usar resumo de histórico</span>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Reduz histórico de 8k para ~2k tokens</p>
                                </div>
                            </label>

                            <label class="flex items-center gap-3 cursor-pointer">
                                <input
                                    type="checkbox"
                                    v-model="analysisFormat.v2_options.validate_field_lengths"
                                    class="rounded border-gray-300 text-primary-500 focus:ring-primary-500"
                                />
                                <div>
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Validar limites de caracteres</span>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Loga avisos quando campos excedem limites (não bloqueia)</p>
                                </div>
                            </label>
                        </div>

                        <!-- Info Box -->
                        <div class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-xl">
                            <p class="text-sm text-blue-700 dark:text-blue-300">
                                <strong>Diferenças:</strong> O formato v2 inclui clarificação de status vs payment_status
                                para evitar falsos alertas de "pedidos pendentes", além de usar tabelas Markdown
                                e resumo de histórico para economizar ~30% de tokens.
                            </p>
                        </div>

                        <!-- Save Button -->
                        <div class="flex justify-end">
                            <BaseButton
                                @click="saveAnalysisFormat"
                                :loading="isSavingFormat"
                            >
                                Salvar Formato
                            </BaseButton>
                        </div>
                    </div>
                </BaseCard>
            </div>

            <!-- AI Settings Tab -->
            <div v-show="activeTab === 'ai'" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Provider Selection -->
            <div class="lg:col-span-1">
                <BaseCard>
                    <div class="flex items-center gap-3 mb-6">
                        <SparklesIcon class="w-6 h-6 text-primary-500" />
                        <h2 class="text-lg font-semibold text-gray-900">Provedor de IA</h2>
                    </div>

                    <div class="space-y-3">
                        <label
                            v-for="provider in availableProviders"
                            :key="provider.id"
                            :class="[
                                'flex items-center gap-4 p-4 rounded-xl border-2 cursor-pointer transition-all',
                                settings.provider === provider.id
                                    ? 'border-primary-500 bg-primary-50'
                                    : 'border-gray-200 hover:border-gray-300'
                            ]"
                        >
                            <input
                                type="radio"
                                :value="provider.id"
                                v-model="settings.provider"
                                class="sr-only"
                            />
                            <div class="text-2xl">{{ getProviderIcon(provider.id) }}</div>
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold text-gray-900">{{ provider.name }}</span>
                                    <CheckCircleIcon
                                        v-if="settings[provider.id].is_configured"
                                        class="w-5 h-5 text-success-500"
                                    />
                                    <XCircleIcon
                                        v-else
                                        class="w-5 h-5 text-gray-300"
                                    />
                                </div>
                                <p class="text-sm text-gray-500">{{ provider.description }}</p>
                            </div>
                            <div
                                :class="[
                                    'w-4 h-4 rounded-full border-2',
                                    settings.provider === provider.id
                                        ? 'border-primary-500 bg-primary-500'
                                        : 'border-gray-300'
                                ]"
                            >
                                <div
                                    v-if="settings.provider === provider.id"
                                    class="w-2 h-2 bg-white rounded-full m-0.5"
                                ></div>
                            </div>
                        </label>
                    </div>

                    <div class="mt-6 p-4 bg-gray-50 dark:bg-gray-800 rounded-xl">
                        <p class="text-sm text-gray-600 dark:text-gray-300">
                            <strong>Provedor ativo:</strong> {{ { openai: 'OpenAI', gemini: 'Google Gemini', anthropic: 'Anthropic Claude' }[settings.provider] }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            Este provedor será usado para análises e chat de IA.
                        </p>
                    </div>
                </BaseCard>
            </div>

            <!-- Provider Settings -->
            <div class="lg:col-span-2 space-y-6">
                <!-- OpenAI Settings -->
                <BaseCard>
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center gap-3">
                            <div class="text-2xl">🤖</div>
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900">OpenAI</h2>
                                <p class="text-sm text-gray-500">Configurações do GPT-4 e outros modelos</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span
                                v-if="settings.openai.is_configured"
                                class="px-3 py-1 text-xs font-medium rounded-full bg-success-100 text-success-700"
                            >
                                Configurado
                            </span>
                            <span
                                v-else
                                class="px-3 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-600"
                            >
                                Não configurado
                            </span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">API Key</label>
                            <div class="relative">
                                <input
                                    :type="showOpenAIKey ? 'text' : 'password'"
                                    v-model="settings.openai.api_key"
                                    placeholder="sk-..."
                                    class="input pr-10"
                                />
                                <button
                                    type="button"
                                    @click="showOpenAIKey = !showOpenAIKey"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                >
                                    <EyeIcon v-if="!showOpenAIKey" class="w-5 h-5" />
                                    <EyeSlashIcon v-else class="w-5 h-5" />
                                </button>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Modelo</label>
                            <select v-model="settings.openai.model" class="input">
                                <option
                                    v-for="model in availableModels.openai"
                                    :key="model.id"
                                    :value="model.id"
                                >
                                    {{ model.name }} - {{ model.description }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Temperatura ({{ settings.openai.temperature }})
                            </label>
                            <input
                                type="range"
                                v-model.number="settings.openai.temperature"
                                min="0"
                                max="2"
                                step="0.1"
                                class="w-full"
                            />
                            <div class="flex justify-between text-xs text-gray-400 mt-1">
                                <span>Preciso</span>
                                <span>Criativo</span>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Max Tokens</label>
                            <input
                                type="number"
                                v-model.number="settings.openai.max_tokens"
                                min="100"
                                max="128000"
                                class="input"
                            />
                        </div>

                        <div class="flex items-end">
                            <BaseButton
                                variant="secondary"
                                @click="testProvider('openai')"
                                :loading="testingProvider === 'openai'"
                                :disabled="!settings.openai.api_key || isTesting"
                            >
                                <BeakerIcon class="w-4 h-4" />
                                Testar Conexão
                            </BaseButton>
                        </div>
                    </div>

                    <!-- Test Result -->
                    <div
                        v-if="testResults.openai"
                        :class="[
                            'mt-4 p-4 rounded-xl',
                            testResults.openai.success ? 'bg-success-50' : 'bg-danger-50'
                        ]"
                    >
                        <div class="flex items-start gap-3">
                            <CheckCircleIcon
                                v-if="testResults.openai.success"
                                class="w-5 h-5 text-success-500 mt-0.5"
                            />
                            <XCircleIcon
                                v-else
                                class="w-5 h-5 text-danger-500 mt-0.5"
                            />
                            <div>
                                <p :class="testResults.openai.success ? 'text-success-700' : 'text-danger-700'">
                                    {{ testResults.openai.message }}
                                </p>
                                <p
                                    v-if="testResults.openai.response"
                                    class="text-sm text-gray-600 mt-1"
                                >
                                    Resposta: "{{ testResults.openai.response }}"
                                </p>
                            </div>
                        </div>
                    </div>
                </BaseCard>

                <!-- Gemini Settings -->
                <BaseCard>
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center gap-3">
                            <div class="text-2xl">✨</div>
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900">Google Gemini</h2>
                                <p class="text-sm text-gray-500">Configurações do Gemini Pro e Flash</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span
                                v-if="settings.gemini.is_configured"
                                class="px-3 py-1 text-xs font-medium rounded-full bg-success-100 text-success-700"
                            >
                                Configurado
                            </span>
                            <span
                                v-else
                                class="px-3 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-600"
                            >
                                Não configurado
                            </span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">API Key</label>
                            <div class="relative">
                                <input
                                    :type="showGeminiKey ? 'text' : 'password'"
                                    v-model="settings.gemini.api_key"
                                    placeholder="AIza..."
                                    class="input pr-10"
                                />
                                <button
                                    type="button"
                                    @click="showGeminiKey = !showGeminiKey"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                >
                                    <EyeIcon v-if="!showGeminiKey" class="w-5 h-5" />
                                    <EyeSlashIcon v-else class="w-5 h-5" />
                                </button>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Modelo</label>
                            <select v-model="settings.gemini.model" class="input">
                                <option
                                    v-for="model in availableModels.gemini"
                                    :key="model.id"
                                    :value="model.id"
                                >
                                    {{ model.name }} - {{ model.description }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Temperatura ({{ settings.gemini.temperature }})
                            </label>
                            <input
                                type="range"
                                v-model.number="settings.gemini.temperature"
                                min="0"
                                max="2"
                                step="0.1"
                                class="w-full"
                            />
                            <div class="flex justify-between text-xs text-gray-400 mt-1">
                                <span>Preciso</span>
                                <span>Criativo</span>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Max Tokens</label>
                            <input
                                type="number"
                                v-model.number="settings.gemini.max_tokens"
                                min="100"
                                max="32000"
                                class="input"
                            />
                        </div>

                        <div class="flex items-end">
                            <BaseButton
                                variant="secondary"
                                @click="testProvider('gemini')"
                                :loading="testingProvider === 'gemini'"
                                :disabled="!settings.gemini.api_key || isTesting"
                            >
                                <BeakerIcon class="w-4 h-4" />
                                Testar Conexão
                            </BaseButton>
                        </div>
                    </div>

                    <!-- Test Result -->
                    <div
                        v-if="testResults.gemini"
                        :class="[
                            'mt-4 p-4 rounded-xl',
                            testResults.gemini.success ? 'bg-success-50' : 'bg-danger-50'
                        ]"
                    >
                        <div class="flex items-start gap-3">
                            <CheckCircleIcon
                                v-if="testResults.gemini.success"
                                class="w-5 h-5 text-success-500 mt-0.5"
                            />
                            <XCircleIcon
                                v-else
                                class="w-5 h-5 text-danger-500 mt-0.5"
                            />
                            <div>
                                <p :class="testResults.gemini.success ? 'text-success-700' : 'text-danger-700'">
                                    {{ testResults.gemini.message }}
                                </p>
                                <p
                                    v-if="testResults.gemini.response"
                                    class="text-sm text-gray-600 mt-1"
                                >
                                    Resposta: "{{ testResults.gemini.response }}"
                                </p>
                            </div>
                        </div>
                    </div>
                </BaseCard>

                <!-- Anthropic Settings -->
                <BaseCard>
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center gap-3">
                            <div class="text-2xl">🧠</div>
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Anthropic Claude</h2>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Configurações do Claude Sonnet, Opus e Haiku</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span
                                v-if="settings.anthropic.is_configured"
                                class="px-3 py-1 text-xs font-medium rounded-full bg-success-100 text-success-700 dark:bg-success-900/30 dark:text-success-400"
                            >
                                Configurado
                            </span>
                            <span
                                v-else
                                class="px-3 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300"
                            >
                                Não configurado
                            </span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">API Key</label>
                            <div class="relative">
                                <input
                                    :type="showAnthropicKey ? 'text' : 'password'"
                                    v-model="settings.anthropic.api_key"
                                    placeholder="sk-ant-..."
                                    class="input pr-10"
                                />
                                <button
                                    type="button"
                                    @click="showAnthropicKey = !showAnthropicKey"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                                >
                                    <EyeIcon v-if="!showAnthropicKey" class="w-5 h-5" />
                                    <EyeSlashIcon v-else class="w-5 h-5" />
                                </button>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Modelo</label>
                            <select v-model="settings.anthropic.model" class="input">
                                <option
                                    v-for="model in availableModels.anthropic"
                                    :key="model.id"
                                    :value="model.id"
                                >
                                    {{ model.name }} - {{ model.description }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Temperatura ({{ settings.anthropic.temperature }})
                            </label>
                            <input
                                type="range"
                                v-model.number="settings.anthropic.temperature"
                                min="0"
                                max="1"
                                step="0.1"
                                class="w-full"
                            />
                            <div class="flex justify-between text-xs text-gray-400 mt-1">
                                <span>Preciso</span>
                                <span>Criativo</span>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Max Tokens</label>
                            <input
                                type="number"
                                v-model.number="settings.anthropic.max_tokens"
                                min="100"
                                max="128000"
                                class="input"
                            />
                        </div>

                        <div class="flex items-end">
                            <BaseButton
                                variant="secondary"
                                @click="testProvider('anthropic')"
                                :loading="testingProvider === 'anthropic'"
                                :disabled="!settings.anthropic.api_key || isTesting"
                            >
                                <BeakerIcon class="w-4 h-4" />
                                Testar Conexão
                            </BaseButton>
                        </div>
                    </div>

                    <!-- Test Result -->
                    <div
                        v-if="testResults.anthropic"
                        :class="[
                            'mt-4 p-4 rounded-xl',
                            testResults.anthropic.success ? 'bg-success-50 dark:bg-success-900/20' : 'bg-danger-50 dark:bg-danger-900/20'
                        ]"
                    >
                        <div class="flex items-start gap-3">
                            <CheckCircleIcon
                                v-if="testResults.anthropic.success"
                                class="w-5 h-5 text-success-500 mt-0.5"
                            />
                            <XCircleIcon
                                v-else
                                class="w-5 h-5 text-danger-500 mt-0.5"
                            />
                            <div>
                                <p :class="testResults.anthropic.success ? 'text-success-700 dark:text-success-400' : 'text-danger-700 dark:text-danger-400'">
                                    {{ testResults.anthropic.message }}
                                </p>
                                <p
                                    v-if="testResults.anthropic.response"
                                    class="text-sm text-gray-600 dark:text-gray-400 mt-1"
                                >
                                    Resposta: "{{ testResults.anthropic.response }}"
                                </p>
                            </div>
                        </div>
                    </div>
                </BaseCard>

                <!-- Save Button -->
                <div class="flex justify-end">
                    <BaseButton
                        @click="saveSettings"
                        :loading="isSaving"
                        size="lg"
                    >
                        Salvar Configurações
                    </BaseButton>
                </div>
            </div>
        </div>

            <!-- Email Tab -->
            <div v-show="activeTab === 'email'">
                <BaseCard>
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center gap-3">
                            <EnvelopeIcon class="w-6 h-6 text-primary-500" />
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Configurações de E-mail</h2>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Gerencie múltiplas configurações de e-mail para diferentes propósitos</p>
                            </div>
                        </div>
                        <BaseButton @click="openEmailModal()">
                            <PlusIcon class="w-4 h-4" />
                            Adicionar Configuração
                        </BaseButton>
                    </div>

                    <!-- Loading -->
                    <div v-if="isLoadingEmails" class="flex items-center justify-center py-12">
                        <LoadingSpinner size="lg" class="text-primary-500" />
                    </div>

                    <!-- Empty State -->
                    <div v-else-if="emailConfigs.length === 0" class="text-center py-12">
                        <EnvelopeIcon class="w-16 h-16 text-gray-300 dark:text-gray-600 mx-auto mb-4" />
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Nenhuma configuração de e-mail</h3>
                        <p class="text-gray-500 dark:text-gray-400 mb-6">Adicione sua primeira configuração de e-mail para começar a enviar mensagens.</p>
                        <BaseButton @click="openEmailModal()">
                            <PlusIcon class="w-4 h-4" />
                            Adicionar Configuração
                        </BaseButton>
                    </div>

                    <!-- Configs List -->
                    <div v-else class="space-y-4">
                        <div
                            v-for="config in emailConfigs"
                            :key="config.id"
                            class="flex items-center gap-4 p-4 bg-gray-50 dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600 transition-colors"
                        >
                            <div class="text-2xl">{{ getEmailProviderIcon(config.provider) }}</div>

                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <h3 class="font-semibold text-gray-900 dark:text-gray-100">{{ config.name }}</h3>
                                    <span class="text-xs text-gray-500 dark:text-gray-400 bg-gray-200 dark:bg-gray-700 px-2 py-0.5 rounded">
                                        {{ config.identifier }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-3 text-sm">
                                    <span class="text-gray-600 dark:text-gray-400">
                                        {{ getEmailProviderName(config.provider) }}
                                    </span>
                                    <span class="text-gray-400 dark:text-gray-600">•</span>
                                    <span class="text-gray-600 dark:text-gray-400 truncate">
                                        {{ config.config.from_address || 'Sem e-mail configurado' }}
                                    </span>
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <span
                                    :class="[
                                        'px-3 py-1 text-xs font-medium rounded-full whitespace-nowrap',
                                        config.is_active
                                            ? 'bg-success-100 text-success-700 dark:bg-success-900/30 dark:text-success-400'
                                            : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300'
                                    ]"
                                >
                                    {{ config.is_active ? 'Ativo' : 'Inativo' }}
                                </span>

                                <BaseButton
                                    variant="secondary"
                                    size="sm"
                                    @click="testEmailConfig(config.id)"
                                    :loading="isTestingEmail"
                                    :disabled="isTestingEmail || isDeletingEmail"
                                >
                                    <BeakerIcon class="w-4 h-4" />
                                    Testar
                                </BaseButton>

                                <BaseButton
                                    variant="secondary"
                                    size="sm"
                                    @click="openEmailModal(config)"
                                    :disabled="isTestingEmail || isDeletingEmail"
                                >
                                    <PencilIcon class="w-4 h-4" />
                                    Editar
                                </BaseButton>

                                <BaseButton
                                    variant="danger"
                                    size="sm"
                                    @click="deleteEmailConfig(config.id)"
                                    :loading="isDeletingEmail"
                                    :disabled="isTestingEmail || isDeletingEmail"
                                >
                                    <TrashIcon class="w-4 h-4" />
                                    Excluir
                                </BaseButton>
                            </div>
                        </div>
                    </div>
                </BaseCard>

                <!-- Modal de Adicionar/Editar -->
                <BaseModal
                    :show="showEmailModal"
                    :title="editingEmailConfig ? 'Editar Configuração de E-mail' : 'Nova Configuração de E-mail'"
                    size="xl"
                    @close="closeEmailModal"
                >
                    <div class="space-y-6">
                        <!-- Informações Básicas -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Nome/Propósito *
                                </label>
                                <input
                                    type="text"
                                    v-model="emailForm.name"
                                    placeholder="Ex: Sincronização, Análise IA, Notificações"
                                    class="input"
                                />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Identificador *
                                </label>
                                <input
                                    type="text"
                                    v-model="emailForm.identifier"
                                    placeholder="Ex: sync, ai-analysis, notifications"
                                    class="input"
                                />
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    Slug único para uso no código
                                </p>
                            </div>
                        </div>

                        <!-- Provedor e Status -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Provedor *
                                </label>
                                <select v-model="emailForm.provider" class="input">
                                    <option
                                        v-for="provider in availableEmailProviders"
                                        :key="provider.id"
                                        :value="provider.id"
                                    >
                                        {{ provider.name }}
                                    </option>
                                </select>
                            </div>

                            <div>
                                <label class="flex items-center gap-3 cursor-pointer pt-6">
                                    <input
                                        type="checkbox"
                                        v-model="emailForm.is_active"
                                        class="rounded border-gray-300 text-primary-500 focus:ring-primary-500"
                                    />
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Configuração ativa
                                    </span>
                                </label>
                            </div>
                        </div>

                        <!-- Configurações Específicas do Provedor -->

                        <!-- SMTP -->
                        <div v-if="emailForm.provider === 'smtp'" class="p-4 bg-gray-50 dark:bg-gray-800 rounded-xl space-y-4">
                            <h3 class="font-medium text-gray-900 dark:text-gray-100 flex items-center gap-2">
                                <span class="text-xl">📧</span>
                                Configurações SMTP
                            </h3>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Host</label>
                                    <input
                                        type="text"
                                        v-model="emailForm.config.host"
                                        placeholder="smtp.gmail.com"
                                        class="input"
                                    />
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Porta</label>
                                    <input
                                        type="number"
                                        v-model.number="emailForm.config.port"
                                        placeholder="587"
                                        class="input"
                                    />
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Usuário</label>
                                    <input
                                        type="text"
                                        v-model="emailForm.config.username"
                                        placeholder="user@example.com"
                                        class="input"
                                    />
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Senha</label>
                                    <div class="relative">
                                        <input
                                            :type="showSmtpPassword ? 'text' : 'password'"
                                            v-model="emailForm.config.password"
                                            placeholder="••••••••"
                                            class="input pr-10"
                                        />
                                        <button
                                            type="button"
                                            @click="showSmtpPassword = !showSmtpPassword"
                                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                                        >
                                            <EyeIcon v-if="!showSmtpPassword" class="w-4 h-4" />
                                            <EyeSlashIcon v-else class="w-4 h-4" />
                                        </button>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Encryption</label>
                                    <select v-model="emailForm.config.encryption" class="input">
                                        <option value="tls">TLS</option>
                                        <option value="ssl">SSL</option>
                                        <option value="">Nenhum</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">From Address</label>
                                    <input
                                        type="email"
                                        v-model="emailForm.config.from_address"
                                        placeholder="noreply@example.com"
                                        class="input"
                                    />
                                </div>

                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">From Name</label>
                                    <input
                                        type="text"
                                        v-model="emailForm.config.from_name"
                                        placeholder="Nome da Empresa"
                                        class="input"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Mailgun -->
                        <div v-if="emailForm.provider === 'mailgun'" class="p-4 bg-gray-50 dark:bg-gray-800 rounded-xl space-y-4">
                            <h3 class="font-medium text-gray-900 dark:text-gray-100 flex items-center gap-2">
                                <span class="text-xl">📮</span>
                                Configurações Mailgun
                            </h3>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">API Key</label>
                                    <div class="relative">
                                        <input
                                            :type="showMailgunKey ? 'text' : 'password'"
                                            v-model="emailForm.config.api_key"
                                            placeholder="key-..."
                                            class="input pr-10"
                                        />
                                        <button
                                            type="button"
                                            @click="showMailgunKey = !showMailgunKey"
                                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                                        >
                                            <EyeIcon v-if="!showMailgunKey" class="w-4 h-4" />
                                            <EyeSlashIcon v-else class="w-4 h-4" />
                                        </button>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Domain</label>
                                    <input
                                        type="text"
                                        v-model="emailForm.config.domain"
                                        placeholder="mg.example.com"
                                        class="input"
                                    />
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">From Address</label>
                                    <input
                                        type="email"
                                        v-model="emailForm.config.from_address"
                                        placeholder="noreply@example.com"
                                        class="input"
                                    />
                                </div>

                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">From Name</label>
                                    <input
                                        type="text"
                                        v-model="emailForm.config.from_name"
                                        placeholder="Nome da Empresa"
                                        class="input"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Amazon SES -->
                        <div v-if="emailForm.provider === 'ses'" class="p-4 bg-gray-50 dark:bg-gray-800 rounded-xl space-y-4">
                            <h3 class="font-medium text-gray-900 dark:text-gray-100 flex items-center gap-2">
                                <span class="text-xl">📨</span>
                                Configurações Amazon SES
                            </h3>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Access Key ID</label>
                                    <input
                                        type="text"
                                        v-model="emailForm.config.key"
                                        placeholder="AKIA..."
                                        class="input"
                                    />
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Secret Access Key</label>
                                    <div class="relative">
                                        <input
                                            :type="showSesSecret ? 'text' : 'password'"
                                            v-model="emailForm.config.secret"
                                            placeholder="••••••••"
                                            class="input pr-10"
                                        />
                                        <button
                                            type="button"
                                            @click="showSesSecret = !showSesSecret"
                                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                                        >
                                            <EyeIcon v-if="!showSesSecret" class="w-4 h-4" />
                                            <EyeSlashIcon v-else class="w-4 h-4" />
                                        </button>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Region</label>
                                    <select v-model="emailForm.config.region" class="input">
                                        <option value="us-east-1">US East (N. Virginia)</option>
                                        <option value="us-west-2">US West (Oregon)</option>
                                        <option value="eu-west-1">EU (Ireland)</option>
                                        <option value="sa-east-1">South America (São Paulo)</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">From Address</label>
                                    <input
                                        type="email"
                                        v-model="emailForm.config.from_address"
                                        placeholder="noreply@example.com"
                                        class="input"
                                    />
                                </div>

                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">From Name</label>
                                    <input
                                        type="text"
                                        v-model="emailForm.config.from_name"
                                        placeholder="Nome da Empresa"
                                        class="input"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Postmark -->
                        <div v-if="emailForm.provider === 'postmark'" class="p-4 bg-gray-50 dark:bg-gray-800 rounded-xl space-y-4">
                            <h3 class="font-medium text-gray-900 dark:text-gray-100 flex items-center gap-2">
                                <span class="text-xl">✉️</span>
                                Configurações Postmark
                            </h3>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Server Token</label>
                                    <div class="relative">
                                        <input
                                            :type="showPostmarkToken ? 'text' : 'password'"
                                            v-model="emailForm.config.token"
                                            placeholder="..."
                                            class="input pr-10"
                                        />
                                        <button
                                            type="button"
                                            @click="showPostmarkToken = !showPostmarkToken"
                                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                                        >
                                            <EyeIcon v-if="!showPostmarkToken" class="w-4 h-4" />
                                            <EyeSlashIcon v-else class="w-4 h-4" />
                                        </button>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">From Address</label>
                                    <input
                                        type="email"
                                        v-model="emailForm.config.from_address"
                                        placeholder="noreply@example.com"
                                        class="input"
                                    />
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">From Name</label>
                                    <input
                                        type="text"
                                        v-model="emailForm.config.from_name"
                                        placeholder="Nome da Empresa"
                                        class="input"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Resend -->
                        <div v-if="emailForm.provider === 'resend'" class="p-4 bg-gray-50 dark:bg-gray-800 rounded-xl space-y-4">
                            <h3 class="font-medium text-gray-900 dark:text-gray-100 flex items-center gap-2">
                                <span class="text-xl">🚀</span>
                                Configurações Resend
                            </h3>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">API Key</label>
                                    <div class="relative">
                                        <input
                                            :type="showResendKey ? 'text' : 'password'"
                                            v-model="emailForm.config.api_key"
                                            placeholder="re_..."
                                            class="input pr-10"
                                        />
                                        <button
                                            type="button"
                                            @click="showResendKey = !showResendKey"
                                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                                        >
                                            <EyeIcon v-if="!showResendKey" class="w-4 h-4" />
                                            <EyeSlashIcon v-else class="w-4 h-4" />
                                        </button>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">From Address</label>
                                    <input
                                        type="email"
                                        v-model="emailForm.config.from_address"
                                        placeholder="noreply@example.com"
                                        class="input"
                                    />
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">From Name</label>
                                    <input
                                        type="text"
                                        v-model="emailForm.config.from_name"
                                        placeholder="Nome da Empresa"
                                        class="input"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <template #footer>
                        <div class="flex justify-end gap-3">
                            <BaseButton
                                variant="secondary"
                                @click="closeEmailModal"
                                :disabled="isSavingEmail"
                            >
                                Cancelar
                            </BaseButton>
                            <BaseButton
                                @click="saveEmailConfig"
                                :loading="isSavingEmail"
                            >
                                {{ editingEmailConfig ? 'Atualizar' : 'Criar' }} Configuração
                            </BaseButton>
                        </div>
                    </template>
                </BaseModal>
            </div>

            <!-- Sync Tab -->
            <div v-show="activeTab === 'sync'">
                <BaseCard>
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center gap-3">
                            <MapPinIcon class="w-6 h-6 text-primary-500" />
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Localidades do Brasil</h2>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Sincronização de estados e cidades via API do IBGE</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <span
                                v-if="!locationsStatus.needs_sync"
                                class="px-3 py-1 text-xs font-medium rounded-full bg-success-100 text-success-700 dark:bg-success-900/30 dark:text-success-400"
                            >
                                Sincronizado
                            </span>
                            <span
                                v-else
                                class="px-3 py-1 text-xs font-medium rounded-full bg-warning-100 text-warning-700 dark:bg-warning-900/30 dark:text-warning-400"
                            >
                                Precisa sincronizar
                            </span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                        <!-- Stats -->
                        <div class="bg-gray-50 dark:bg-gray-800 rounded-xl p-4">
                            <p class="text-sm text-gray-500 dark:text-gray-400">Última sincronização</p>
                            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100 mt-1">
                                {{ formatSyncDate(locationsStatus.last_sync) }}
                            </p>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-800 rounded-xl p-4">
                            <p class="text-sm text-gray-500 dark:text-gray-400">Estados</p>
                            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100 mt-1">
                                {{ locationsStatus.states_count || 0 }}
                            </p>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-800 rounded-xl p-4">
                            <p class="text-sm text-gray-500 dark:text-gray-400">Cidades</p>
                            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100 mt-1">
                                {{ locationsStatus.cities_count || 0 }}
                            </p>
                        </div>
                        <div class="flex items-center justify-center">
                            <BaseButton
                                variant="secondary"
                                @click="syncLocations"
                                :loading="isSyncingLocations"
                                :disabled="isSyncingLocations"
                            >
                                <ArrowPathIcon class="w-4 h-4" :class="{ 'animate-spin': isSyncingLocations }" />
                                {{ isSyncingLocations ? 'Sincronizando...' : 'Sincronizar Agora' }}
                            </BaseButton>
                        </div>
                    </div>

                    <div class="mt-4 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-xl">
                        <p class="text-sm text-blue-700 dark:text-blue-300">
                            <strong>Sincronização automática:</strong> Os dados são atualizados automaticamente todo domingo às 3h da manhã.
                            Use o botão acima para forçar uma atualização manual quando necessário.
                        </p>
                    </div>
                </BaseCard>
            </div>
        </div>
    </div>
</template>

