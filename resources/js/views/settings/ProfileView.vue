<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useAuthStore } from '../../stores/authStore';
import { useNotificationStore } from '../../stores/notificationStore';
import BaseCard from '../../components/common/BaseCard.vue';
import BaseButton from '../../components/common/BaseButton.vue';
import BaseInput from '../../components/common/BaseInput.vue';
import { UserCircleIcon, LockClosedIcon } from '@heroicons/vue/24/outline';

const authStore = useAuthStore();
const notificationStore = useNotificationStore();

const isLoadingProfile = ref(false);
const isLoadingPassword = ref(false);

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

function loadUserData() {
    profileForm.name = authStore.user?.name || '';
    profileForm.email = authStore.user?.email || '';
    profileForm.phone = authStore.user?.phone || '';
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

function clearProfileErrors() {
    Object.keys(profileErrors).forEach(key => {
        profileErrors[key] = '';
    });
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
                    <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-blue-400 to-indigo-500 flex items-center justify-center shadow-lg shadow-blue-500/30">
                        <UserCircleIcon class="w-7 h-7 text-white" />
                    </div>
                    <div>
                        <h1 class="text-3xl lg:text-4xl font-display font-bold text-white">
                            Meu Perfil
                        </h1>
                        <p class="text-primary-200/80 text-sm lg:text-base">
                            Gerencie suas informações pessoais e segurança
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="px-8 py-8 bg-gradient-to-b from-gray-100 to-gray-50 dark:from-gray-900 dark:to-gray-950 min-h-[calc(100vh-200px)]">
            <div class="max-w-4xl mx-auto space-y-8">
            <!-- Profile Section -->
            <BaseCard padding="lg">
                <div class="mb-6">
                    <div class="flex items-center gap-2 mb-2">
                        <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center">
                            <UserCircleIcon class="w-4 h-4 text-white" />
                        </div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            Informações do Perfil
                        </h2>
                    </div>
                    <p class="text-gray-500 dark:text-gray-400 text-sm">
                        Atualize suas informações pessoais
                    </p>
                </div>

                <form @submit.prevent="updateProfile" class="space-y-5">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <BaseInput
                            v-model="profileForm.name"
                            type="text"
                            label="Nome"
                            placeholder="Seu nome completo"
                            :error="profileErrors.name"
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
                    </div>

                    <BaseInput
                        v-model="profileForm.email"
                        type="email"
                        label="E-mail"
                        placeholder="seu@email.com"
                        :error="profileErrors.email"
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
            </BaseCard>

            <!-- Password Section -->
            <BaseCard padding="lg">
                <div class="mb-6">
                    <div class="flex items-center gap-2 mb-2">
                        <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-purple-500 to-pink-600 flex items-center justify-center">
                            <LockClosedIcon class="w-4 h-4 text-white" />
                        </div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            Alterar Senha
                        </h2>
                    </div>
                    <p class="text-gray-500 dark:text-gray-400 text-sm">
                        Mantenha sua conta segura com uma senha forte
                    </p>
                </div>

                <form @submit.prevent="updatePassword" class="space-y-5">
                    <BaseInput
                        v-model="passwordForm.current_password"
                        type="password"
                        label="Senha Atual"
                        placeholder="••••••••"
                        :error="passwordErrors.current_password"
                        :disabled="!authStore.hasPermission('settings.edit')"
                    />

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
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
                    </div>

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
            </BaseCard>
            </div>
        </div>
    </div>
</template>
