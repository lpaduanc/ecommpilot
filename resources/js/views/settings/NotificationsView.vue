<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useAuthStore } from '../../stores/authStore';
import { useNotificationStore } from '../../stores/notificationStore';
import api from '../../services/api';
import BaseCard from '../../components/common/BaseCard.vue';
import BaseButton from '../../components/common/BaseButton.vue';
import LoadingSpinner from '../../components/common/LoadingSpinner.vue';
import {
    BellIcon,
    ChartBarIcon,
    ArchiveBoxIcon,
    ShoppingCartIcon,
    SparklesIcon,
    InformationCircleIcon,
} from '@heroicons/vue/24/outline';

const authStore = useAuthStore();
const notificationStore = useNotificationStore();

const isLoadingNotifications = ref(false);
const isSavingNotifications = ref(false);

const notificationSettings = reactive({
    email_analysis: true,
    stock_alerts: true,
    new_orders: false,
    system_updates: true,
});

// Configuração visual de cada tipo de notificação
const notificationTypes = [
    {
        key: 'email_analysis',
        icon: ChartBarIcon,
        iconColor: 'text-blue-500',
        iconBg: 'bg-blue-100 dark:bg-blue-900/30',
        name: 'Análises e Insights',
        description: 'Receber resumo semanal das análises e sugestões geradas pela IA',
    },
    {
        key: 'stock_alerts',
        icon: ArchiveBoxIcon,
        iconColor: 'text-amber-500',
        iconBg: 'bg-amber-100 dark:bg-amber-900/30',
        name: 'Alertas de Estoque',
        description: 'Notificar quando produtos estiverem com estoque baixo ou zerado',
    },
    {
        key: 'new_orders',
        icon: ShoppingCartIcon,
        iconColor: 'text-emerald-500',
        iconBg: 'bg-emerald-100 dark:bg-emerald-900/30',
        name: 'Novos Pedidos',
        description: 'Receber notificação instantânea a cada novo pedido realizado',
    },
    {
        key: 'system_updates',
        icon: SparklesIcon,
        iconColor: 'text-purple-500',
        iconBg: 'bg-purple-100 dark:bg-purple-900/30',
        name: 'Atualizações do Sistema',
        description: 'Novidades, melhorias e recursos da plataforma',
    },
];

async function loadNotificationSettings() {
    isLoadingNotifications.value = true;
    try {
        const response = await api.get('/settings/notifications');
        Object.assign(notificationSettings, response.data);
    } catch {
        // Use defaults
    } finally {
        isLoadingNotifications.value = false;
    }
}

async function saveNotificationSettings() {
    isSavingNotifications.value = true;

    try {
        await api.put('/settings/notifications', notificationSettings);
        notificationStore.success('Preferências de notificação salvas!');
    } catch (error) {
        notificationStore.error('Erro ao salvar preferências');
    } finally {
        isSavingNotifications.value = false;
    }
}

onMounted(() => {
    loadNotificationSettings();
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
                    <div class="w-10 sm:w-12 lg:w-14 h-10 sm:h-12 lg:h-14 rounded-xl sm:rounded-2xl bg-gradient-to-br from-amber-400 to-orange-500 flex items-center justify-center shadow-lg shadow-amber-500/30 flex-shrink-0">
                        <BellIcon class="w-5 sm:w-6 lg:w-7 h-5 sm:h-6 lg:h-7 text-white" />
                    </div>
                    <div class="min-w-0">
                        <h1 class="text-xl sm:text-2xl lg:text-4xl font-display font-bold text-white">
                            Notificações
                        </h1>
                        <p class="text-primary-200/80 text-xs sm:text-sm lg:text-base">
                            Configure como e quando você deseja ser notificado
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="px-8 py-8 bg-gradient-to-b from-gray-100 to-gray-50 dark:from-gray-900 dark:to-gray-950 min-h-[calc(100vh-200px)]">
            <div class="max-w-4xl mx-auto">
                <!-- Settings Card -->
                <BaseCard padding="lg">
                    <!-- Loading State -->
                    <div v-if="isLoadingNotifications" class="flex items-center justify-center py-12">
                        <LoadingSpinner size="lg" />
                    </div>

                    <template v-else>
                        <div class="mb-6">
                            <div class="flex items-center gap-2 mb-2">
                                <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-amber-500 to-orange-600 flex items-center justify-center">
                                    <BellIcon class="w-4 h-4 text-white" />
                                </div>
                                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                    Notificações por E-mail
                                </h2>
                            </div>
                            <p class="text-gray-500 dark:text-gray-400 text-sm">
                                Escolha quais notificações você deseja receber
                            </p>
                        </div>

                        <!-- Info Banner -->
                        <div class="mb-6 p-4 rounded-xl bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800">
                            <div class="flex items-start gap-3">
                                <InformationCircleIcon class="w-5 h-5 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" />
                                <div>
                                    <h3 class="font-semibold text-blue-900 dark:text-blue-100 mb-1">
                                        Sobre as Notificações
                                    </h3>
                                    <p class="text-sm text-blue-700 dark:text-blue-300">
                                        Configure suas preferências para receber notificações importantes sobre sua loja.
                                        As notificações serão enviadas para o e-mail cadastrado em sua conta.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Notification Items Grid -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div
                                v-for="type in notificationTypes"
                                :key="type.key"
                                :class="[
                                    'p-5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800/50 transition-all',
                                    authStore.hasPermission('settings.edit') ? 'hover:shadow-md' : 'opacity-60'
                                ]"
                            >
                                <div class="flex items-start justify-between mb-4">
                                    <div class="flex items-start gap-3">
                                        <div :class="['w-11 h-11 rounded-xl flex items-center justify-center flex-shrink-0', type.iconBg]">
                                            <component :is="type.icon" :class="['w-5 h-5', type.iconColor]" />
                                        </div>
                                        <div>
                                            <h3 class="font-semibold text-base text-gray-900 dark:text-gray-100 mb-1">
                                                {{ type.name }}
                                            </h3>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed">
                                                {{ type.description }}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Toggle Switch -->
                                <div class="flex justify-end">
                                    <label class="relative inline-flex items-center cursor-pointer flex-shrink-0">
                                        <input
                                            type="checkbox"
                                            v-model="notificationSettings[type.key]"
                                            :disabled="!authStore.hasPermission('settings.edit')"
                                            class="sr-only peer"
                                        />
                                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-primary-300 dark:peer-focus:ring-primary-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-primary-600 disabled:opacity-50 disabled:cursor-not-allowed"></div>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <BaseButton
                            v-if="authStore.hasPermission('settings.edit')"
                            class="mt-6"
                            @click="saveNotificationSettings"
                            :loading="isSavingNotifications"
                        >
                            Salvar Preferências
                        </BaseButton>
                        <p v-else class="text-sm text-gray-500 dark:text-gray-400 italic mt-6">
                            Você não possui permissão para editar as configurações.
                        </p>
                    </template>
                </BaseCard>
            </div>
        </div>
    </div>
</template>
