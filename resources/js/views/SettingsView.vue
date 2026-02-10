<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/authStore';
import { useNotificationStore } from '../stores/notificationStore';
import api from '../services/api';
import BaseCard from '../components/common/BaseCard.vue';
import BaseButton from '../components/common/BaseButton.vue';
import BaseInput from '../components/common/BaseInput.vue';
import LoadingSpinner from '../components/common/LoadingSpinner.vue';
import {
    Cog6ToothIcon,
    UserCircleIcon,
    LockClosedIcon,
    BellIcon,
    UsersIcon,
    BuildingStorefrontIcon,
    SignalIcon,
    CheckCircleIcon,
    ChartBarIcon,
} from '@heroicons/vue/24/outline';

const router = useRouter();
const authStore = useAuthStore();
const notificationStore = useNotificationStore();

const activeTab = ref('profile');
const isLoadingProfile = ref(false);
const isLoadingPassword = ref(false);
const isLoadingNotifications = ref(false);
const isSavingNotifications = ref(false);
const isLoadingStore = ref(false);
const isLoadingTracking = ref(false);
const isSavingTracking = ref(false);
const storeInfo = ref(null);

const profileForm = reactive({
    name: '',
    email: '',
    phone: '',
});

const profileErrors = reactive({
    name: '',
    email: '',
    phone: '',
});

const passwordForm = reactive({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const passwordErrors = reactive({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const notificationSettings = reactive({
    email_analysis: true,
    stock_alerts: true,
    new_orders: false,
    system_updates: true,
});

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

const tabs = [
    { id: 'profile', name: 'Perfil', icon: UserCircleIcon, gradient: 'from-blue-500 to-indigo-600' },
    { id: 'password', name: 'Senha', icon: LockClosedIcon, gradient: 'from-purple-500 to-pink-600' },
    { id: 'notifications', name: 'Notificações', icon: BellIcon, gradient: 'from-amber-500 to-orange-600' },
    { id: 'store', name: 'Informações da Loja', icon: BuildingStorefrontIcon, gradient: 'from-green-500 to-emerald-600' },
    { id: 'tracking', name: 'Tracking & Analytics', icon: SignalIcon, gradient: 'from-cyan-500 to-blue-600' },
]

const visibleTabs = computed(() => {
    const baseTabs = [...tabs]

    // Adiciona a aba de Usuários APENAS para Clientes (não Admin) com permissão
    // Admins gerenciam clientes via /admin/clients
    if (!authStore.isAdmin && authStore.hasPermission('users.view')) {
        baseTabs.push({
            id: 'users',
            name: 'Usuários',
            icon: UsersIcon,
            gradient: 'from-green-500 to-emerald-600',
            route: 'users-management'
        })
    }

    return baseTabs
})

function loadUserData() {
    profileForm.name = authStore.user?.name || '';
    profileForm.email = authStore.user?.email || '';
    profileForm.phone = authStore.user?.phone || '';
}

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

async function loadStoreInfo() {
    isLoadingStore.value = true;
    try {
        const response = await api.get('/integrations/my-stores');
        const activeStoreId = response.data.active_store_id;
        if (activeStoreId) {
            storeInfo.value = response.data.stores.find(s => s.id === activeStoreId);
        }
    } catch {
        storeInfo.value = null;
    } finally {
        isLoadingStore.value = false;
    }
}

async function updateProfile() {
    clearProfileErrors();
    
    if (!validateProfileForm()) return;
    
    isLoadingProfile.value = true;
    
    const result = await authStore.updateProfile(profileForm);
    
    isLoadingProfile.value = false;
    
    if (result.success) {
        notificationStore.success('Perfil atualizado com sucesso!');
    } else {
        if (result.errors) {
            Object.keys(result.errors).forEach(key => {
                if (profileErrors[key] !== undefined) {
                    profileErrors[key] = result.errors[key][0];
                }
            });
        }
        notificationStore.error(result.message);
    }
}

async function updatePassword() {
    clearPasswordErrors();
    
    if (!validatePasswordForm()) return;
    
    isLoadingPassword.value = true;
    
    const result = await authStore.updatePassword(passwordForm);
    
    isLoadingPassword.value = false;
    
    if (result.success) {
        notificationStore.success('Senha atualizada com sucesso!');
        resetPasswordForm();
    } else {
        if (result.errors) {
            Object.keys(result.errors).forEach(key => {
                if (passwordErrors[key] !== undefined) {
                    passwordErrors[key] = result.errors[key][0];
                }
            });
        }
        notificationStore.error(result.message);
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

async function loadTrackingSettings() {
    isLoadingTracking.value = true;
    try {
        const response = await api.get('/settings/tracking/edit');
        if (response.data?.data) {
            Object.assign(trackingForm.ga, response.data.data.ga || {});
            Object.assign(trackingForm.meta_pixel, response.data.data.meta_pixel || {});
            Object.assign(trackingForm.clarity, response.data.data.clarity || {});
            Object.assign(trackingForm.hotjar, response.data.data.hotjar || {});
        }
    } catch {
        // Use defaults
    } finally {
        isLoadingTracking.value = false;
    }
}

async function saveTrackingSettings() {
    isSavingTracking.value = true;
    try {
        await api.put('/settings/tracking', trackingForm);
        notificationStore.success('Configurações de tracking salvas com sucesso!');
    } catch (error) {
        notificationStore.error(error.response?.data?.message || 'Erro ao salvar configurações de tracking');
    } finally {
        isSavingTracking.value = false;
    }
}

function validateProfileForm() {
    let valid = true;
    
    if (!profileForm.name) {
        profileErrors.name = 'O nome é obrigatório';
        valid = false;
    }
    
    if (!profileForm.email) {
        profileErrors.email = 'O e-mail é obrigatório';
        valid = false;
    }
    
    return valid;
}

function validatePasswordForm() {
    let valid = true;
    
    if (!passwordForm.current_password) {
        passwordErrors.current_password = 'A senha atual é obrigatória';
        valid = false;
    }
    
    if (!passwordForm.password) {
        passwordErrors.password = 'A nova senha é obrigatória';
        valid = false;
    } else if (passwordForm.password.length < 8) {
        passwordErrors.password = 'A senha deve ter pelo menos 8 caracteres';
        valid = false;
    }
    
    if (passwordForm.password !== passwordForm.password_confirmation) {
        passwordErrors.password_confirmation = 'As senhas não conferem';
        valid = false;
    }
    
    return valid;
}

function clearProfileErrors() {
    Object.keys(profileErrors).forEach(key => {
        profileErrors[key] = '';
    });
}

function clearPasswordErrors() {
    Object.keys(passwordErrors).forEach(key => {
        passwordErrors[key] = '';
    });
}

function resetPasswordForm() {
    passwordForm.current_password = '';
    passwordForm.password = '';
    passwordForm.password_confirmation = '';
}

function handleTabClick(tab) {
    if (tab.route) {
        // Se a aba tem rota, navega para ela
        router.push({ name: tab.route });
    } else {
        // Se não, apenas muda a aba ativa
        activeTab.value = tab.id;
    }
}

onMounted(() => {
    loadUserData();
    loadNotificationSettings();
    loadStoreInfo();
    loadTrackingSettings();
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
                        <Cog6ToothIcon class="w-7 h-7 text-white" />
                    </div>
                    <div>
                        <h1 class="text-3xl lg:text-4xl font-display font-bold text-white">
                            Configurações
                        </h1>
                        <p class="text-primary-200/80 text-sm lg:text-base">
                            Gerencie sua conta e preferências
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="px-8 py-8 bg-gradient-to-b from-gray-100 to-gray-50 dark:from-gray-900 dark:to-gray-950 min-h-[calc(100vh-200px)]">
            <div class="max-w-7xl mx-auto">
                <div class="flex flex-col lg:flex-row gap-8">
                    <!-- Tabs Navigation -->
                    <div class="w-full lg:w-64 flex-shrink-0">
                        <BaseCard padding="sm">
                            <nav class="space-y-1">
                                <button
                                    v-for="tab in visibleTabs"
                                    :key="tab.id"
                                    @click="handleTabClick(tab)"
                                    :class="[
                                        'w-full flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all duration-200',
                                        activeTab === tab.id
                                            ? 'bg-gradient-to-r text-white shadow-lg'
                                            : 'text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700',
                                        activeTab === tab.id ? tab.gradient : ''
                                    ]"
                                >
                                    <component :is="tab.icon" class="w-5 h-5" />
                                    {{ tab.name }}
                                </button>
                            </nav>
                        </BaseCard>
                    </div>

                    <!-- Tab Content -->
                    <div class="flex-1">
                        <!-- Profile Tab -->
                        <BaseCard v-if="activeTab === 'profile'" padding="lg">
                            <div class="max-w-xl">
                                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-6 flex items-center gap-2">
                                    <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center">
                                        <UserCircleIcon class="w-4 h-4 text-white" />
                                    </div>
                                    Informações do Perfil
                                </h2>
                                
                                <form @submit.prevent="updateProfile" class="space-y-5">
                                    <BaseInput
                                        v-model="profileForm.name"
                                        type="text"
                                        label="Nome"
                                        placeholder="Seu nome completo"
                                        :error="profileErrors.name"
                                        :disabled="!authStore.hasPermission('settings.edit')"
                                    />

                                    <BaseInput
                                        v-model="profileForm.email"
                                        type="email"
                                        label="E-mail"
                                        placeholder="seu@email.com"
                                        :error="profileErrors.email"
                                        :disabled="!authStore.hasPermission('settings.edit')"
                                    />

                                    <BaseInput
                                        v-model="profileForm.phone"
                                        type="tel"
                                        label="Telefone"
                                        placeholder="(00) 00000-0000"
                                        :error="profileErrors.phone"
                                        :disabled="!authStore.hasPermission('settings.edit')"
                                    />

                                    <BaseButton
                                        v-if="authStore.hasPermission('settings.edit')"
                                        type="submit"
                                        :loading="isLoadingProfile"
                                    >
                                        Salvar Alterações
                                    </BaseButton>
                                    <p v-else class="text-sm text-gray-500 dark:text-gray-400 italic">
                                        Você não possui permissão para editar as configurações.
                                    </p>
                                </form>
                            </div>
                        </BaseCard>

                        <!-- Password Tab -->
                        <BaseCard v-if="activeTab === 'password'" padding="lg">
                            <div class="max-w-xl">
                                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-6 flex items-center gap-2">
                                    <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-purple-500 to-pink-600 flex items-center justify-center">
                                        <LockClosedIcon class="w-4 h-4 text-white" />
                                    </div>
                                    Alterar Senha
                                </h2>

                                <form @submit.prevent="updatePassword" class="space-y-5">
                                    <BaseInput
                                        v-model="passwordForm.current_password"
                                        type="password"
                                        label="Senha Atual"
                                        placeholder="••••••••"
                                        :error="passwordErrors.current_password"
                                        :disabled="!authStore.hasPermission('settings.edit')"
                                    />

                                    <BaseInput
                                        v-model="passwordForm.password"
                                        type="password"
                                        label="Nova Senha"
                                        placeholder="••••••••"
                                        hint="Mínimo de 8 caracteres"
                                        :error="passwordErrors.password"
                                        :disabled="!authStore.hasPermission('settings.edit')"
                                    />

                                    <BaseInput
                                        v-model="passwordForm.password_confirmation"
                                        type="password"
                                        label="Confirmar Nova Senha"
                                        placeholder="••••••••"
                                        :error="passwordErrors.password_confirmation"
                                        :disabled="!authStore.hasPermission('settings.edit')"
                                    />

                                    <BaseButton
                                        v-if="authStore.hasPermission('settings.edit')"
                                        type="submit"
                                        :loading="isLoadingPassword"
                                    >
                                        Alterar Senha
                                    </BaseButton>
                                    <p v-else class="text-sm text-gray-500 dark:text-gray-400 italic">
                                        Você não possui permissão para editar as configurações.
                                    </p>
                                </form>
                            </div>
                        </BaseCard>

                        <!-- Notifications Tab -->
                        <BaseCard v-if="activeTab === 'notifications'" padding="lg">
                            <div class="max-w-xl">
                                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-6 flex items-center gap-2">
                                    <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-amber-500 to-orange-600 flex items-center justify-center">
                                        <BellIcon class="w-4 h-4 text-white" />
                                    </div>
                                    Preferências de Notificação
                                </h2>

                                <div class="space-y-4">
                                    <label :class="[
                                        'flex items-center justify-between p-4 rounded-xl border border-gray-200 dark:border-gray-700 transition-all',
                                        authStore.hasPermission('settings.edit') ? 'cursor-pointer hover:bg-gradient-to-r hover:from-gray-50 dark:hover:from-gray-700 hover:to-transparent' : 'opacity-60 cursor-not-allowed'
                                    ]">
                                        <div>
                                            <p class="font-medium text-gray-900 dark:text-gray-100">E-mail de Análises</p>
                                            <p class="text-sm text-gray-500 dark:text-gray-400">Receber resumo semanal das análises</p>
                                        </div>
                                        <input
                                            type="checkbox"
                                            v-model="notificationSettings.email_analysis"
                                            :disabled="!authStore.hasPermission('settings.edit')"
                                            class="w-5 h-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500 disabled:opacity-50"
                                        />
                                    </label>

                                    <label :class="[
                                        'flex items-center justify-between p-4 rounded-xl border border-gray-200 dark:border-gray-700 transition-all',
                                        authStore.hasPermission('settings.edit') ? 'cursor-pointer hover:bg-gradient-to-r hover:from-gray-50 dark:hover:from-gray-700 hover:to-transparent' : 'opacity-60 cursor-not-allowed'
                                    ]">
                                        <div>
                                            <p class="font-medium text-gray-900 dark:text-gray-100">Alertas de Estoque</p>
                                            <p class="text-sm text-gray-500 dark:text-gray-400">Notificar quando produtos estiverem com estoque baixo</p>
                                        </div>
                                        <input
                                            type="checkbox"
                                            v-model="notificationSettings.stock_alerts"
                                            :disabled="!authStore.hasPermission('settings.edit')"
                                            class="w-5 h-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500 disabled:opacity-50"
                                        />
                                    </label>

                                    <label :class="[
                                        'flex items-center justify-between p-4 rounded-xl border border-gray-200 dark:border-gray-700 transition-all',
                                        authStore.hasPermission('settings.edit') ? 'cursor-pointer hover:bg-gradient-to-r hover:from-gray-50 dark:hover:from-gray-700 hover:to-transparent' : 'opacity-60 cursor-not-allowed'
                                    ]">
                                        <div>
                                            <p class="font-medium text-gray-900 dark:text-gray-100">Novos Pedidos</p>
                                            <p class="text-sm text-gray-500 dark:text-gray-400">Receber notificação a cada novo pedido</p>
                                        </div>
                                        <input
                                            type="checkbox"
                                            v-model="notificationSettings.new_orders"
                                            :disabled="!authStore.hasPermission('settings.edit')"
                                            class="w-5 h-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500 disabled:opacity-50"
                                        />
                                    </label>

                                    <label :class="[
                                        'flex items-center justify-between p-4 rounded-xl border border-gray-200 dark:border-gray-700 transition-all',
                                        authStore.hasPermission('settings.edit') ? 'cursor-pointer hover:bg-gradient-to-r hover:from-gray-50 dark:hover:from-gray-700 hover:to-transparent' : 'opacity-60 cursor-not-allowed'
                                    ]">
                                        <div>
                                            <p class="font-medium text-gray-900 dark:text-gray-100">Atualizações do Sistema</p>
                                            <p class="text-sm text-gray-500 dark:text-gray-400">Novidades e melhorias da plataforma</p>
                                        </div>
                                        <input
                                            type="checkbox"
                                            v-model="notificationSettings.system_updates"
                                            :disabled="!authStore.hasPermission('settings.edit')"
                                            class="w-5 h-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500 disabled:opacity-50"
                                        />
                                    </label>
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
                            </div>
                        </BaseCard>

                        <!-- Store Information Tab -->
                        <BaseCard v-if="activeTab === 'store'" padding="lg">
                            <div class="max-w-xl">
                                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-6 flex items-center gap-2">
                                    <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-green-500 to-emerald-600 flex items-center justify-center">
                                        <BuildingStorefrontIcon class="w-4 h-4 text-white" />
                                    </div>
                                    Informações da Loja
                                </h2>

                                <!-- Loading State -->
                                <div v-if="isLoadingStore" class="flex items-center justify-center py-12">
                                    <LoadingSpinner size="lg" />
                                </div>

                                <!-- No Store Connected -->
                                <div v-else-if="!storeInfo" class="text-center py-12">
                                    <div class="w-16 h-16 rounded-2xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center mx-auto mb-4">
                                        <BuildingStorefrontIcon class="w-8 h-8 text-gray-400" />
                                    </div>
                                    <p class="text-gray-500 dark:text-gray-400 mb-4">
                                        Nenhuma loja conectada
                                    </p>
                                    <router-link
                                        :to="{ name: 'integrations' }"
                                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300"
                                    >
                                        Conectar Loja
                                    </router-link>
                                </div>

                                <!-- Store Information Display -->
                                <div v-else class="space-y-6">
                                    <p class="text-gray-500 dark:text-gray-400 text-sm">
                                        Informações básicas da sua loja conectada. Estes dados são sincronizados automaticamente da plataforma.
                                    </p>

                                    <div class="space-y-4">
                                        <!-- Store Name -->
                                        <div class="p-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                                                Nome da Loja
                                            </label>
                                            <p class="text-gray-900 dark:text-gray-100 font-medium">
                                                {{ storeInfo.name || '-' }}
                                            </p>
                                        </div>

                                        <!-- Store Domain -->
                                        <div class="p-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                                                Domínio
                                            </label>
                                            <p class="text-gray-900 dark:text-gray-100 font-medium">
                                                {{ storeInfo.domain || '-' }}
                                            </p>
                                        </div>

                                        <!-- Store Email -->
                                        <div class="p-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                                                E-mail da Loja
                                            </label>
                                            <p class="text-gray-900 dark:text-gray-100 font-medium">
                                                {{ storeInfo.email || '-' }}
                                            </p>
                                        </div>

                                        <!-- Platform -->
                                        <div class="p-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                                                Plataforma
                                            </label>
                                            <p class="text-gray-900 dark:text-gray-100 font-medium capitalize">
                                                {{ storeInfo.platform || '-' }}
                                            </p>
                                        </div>

                                        <!-- Last Sync -->
                                        <div class="p-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                                                Última Sincronização
                                            </label>
                                            <p class="text-gray-900 dark:text-gray-100 font-medium">
                                                {{ storeInfo.last_sync_at ? new Date(storeInfo.last_sync_at).toLocaleString('pt-BR') : 'Nunca' }}
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Action Buttons -->
                                    <div class="flex flex-col sm:flex-row gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                                        <router-link
                                            :to="{ name: 'settings-store-info' }"
                                            class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-primary-50 dark:bg-primary-900/30 text-primary-600 dark:text-primary-400 font-medium hover:bg-primary-100 dark:hover:bg-primary-900/50 transition-colors"
                                        >
                                            <Cog6ToothIcon class="w-5 h-5" />
                                            Configurar Loja
                                        </router-link>
                                        <router-link
                                            :to="{ name: 'integrations' }"
                                            class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 font-medium hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors"
                                        >
                                            Gerenciar Integrações
                                        </router-link>
                                    </div>
                                </div>
                            </div>
                        </BaseCard>

                        <!-- Tracking & Analytics Tab -->
                        <BaseCard v-if="activeTab === 'tracking'" padding="lg">
                            <div class="max-w-4xl">
                                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-6 flex items-center gap-2">
                                    <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-cyan-500 to-blue-600 flex items-center justify-center">
                                        <SignalIcon class="w-4 h-4 text-white" />
                                    </div>
                                    Integrações de Tracking
                                </h2>

                                <!-- Loading State -->
                                <div v-if="isLoadingTracking" class="flex items-center justify-center py-12">
                                    <LoadingSpinner size="lg" />
                                </div>

                                <template v-else>
                                    <!-- Info Banner -->
                                    <div class="mb-6 p-4 rounded-xl bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800">
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
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
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
                                                        :disabled="!authStore.hasPermission('integrations.manage')"
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
                                                    :disabled="!authStore.hasPermission('integrations.manage')"
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
                                                        :disabled="!authStore.hasPermission('integrations.manage')"
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
                                                    :disabled="!authStore.hasPermission('integrations.manage')"
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
                                                        :disabled="!authStore.hasPermission('integrations.manage')"
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
                                                    :disabled="!authStore.hasPermission('integrations.manage')"
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
                                                        :disabled="!authStore.hasPermission('integrations.manage')"
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
                                                    :disabled="!authStore.hasPermission('integrations.manage')"
                                                />
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Action Button -->
                                    <BaseButton
                                        v-if="authStore.hasPermission('integrations.manage')"
                                        @click="saveTrackingSettings"
                                        :loading="isSavingTracking"
                                    >
                                        <CheckCircleIcon class="w-5 h-5" />
                                        Salvar Configurações de Tracking
                                    </BaseButton>
                                    <p v-else class="text-sm text-gray-500 dark:text-gray-400 italic">
                                        Você não possui permissão para editar as configurações de tracking.
                                    </p>
                                </template>
                            </div>
                        </BaseCard>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
