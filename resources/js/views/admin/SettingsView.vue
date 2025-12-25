<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useNotificationStore } from '../../stores/notificationStore';
import api from '../../services/api';
import BaseCard from '../../components/common/BaseCard.vue';
import BaseButton from '../../components/common/BaseButton.vue';
import BaseInput from '../../components/common/BaseInput.vue';
import LoadingSpinner from '../../components/common/LoadingSpinner.vue';
import {
    Cog6ToothIcon,
    SparklesIcon,
    CheckCircleIcon,
    XCircleIcon,
    BeakerIcon,
    EyeIcon,
    EyeSlashIcon,
} from '@heroicons/vue/24/outline';

const notificationStore = useNotificationStore();

const isLoading = ref(true);
const isSaving = ref(false);
const isTesting = ref(false);
const testingProvider = ref(null);

const showOpenAIKey = ref(false);
const showGeminiKey = ref(false);

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
});

const availableProviders = ref([]);
const availableModels = ref({});
const testResults = reactive({
    openai: null,
    gemini: null,
});

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
    return providerId === 'openai' ? '🤖' : '✨';
}

onMounted(() => {
    fetchSettings();
});
</script>

<template>
    <div class="space-y-6">
        <!-- Page Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-display font-bold text-gray-900 flex items-center gap-3">
                    <Cog6ToothIcon class="w-8 h-8 text-primary-500" />
                    Configurações do Sistema
                </h1>
                <p class="text-gray-500 mt-1">Gerencie as configurações de IA e integrações</p>
            </div>
        </div>

        <!-- Loading -->
        <div v-if="isLoading" class="flex items-center justify-center py-20">
            <LoadingSpinner size="lg" class="text-primary-500" />
        </div>

        <div v-else class="grid grid-cols-1 lg:grid-cols-3 gap-6">
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

                    <div class="mt-6 p-4 bg-gray-50 rounded-xl">
                        <p class="text-sm text-gray-600">
                            <strong>Provedor ativo:</strong> {{ settings.provider === 'openai' ? 'OpenAI' : 'Google Gemini' }}
                        </p>
                        <p class="text-xs text-gray-500 mt-1">
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
    </div>
</template>

