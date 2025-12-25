<script setup>
import { ref, reactive } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useAuthStore } from '../../stores/authStore';
import { useNotificationStore } from '../../stores/notificationStore';
import BaseButton from '../../components/common/BaseButton.vue';
import BaseInput from '../../components/common/BaseInput.vue';
import { EnvelopeIcon, LockClosedIcon, SparklesIcon } from '@heroicons/vue/24/outline';

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();
const notificationStore = useNotificationStore();

const form = reactive({
    email: '',
    password: '',
    remember: false,
});

const errors = reactive({
    email: '',
    password: '',
});

const isLoading = ref(false);

async function handleSubmit() {
    clearErrors();
    
    if (!validateForm()) return;
    
    isLoading.value = true;
    
    const result = await authStore.login({
        email: form.email,
        password: form.password,
        remember: form.remember,
    });
    
    isLoading.value = false;
    
    if (result.success) {
        notificationStore.success('Login realizado com sucesso!');
        const redirect = route.query.redirect || '/';
        router.push(redirect);
    } else {
        if (result.errors) {
            Object.keys(result.errors).forEach(key => {
                if (errors[key] !== undefined) {
                    errors[key] = result.errors[key][0];
                }
            });
        }
        notificationStore.error(result.message);
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
    }
    
    return valid;
}

function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

function clearErrors() {
    errors.email = '';
    errors.password = '';
}
</script>

<template>
    <div class="min-h-screen flex">
        <!-- Left Side - Form -->
        <div class="flex-1 flex items-center justify-center p-8">
            <div class="w-full max-w-md">
                <!-- Logo -->
                <div class="flex items-center gap-3 mb-8">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-primary-500 to-secondary-600 flex items-center justify-center">
                        <SparklesIcon class="w-7 h-7 text-white" />
                    </div>
                    <span class="font-display font-bold text-2xl text-gray-900">Ecommpilot</span>
                </div>

                <!-- Header -->
                <div class="mb-8">
                    <h1 class="text-3xl font-display font-bold text-gray-900 mb-2">
                        Acesso à Plataforma
                    </h1>
                    <p class="text-gray-500">
                        Entre com suas credenciais para continuar
                    </p>
                </div>

                <!-- Form -->
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
                        label="Senha"
                        placeholder="••••••••"
                        :icon="LockClosedIcon"
                        :error="errors.password"
                        required
                    />

                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input
                                v-model="form.remember"
                                type="checkbox"
                                class="w-4 h-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                            />
                            <span class="text-sm text-gray-600">Lembrar-me</span>
                        </label>

                        <router-link
                            :to="{ name: 'forgot-password' }"
                            class="text-sm text-primary-600 hover:text-primary-700 font-medium"
                        >
                            Esqueceu sua senha?
                        </router-link>
                    </div>

                    <BaseButton
                        type="submit"
                        :loading="isLoading"
                        full-width
                        size="lg"
                    >
                        Entrar
                    </BaseButton>
                </form>

                <!-- Register Link -->
                <p class="mt-8 text-center text-gray-500">
                    Não tem uma conta?
                    <router-link
                        :to="{ name: 'register' }"
                        class="text-primary-600 hover:text-primary-700 font-medium"
                    >
                        Criar conta
                    </router-link>
                </p>
            </div>
        </div>

        <!-- Right Side - Visual -->
        <div class="hidden lg:flex flex-1 bg-gradient-to-br from-primary-600 via-primary-700 to-secondary-700 items-center justify-center p-12 relative overflow-hidden">
            <!-- Background Pattern -->
            <div class="absolute inset-0 opacity-10">
                <div class="absolute inset-0" style="background-image: url('data:image/svg+xml,%3Csvg width=&quot;60&quot; height=&quot;60&quot; viewBox=&quot;0 0 60 60&quot; xmlns=&quot;http://www.w3.org/2000/svg&quot;%3E%3Cg fill=&quot;none&quot; fill-rule=&quot;evenodd&quot;%3E%3Cg fill=&quot;%23ffffff&quot; fill-opacity=&quot;1&quot;%3E%3Cpath d=&quot;M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z&quot;/%3E%3C/g%3E%3C/g%3E%3C/svg%3E');"></div>
            </div>
            
            <!-- Content -->
            <div class="relative z-10 text-white text-center max-w-lg">
                <div class="w-20 h-20 rounded-2xl bg-white/10 backdrop-blur-sm flex items-center justify-center mx-auto mb-8">
                    <SparklesIcon class="w-10 h-10" />
                </div>
                <h2 class="text-3xl font-display font-bold mb-4">
                    Inteligência Artificial para seu E-commerce
                </h2>
                <p class="text-white/80 text-lg">
                    Análises inteligentes e sugestões personalizadas para aumentar suas vendas
                </p>
                
                <!-- Features -->
                <div class="mt-12 grid grid-cols-2 gap-6 text-left">
                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 rounded-lg bg-white/10 flex items-center justify-center flex-shrink-0">
                            <span class="text-sm">📊</span>
                        </div>
                        <div>
                            <h4 class="font-medium">Dashboard Inteligente</h4>
                            <p class="text-sm text-white/60">Métricas em tempo real</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 rounded-lg bg-white/10 flex items-center justify-center flex-shrink-0">
                            <span class="text-sm">🤖</span>
                        </div>
                        <div>
                            <h4 class="font-medium">IA Avançada</h4>
                            <p class="text-sm text-white/60">GPT-4 para análises</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 rounded-lg bg-white/10 flex items-center justify-center flex-shrink-0">
                            <span class="text-sm">🔗</span>
                        </div>
                        <div>
                            <h4 class="font-medium">Integração Fácil</h4>
                            <p class="text-sm text-white/60">Nuvemshop e mais</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 rounded-lg bg-white/10 flex items-center justify-center flex-shrink-0">
                            <span class="text-sm">💡</span>
                        </div>
                        <div>
                            <h4 class="font-medium">Sugestões Práticas</h4>
                            <p class="text-sm text-white/60">Ações para crescer</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

