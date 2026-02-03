<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
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
    ChartBarIcon,
    CheckCircleIcon,
} from '@heroicons/vue/24/outline';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const dashboardStore = useDashboardStore();
const notificationStore = useNotificationStore();

const isLoading = ref(true);
const isSaving = ref(false);

const trackingForm = reactive({
    ga: {
        enabled: false,
        measurement_id: '',
    },
    meta_pixel: {
        enabled: false,
        pixel_id: '',
    },
    clarity: {
        enabled: false,
        project_id: '',
    },
    hotjar: {
        enabled: false,
        site_id: '',
    },
});

const storeId = computed(() => {
    return route.params.id || dashboardStore.activeStore?.id;
});

const storeName = computed(() => {
    return dashboardStore.activeStore?.name || 'Loja';
});

const canEdit = computed(() => {
    return authStore.hasPermission('integrations.manage');
});

async function loadTrackingSettings() {
    if (!storeId.value) {
        notificationStore.error('Nenhuma loja selecionada');
        router.push({ name: 'integrations' });
        return;
    }

    isLoading.value = true;
    try {
        const response = await api.get('/settings/tracking/edit');
        if (response.data?.data) {
            Object.assign(trackingForm.ga, response.data.data.ga || {});
            Object.assign(trackingForm.meta_pixel, response.data.data.meta_pixel || {});
            Object.assign(trackingForm.clarity, response.data.data.clarity || {});
            Object.assign(trackingForm.hotjar, response.data.data.hotjar || {});
        }
    } catch {
        notificationStore.error('Erro ao carregar configurações de tracking');
    } finally {
        isLoading.value = false;
    }
}

async function saveTrackingSettings() {
    isSaving.value = true;
    try {
        await api.put('/settings/tracking', trackingForm);
        notificationStore.success('Configurações de tracking salvas com sucesso!');
    } catch (error) {
        notificationStore.error(error.response?.data?.message || 'Erro ao salvar configurações de tracking');
    } finally {
        isSaving.value = false;
    }
}

onMounted(async () => {
    await loadTrackingSettings();
});
</script>

<template>
    <div class="min-h-screen -m-8 -mt-8">
        <!-- Hero Header with Gradient -->
        <div class="relative overflow-hidden bg-gradient-to-br from-cyan-900 via-blue-950 to-indigo-950 dark:from-gray-950 dark:via-gray-900 dark:to-gray-950 px-8 py-12">
            <!-- Background Elements -->
            <div class="absolute inset-0 overflow-hidden">
                <div class="absolute -top-40 -right-40 w-80 h-80 bg-cyan-500/20 rounded-full blur-3xl"></div>
                <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-blue-500/20 rounded-full blur-3xl"></div>
                <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-96 h-96 bg-indigo-500/10 rounded-full blur-3xl"></div>
                <!-- Grid Pattern -->
                <div class="absolute inset-0" style="background-image: radial-gradient(rgba(255,255,255,0.03) 1px, transparent 1px); background-size: 40px 40px;"></div>
            </div>

            <div class="relative z-10 max-w-7xl mx-auto">
                <div class="flex items-center gap-3">
                    <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-cyan-400 to-blue-500 flex items-center justify-center shadow-lg shadow-cyan-500/30">
                        <ChartBarIcon class="w-7 h-7 text-white" />
                    </div>
                    <div>
                        <h1 class="text-3xl lg:text-4xl font-display font-bold text-white">
                            Integrações de Tracking
                        </h1>
                        <p class="text-cyan-200/80 text-sm lg:text-base">
                            {{ storeName }} - Configure códigos de rastreamento e analytics
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
                    <div class="w-20 h-20 rounded-full bg-gradient-to-r from-cyan-500 to-blue-500"></div>
                    <LoadingSpinner size="xl" class="absolute inset-0 m-auto text-white" />
                </div>
            </div>

            <template v-else>
                <div class="max-w-5xl mx-auto space-y-8">
                    <!-- Info Banner -->
                    <div class="p-4 rounded-xl bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800">
                        <div class="flex items-start gap-3">
                            <ChartBarIcon class="w-5 h-5 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" />
                            <div>
                                <h3 class="font-semibold text-blue-900 dark:text-blue-100 mb-1">
                                    Sobre as Integrações de Tracking
                                </h3>
                                <p class="text-sm text-blue-700 dark:text-blue-300">
                                    Configure os códigos de rastreamento das principais ferramentas de analytics e marketing.
                                    Os códigos serão automaticamente inseridos na sua loja quando habilitados.
                                    <strong class="block mt-2">Certifique-se de usar os IDs corretos para evitar perda de dados.</strong>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Tracking Services Grid -->
                    <BaseCard padding="lg">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-6">
                            Serviços de Tracking
                        </h2>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Google Analytics 4 -->
                            <div class="p-5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800/50 hover:shadow-md transition-shadow">
                                <div class="flex items-start justify-between mb-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-yellow-400 to-orange-500 flex items-center justify-center flex-shrink-0 shadow-sm">
                                            <svg class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M12.87 15.07l-2.54-2.51.03-.03A17.52 17.52 0 0014.07 6H17V4h-7V2H8v2H1v2h11.17C11.5 7.92 10.44 9.75 9 11.35 8.07 10.32 7.3 9.19 6.69 8h-2c.73 1.63 1.73 3.17 2.98 4.56l-5.09 5.02L4 19l5-5 3.11 3.11.76-2.04zM18.5 10h-2L12 22h2l1.12-3h4.75L21 22h2l-4.5-12zm-2.62 7l1.62-4.33L19.12 17h-3.24z"/>
                                            </svg>
                                        </div>
                                        <div>
                                            <h3 class="font-semibold text-base text-gray-900 dark:text-gray-100">Google Analytics 4</h3>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">Métricas e conversão</p>
                                        </div>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer flex-shrink-0">
                                        <input
                                            type="checkbox"
                                            v-model="trackingForm.ga.enabled"
                                            :disabled="!canEdit"
                                            class="sr-only peer"
                                        />
                                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-primary-300 dark:peer-focus:ring-primary-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-primary-600"></div>
                                    </label>
                                </div>
                                <div v-if="trackingForm.ga.enabled">
                                    <BaseInput
                                        v-model="trackingForm.ga.measurement_id"
                                        label="Measurement ID"
                                        placeholder="G-XXXXXXXXXX"
                                        hint="Google Analytics > Administrador > Fluxo de dados"
                                        :disabled="!canEdit"
                                    />
                                </div>
                            </div>

                            <!-- Meta Pixel -->
                            <div class="p-5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800/50 hover:shadow-md transition-shadow">
                                <div class="flex items-start justify-between mb-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-blue-500 to-blue-700 flex items-center justify-center flex-shrink-0 shadow-sm">
                                            <svg class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M12 2.04C6.5 2.04 2 6.53 2 12.06C2 17.06 5.66 21.21 10.44 21.96V14.96H7.9V12.06H10.44V9.85C10.44 7.34 11.93 5.96 14.22 5.96C15.31 5.96 16.45 6.15 16.45 6.15V8.62H15.19C13.95 8.62 13.56 9.39 13.56 10.18V12.06H16.34L15.89 14.96H13.56V21.96C18.34 21.21 22 17.06 22 12.06C22 6.53 17.5 2.04 12 2.04Z"/>
                                            </svg>
                                        </div>
                                        <div>
                                            <h3 class="font-semibold text-base text-gray-900 dark:text-gray-100">Meta Pixel</h3>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">Facebook & Instagram Ads</p>
                                        </div>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer flex-shrink-0">
                                        <input
                                            type="checkbox"
                                            v-model="trackingForm.meta_pixel.enabled"
                                            :disabled="!canEdit"
                                            class="sr-only peer"
                                        />
                                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-primary-300 dark:peer-focus:ring-primary-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-primary-600"></div>
                                    </label>
                                </div>
                                <div v-if="trackingForm.meta_pixel.enabled">
                                    <BaseInput
                                        v-model="trackingForm.meta_pixel.pixel_id"
                                        label="Pixel ID"
                                        placeholder="123456789012345"
                                        hint="Meta Business Suite > Gerenciador de Eventos"
                                        :disabled="!canEdit"
                                    />
                                </div>
                            </div>

                            <!-- Microsoft Clarity -->
                            <div class="p-5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800/50 hover:shadow-md transition-shadow">
                                <div class="flex items-start justify-between mb-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-cyan-400 to-blue-500 flex items-center justify-center flex-shrink-0 shadow-sm">
                                            <svg class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M21.17 3.25H2.83c-.46 0-.83.37-.83.83v15.84c0 .46.37.83.83.83h18.34c.46 0 .83-.37.83-.83V4.08c0-.46-.37-.83-.83-.83zM12 18.25c-3.45 0-6.25-2.8-6.25-6.25S8.55 5.75 12 5.75s6.25 2.8 6.25 6.25-2.8 6.25-6.25 6.25z"/>
                                            </svg>
                                        </div>
                                        <div>
                                            <h3 class="font-semibold text-base text-gray-900 dark:text-gray-100">Microsoft Clarity</h3>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">Heatmaps e sessões (gratuito)</p>
                                        </div>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer flex-shrink-0">
                                        <input
                                            type="checkbox"
                                            v-model="trackingForm.clarity.enabled"
                                            :disabled="!canEdit"
                                            class="sr-only peer"
                                        />
                                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-primary-300 dark:peer-focus:ring-primary-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-primary-600"></div>
                                    </label>
                                </div>
                                <div v-if="trackingForm.clarity.enabled">
                                    <BaseInput
                                        v-model="trackingForm.clarity.project_id"
                                        label="Project ID"
                                        placeholder="abcdefghij"
                                        hint="clarity.microsoft.com > Configurações > Setup"
                                        :disabled="!canEdit"
                                    />
                                </div>
                            </div>

                            <!-- Hotjar -->
                            <div class="p-5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800/50 hover:shadow-md transition-shadow">
                                <div class="flex items-start justify-between mb-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-orange-400 to-red-500 flex items-center justify-center flex-shrink-0 shadow-sm">
                                            <svg class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                            </svg>
                                        </div>
                                        <div>
                                            <h3 class="font-semibold text-base text-gray-900 dark:text-gray-100">Hotjar</h3>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">Heatmaps e feedback</p>
                                        </div>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer flex-shrink-0">
                                        <input
                                            type="checkbox"
                                            v-model="trackingForm.hotjar.enabled"
                                            :disabled="!canEdit"
                                            class="sr-only peer"
                                        />
                                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-primary-300 dark:peer-focus:ring-primary-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-primary-600"></div>
                                    </label>
                                </div>
                                <div v-if="trackingForm.hotjar.enabled">
                                    <BaseInput
                                        v-model="trackingForm.hotjar.site_id"
                                        label="Site ID"
                                        placeholder="1234567"
                                        hint="insights.hotjar.com > Sites & Organizations"
                                        :disabled="!canEdit"
                                    />
                                </div>
                            </div>
                        </div>
                    </BaseCard>

                    <!-- Action Buttons -->
                    <div class="flex justify-end gap-4">
                        <BaseButton
                            variant="secondary"
                            @click="router.push({ name: 'integrations' })"
                        >
                            Voltar
                        </BaseButton>
                        <BaseButton
                            v-if="canEdit"
                            @click="saveTrackingSettings"
                            :loading="isSaving"
                        >
                            <CheckCircleIcon class="w-5 h-5" />
                            Salvar Configurações de Tracking
                        </BaseButton>
                    </div>

                    <p v-if="!canEdit" class="text-center text-sm text-gray-500 dark:text-gray-400 italic">
                        Você não possui permissão para editar as configurações de tracking.
                    </p>
                </div>
            </template>
        </div>
    </div>
</template>
