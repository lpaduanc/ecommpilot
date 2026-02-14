<script setup>
import { ref, reactive } from 'vue';
import { useAuthStore } from '../../stores/authStore';
import { useNotificationStore } from '../../stores/notificationStore';
import BaseButton from '../../components/common/BaseButton.vue';
import BaseInput from '../../components/common/BaseInput.vue';
import { EnvelopeIcon, SparklesIcon, ArrowLeftIcon, CheckCircleIcon } from '@heroicons/vue/24/outline';

const authStore = useAuthStore();
const notificationStore = useNotificationStore();

const form = reactive({
    email: '',
});

const errors = reactive({
    email: '',
});

const isLoading = ref(false);
const emailSent = ref(false);

async function handleSubmit() {
    clearErrors();
    
    if (!validateForm()) return;
    
    isLoading.value = true;
    
    const result = await authStore.forgotPassword(form.email);
    
    isLoading.value = false;
    
    if (result.success) {
        emailSent.value = true;
    } else {
        // Mostrar erro genérico para não revelar se e-mail existe
        if (result.error.status === 422) {
            // Erros de validação
            if (result.error.errors?.email) {
                errors.email = result.error.errors.email[0];
            }
        } else {
            notificationStore.error(result.error.message || 'Erro ao enviar e-mail de recuperação');
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
    
    return valid;
}

function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

function clearErrors() {
    errors.email = '';
}

function resetForm() {
    emailSent.value = false;
    form.email = '';
}
</script>

<template>
    <div class="min-h-screen flex items-center justify-center p-4 sm:p-8 bg-gradient-to-br from-gray-50 to-gray-100 dark:from-gray-900 dark:to-gray-950">
        <div class="w-full max-w-md">
            <!-- Card -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-5 sm:p-8">
                <!-- Logo -->
                <div class="flex items-center justify-center gap-3 mb-8">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-primary-500 to-secondary-600 flex items-center justify-center">
                        <SparklesIcon class="w-7 h-7 text-white" />
                    </div>
                    <span class="font-display font-bold text-2xl text-gray-900">Ecommpilot</span>
                </div>

                <!-- Success State -->
                <div v-if="emailSent" class="text-center">
                    <div class="w-16 h-16 rounded-full bg-success-100 flex items-center justify-center mx-auto mb-4">
                        <CheckCircleIcon class="w-8 h-8 text-success-600" />
                    </div>
                    <h2 class="text-2xl font-display font-bold text-gray-900 mb-2">
                        E-mail Enviado!
                    </h2>
                    <p class="text-gray-500 mb-6">
                        Se o e-mail informado estiver cadastrado, você receberá as instruções para redefinir sua senha. Verifique também a caixa de spam.
                    </p>
                    <div class="space-y-3">
                        <BaseButton
                            @click="resetForm"
                            variant="secondary"
                            full-width
                        >
                            Enviar novamente
                        </BaseButton>
                        <router-link
                            :to="{ name: 'login' }"
                            class="btn btn-ghost w-full"
                        >
                            <ArrowLeftIcon class="w-4 h-4" />
                            Voltar para o login
                        </router-link>
                    </div>
                </div>

                <!-- Form State -->
                <template v-else>
                    <div class="text-center mb-8">
                        <h1 class="text-2xl font-display font-bold text-gray-900 mb-2">
                            Esqueceu sua senha?
                        </h1>
                        <p class="text-gray-500">
                            Digite seu e-mail e enviaremos um link para redefinir sua senha
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

                        <BaseButton
                            type="submit"
                            :loading="isLoading"
                            full-width
                            size="lg"
                        >
                            Enviar Link
                        </BaseButton>
                    </form>

                    <router-link
                        :to="{ name: 'login' }"
                        class="flex items-center justify-center gap-2 mt-6 text-gray-500 hover:text-gray-700 transition-colors"
                    >
                        <ArrowLeftIcon class="w-4 h-4" />
                        Voltar para o login
                    </router-link>
                </template>
            </div>
        </div>
    </div>
</template>

