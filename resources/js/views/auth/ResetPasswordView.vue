<script setup>
import { ref, reactive } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useAuthStore } from '../../stores/authStore';
import { useNotificationStore } from '../../stores/notificationStore';
import BaseButton from '../../components/common/BaseButton.vue';
import BaseInput from '../../components/common/BaseInput.vue';
import { LockClosedIcon, SparklesIcon, EnvelopeIcon, ArrowLeftIcon } from '@heroicons/vue/24/outline';

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();
const notificationStore = useNotificationStore();

const form = reactive({
    email: route.query.email || '',
    token: route.params.token,
    password: '',
    password_confirmation: '',
});

const errors = reactive({
    email: '',
    password: '',
    password_confirmation: '',
});

const isLoading = ref(false);

async function handleSubmit() {
    clearErrors();
    
    if (!validateForm()) return;
    
    isLoading.value = true;
    
    const result = await authStore.resetPassword(form);
    
    isLoading.value = false;
    
    if (result.success) {
        notificationStore.success('Senha redefinida com sucesso!');
        router.push({ name: 'login' });
    } else {
        // Tratar erros de validação
        if (result.error.errors) {
            Object.keys(result.error.errors).forEach(key => {
                if (errors[key] !== undefined) {
                    errors[key] = result.error.errors[key][0];
                }
            });
        }

        // Mostrar mensagem de erro apropriada
        const errorMessage = result.error.message || 'Erro ao redefinir senha';

        // Se o token expirou ou é inválido, informar claramente
        if (result.error.status === 422 || result.error.status === 400) {
            notificationStore.error(errorMessage + '. O link pode ter expirado. Solicite um novo link de recuperação.');
        } else {
            notificationStore.error(errorMessage);
        }
    }
}

function validateForm() {
    let valid = true;

    if (!form.email) {
        errors.email = 'O e-mail é obrigatório';
        valid = false;
    } else if (!isValidEmail(form.email)) {
        errors.email = 'Digite um e-mail válido';
        valid = false;
    }

    if (!form.password) {
        errors.password = 'A senha é obrigatória';
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

function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

function clearErrors() {
    errors.email = '';
    errors.password = '';
    errors.password_confirmation = '';
}
</script>

<template>
    <div class="min-h-screen flex items-center justify-center p-8 bg-gradient-to-br from-gray-50 to-gray-100">
        <div class="w-full max-w-md">
            <!-- Card -->
            <div class="bg-white rounded-2xl shadow-xl p-8">
                <!-- Logo -->
                <div class="flex items-center justify-center gap-3 mb-8">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-primary-500 to-secondary-600 flex items-center justify-center">
                        <SparklesIcon class="w-7 h-7 text-white" />
                    </div>
                    <span class="font-display font-bold text-2xl text-gray-900">Ecommpilot</span>
                </div>

                <div class="text-center mb-8">
                    <h1 class="text-2xl font-display font-bold text-gray-900 mb-2">
                        Redefinir Senha
                    </h1>
                    <p class="text-gray-500">
                        Digite sua nova senha abaixo
                    </p>
                </div>

                <form @submit.prevent="handleSubmit" class="space-y-5">
                    <BaseInput
                        v-model="form.email"
                        type="email"
                        label="E-mail"
                        placeholder="seu@email.com"
                        :icon="EnvelopeIcon"
                        :error="errors.email"
                        required
                    />

                    <BaseInput
                        v-model="form.password"
                        type="password"
                        label="Nova Senha"
                        placeholder="••••••••"
                        :icon="LockClosedIcon"
                        :error="errors.password"
                        hint="Mínimo de 8 caracteres"
                        required
                    />

                    <BaseInput
                        v-model="form.password_confirmation"
                        type="password"
                        label="Confirmar Nova Senha"
                        placeholder="••••••••"
                        :icon="LockClosedIcon"
                        :error="errors.password_confirmation"
                        required
                    />

                    <BaseButton
                        type="submit"
                        :loading="isLoading"
                        full-width
                        size="lg"
                    >
                        Redefinir Senha
                    </BaseButton>
                </form>

                <router-link
                    :to="{ name: 'login' }"
                    class="flex items-center justify-center gap-2 mt-6 text-gray-500 hover:text-gray-700 transition-colors"
                >
                    <ArrowLeftIcon class="w-4 h-4" />
                    Voltar para o login
                </router-link>
            </div>
        </div>
    </div>
</template>

