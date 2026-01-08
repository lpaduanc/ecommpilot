<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useAuthStore } from '../stores/authStore';
import { useNotificationStore } from '../stores/notificationStore';
import api from '../services/api';
import BaseCard from '../components/common/BaseCard.vue';
import BaseButton from '../components/common/BaseButton.vue';
import BaseInput from '../components/common/BaseInput.vue';
import {
    Cog6ToothIcon,
    UserCircleIcon,
    LockClosedIcon,
    BellIcon,
} from '@heroicons/vue/24/outline';

const authStore = useAuthStore();
const notificationStore = useNotificationStore();

const activeTab = ref('profile');
const isLoadingProfile = ref(false);
const isLoadingPassword = ref(false);
const isLoadingNotifications = ref(false);
const isSavingNotifications = ref(false);

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

const tabs = [
    { id: 'profile', name: 'Perfil', icon: UserCircleIcon, gradient: 'from-blue-500 to-indigo-600' },
    { id: 'password', name: 'Senha', icon: LockClosedIcon, gradient: 'from-purple-500 to-pink-600' },
    { id: 'notifications', name: 'Notificações', icon: BellIcon, gradient: 'from-amber-500 to-orange-600' },
];

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

onMounted(() => {
    loadUserData();
    loadNotificationSettings();
});
</script>

<template>
    <div class="min-h-screen -m-8 -mt-8">
        <!-- Hero Header with Gradient -->
        <div class="relative overflow-hidden bg-gradient-to-br from-slate-900 via-primary-950 to-secondary-950 px-8 py-12">
            <!-- Animated Background Elements -->
            <div class="absolute inset-0 overflow-hidden">
                <div class="absolute -top-40 -right-40 w-80 h-80 bg-primary-500/20 rounded-full blur-3xl animate-pulse-soft"></div>
                <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-secondary-500/20 rounded-full blur-3xl animate-pulse-soft" style="animation-delay: 1s;"></div>
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
        <div class="px-8 py-8 bg-gradient-to-b from-gray-100 to-gray-50 min-h-[calc(100vh-200px)]">
            <div class="max-w-7xl mx-auto">
                <div class="flex flex-col lg:flex-row gap-8">
                    <!-- Tabs Navigation -->
                    <div class="w-full lg:w-64 flex-shrink-0">
                        <BaseCard padding="sm" class="animate-fade-in">
                            <nav class="space-y-1">
                                <button
                                    v-for="tab in tabs"
                                    :key="tab.id"
                                    @click="activeTab = tab.id"
                                    :class="[
                                        'w-full flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all duration-200',
                                        activeTab === tab.id
                                            ? 'bg-gradient-to-r text-white shadow-lg'
                                            : 'text-gray-600 hover:bg-gray-50',
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
                        <BaseCard v-if="activeTab === 'profile'" padding="lg" class="animate-fade-in animate-delay-100">
                            <div class="max-w-xl">
                                <h2 class="text-lg font-semibold text-gray-900 mb-6 flex items-center gap-2">
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
                                    />

                                    <BaseInput
                                        v-model="profileForm.email"
                                        type="email"
                                        label="E-mail"
                                        placeholder="seu@email.com"
                                        :error="profileErrors.email"
                                    />

                                    <BaseInput
                                        v-model="profileForm.phone"
                                        type="tel"
                                        label="Telefone"
                                        placeholder="(00) 00000-0000"
                                        :error="profileErrors.phone"
                                    />

                                    <BaseButton
                                        type="submit"
                                        :loading="isLoadingProfile"
                                    >
                                        Salvar Alterações
                                    </BaseButton>
                                </form>
                            </div>
                        </BaseCard>

                        <!-- Password Tab -->
                        <BaseCard v-if="activeTab === 'password'" padding="lg" class="animate-fade-in animate-delay-100">
                            <div class="max-w-xl">
                                <h2 class="text-lg font-semibold text-gray-900 mb-6 flex items-center gap-2">
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
                                    />

                                    <BaseInput
                                        v-model="passwordForm.password"
                                        type="password"
                                        label="Nova Senha"
                                        placeholder="••••••••"
                                        hint="Mínimo de 8 caracteres"
                                        :error="passwordErrors.password"
                                    />

                                    <BaseInput
                                        v-model="passwordForm.password_confirmation"
                                        type="password"
                                        label="Confirmar Nova Senha"
                                        placeholder="••••••••"
                                        :error="passwordErrors.password_confirmation"
                                    />

                                    <BaseButton
                                        type="submit"
                                        :loading="isLoadingPassword"
                                    >
                                        Alterar Senha
                                    </BaseButton>
                                </form>
                            </div>
                        </BaseCard>

                        <!-- Notifications Tab -->
                        <BaseCard v-if="activeTab === 'notifications'" padding="lg" class="animate-fade-in animate-delay-100">
                            <div class="max-w-xl">
                                <h2 class="text-lg font-semibold text-gray-900 mb-6 flex items-center gap-2">
                                    <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-amber-500 to-orange-600 flex items-center justify-center">
                                        <BellIcon class="w-4 h-4 text-white" />
                                    </div>
                                    Preferências de Notificação
                                </h2>
                                
                                <div class="space-y-4">
                                    <label class="flex items-center justify-between p-4 rounded-xl border border-gray-200 cursor-pointer hover:bg-gradient-to-r hover:from-gray-50 hover:to-transparent transition-all">
                                        <div>
                                            <p class="font-medium text-gray-900">E-mail de Análises</p>
                                            <p class="text-sm text-gray-500">Receber resumo semanal das análises</p>
                                        </div>
                                        <input
                                            type="checkbox"
                                            v-model="notificationSettings.email_analysis"
                                            class="w-5 h-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                        />
                                    </label>

                                    <label class="flex items-center justify-between p-4 rounded-xl border border-gray-200 cursor-pointer hover:bg-gradient-to-r hover:from-gray-50 hover:to-transparent transition-all">
                                        <div>
                                            <p class="font-medium text-gray-900">Alertas de Estoque</p>
                                            <p class="text-sm text-gray-500">Notificar quando produtos estiverem com estoque baixo</p>
                                        </div>
                                        <input
                                            type="checkbox"
                                            v-model="notificationSettings.stock_alerts"
                                            class="w-5 h-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                        />
                                    </label>

                                    <label class="flex items-center justify-between p-4 rounded-xl border border-gray-200 cursor-pointer hover:bg-gradient-to-r hover:from-gray-50 hover:to-transparent transition-all">
                                        <div>
                                            <p class="font-medium text-gray-900">Novos Pedidos</p>
                                            <p class="text-sm text-gray-500">Receber notificação a cada novo pedido</p>
                                        </div>
                                        <input
                                            type="checkbox"
                                            v-model="notificationSettings.new_orders"
                                            class="w-5 h-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                        />
                                    </label>

                                    <label class="flex items-center justify-between p-4 rounded-xl border border-gray-200 cursor-pointer hover:bg-gradient-to-r hover:from-gray-50 hover:to-transparent transition-all">
                                        <div>
                                            <p class="font-medium text-gray-900">Atualizações do Sistema</p>
                                            <p class="text-sm text-gray-500">Novidades e melhorias da plataforma</p>
                                        </div>
                                        <input
                                            type="checkbox"
                                            v-model="notificationSettings.system_updates"
                                            class="w-5 h-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                        />
                                    </label>
                                </div>

                                <BaseButton class="mt-6" @click="saveNotificationSettings" :loading="isSavingNotifications">
                                    Salvar Preferências
                                </BaseButton>
                            </div>
                        </BaseCard>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
