<script setup>
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/authStore';
import { useNotificationStore } from '../../stores/notificationStore';
import BaseCard from '../../components/common/BaseCard.vue';
import BaseButton from '../../components/common/BaseButton.vue';
import BaseInput from '../../components/common/BaseInput.vue';
import { LockClosedIcon, ShieldExclamationIcon } from '@heroicons/vue/24/outline';

const router = useRouter();
const authStore = useAuthStore();
const notificationStore = useNotificationStore();

const isLoading = ref(false);

const form = reactive({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const errors = reactive({
    current_password: '',
    password: '',
    password_confirmation: '',
});

function validateForm() {
    let valid = true;
    clearErrors();

    if (!form.current_password) {
        errors.current_password = 'A senha atual é obrigatória';
        valid = false;
    }

    if (!form.password) {
        errors.password = 'A nova senha é obrigatória';
        valid = false;
    } else if (form.password.length < 8) {
        errors.password = 'A senha deve ter pelo menos 8 caracteres';
        valid = false;
    }

    if (form.password !== form.password_confirmation) {
        errors.password_confirmation = 'As senhas não conferem';
        valid = false;
    }

    return valid;
}

function clearErrors() {
    Object.keys(errors).forEach(key => {
        errors[key] = '';
    });
}

async function handleSubmit() {
    if (!validateForm()) return;

    isLoading.value = true;

    const result = await authStore.updatePassword(form);

    isLoading.value = false;

    if (result.success) {
        notificationStore.success('Senha alterada com sucesso!');

        // Redireciona para o dashboard após trocar a senha
        const redirect = router.currentRoute.value.query.redirect;
        router.push(redirect || { name: 'dashboard' });
    } else {
        if (result.errors) {
            Object.keys(result.errors).forEach(key => {
                if (errors[key] !== undefined) {
                    errors[key] = result.errors[key][0];
                }
            });
        }
        notificationStore.error(result.error?.message || 'Erro ao alterar senha');
    }
}

async function handleLogout() {
    await authStore.logoutFromServer();
    router.push({ name: 'login' });
}
</script>

<template>
    <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-slate-900 via-primary-950 to-secondary-950 px-4 py-12">
        <!-- Background Elements -->
        <div class="absolute inset-0 overflow-hidden">
            <div class="absolute -top-40 -right-40 w-80 h-80 bg-primary-500/20 rounded-full blur-3xl"></div>
            <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-secondary-500/20 rounded-full blur-3xl"></div>
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-96 h-96 bg-accent-500/10 rounded-full blur-3xl"></div>
            <div class="absolute inset-0" style="background-image: radial-gradient(rgba(255,255,255,0.03) 1px, transparent 1px); background-size: 40px 40px;"></div>
        </div>

        <div class="relative z-10 w-full max-w-md">
            <!-- Alert Banner -->
            <div class="mb-6 p-4 rounded-xl bg-amber-500/20 border border-amber-500/30 backdrop-blur-sm">
                <div class="flex items-start gap-3">
                    <ShieldExclamationIcon class="w-6 h-6 text-amber-400 flex-shrink-0 mt-0.5" />
                    <div>
                        <h3 class="font-semibold text-amber-200">Troca de Senha Obrigatória</h3>
                        <p class="text-sm text-amber-200/80 mt-1">
                            Por motivos de segurança, você precisa alterar sua senha antes de continuar.
                        </p>
                    </div>
                </div>
            </div>

            <BaseCard padding="lg" class="backdrop-blur-sm bg-white/95 dark:bg-gray-800/95">
                <div class="text-center mb-8">
                    <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-purple-500 to-pink-600 flex items-center justify-center mx-auto mb-4 shadow-lg shadow-purple-500/30">
                        <LockClosedIcon class="w-8 h-8 text-white" />
                    </div>
                    <h1 class="text-2xl font-display font-bold text-gray-900 dark:text-gray-100">
                        Alterar Senha
                    </h1>
                    <p class="text-gray-500 dark:text-gray-400 mt-2">
                        Crie uma nova senha segura para sua conta
                    </p>
                </div>

                <form @submit.prevent="handleSubmit" class="space-y-5">
                    <BaseInput
                        v-model="form.current_password"
                        type="password"
                        label="Senha Atual"
                        placeholder="Digite sua senha atual"
                        :error="errors.current_password"
                        autocomplete="current-password"
                    />

                    <BaseInput
                        v-model="form.password"
                        type="password"
                        label="Nova Senha"
                        placeholder="Digite sua nova senha"
                        hint="Mínimo de 8 caracteres"
                        :error="errors.password"
                        autocomplete="new-password"
                    />

                    <BaseInput
                        v-model="form.password_confirmation"
                        type="password"
                        label="Confirmar Nova Senha"
                        placeholder="Repita a nova senha"
                        :error="errors.password_confirmation"
                        autocomplete="new-password"
                    />

                    <BaseButton
                        type="submit"
                        :loading="isLoading"
                        class="w-full"
                    >
                        Alterar Senha
                    </BaseButton>
                </form>

                <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700 text-center">
                    <button
                        @click="handleLogout"
                        class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 transition-colors"
                    >
                        Sair da conta
                    </button>
                </div>
            </BaseCard>
        </div>
    </div>
</template>
