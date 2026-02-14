<script setup>
import { ref, reactive } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/authStore';
import { useNotificationStore } from '../../stores/notificationStore';
import BaseButton from '../../components/common/BaseButton.vue';
import BaseInput from '../../components/common/BaseInput.vue';
import { UserIcon, EnvelopeIcon, LockClosedIcon, PhoneIcon, SparklesIcon } from '@heroicons/vue/24/outline';

const router = useRouter();
const authStore = useAuthStore();
const notificationStore = useNotificationStore();

const form = reactive({
    name: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: '',
});

const errors = reactive({
    name: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: '',
});

const isLoading = ref(false);

async function handleSubmit() {
    clearErrors();
    
    if (!validateForm()) return;
    
    isLoading.value = true;
    
    const result = await authStore.register(form);
    
    isLoading.value = false;
    
    if (result.success) {
        notificationStore.success('Conta criada com sucesso!');
        router.push({ name: 'dashboard' });
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
    
    if (!form.name) {
        errors.name = 'O nome é obrigatório';
        valid = false;
    }
    
    if (!form.email) {
        errors.email = 'O e-mail é obrigatório';
        valid = false;
    } else if (!isValidEmail(form.email)) {
        errors.email = 'Digite um e-mail válido';
        valid = false;
    }
    
    if (!form.phone) {
        errors.phone = 'O telefone é obrigatório';
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
    Object.keys(errors).forEach(key => {
        errors[key] = '';
    });
}
</script>

<template>
    <div class="min-h-screen flex">
        <!-- Left Side - Visual -->
        <div class="hidden lg:flex flex-1 bg-gradient-to-br from-secondary-600 via-secondary-700 to-primary-700 items-center justify-center p-12 relative overflow-hidden">
            <!-- Background Pattern -->
            <div class="absolute inset-0 opacity-10">
                <div class="absolute inset-0" style="background-image: url('data:image/svg+xml,%3Csvg width=&quot;60&quot; height=&quot;60&quot; viewBox=&quot;0 0 60 60&quot; xmlns=&quot;http://www.w3.org/2000/svg&quot;%3E%3Cg fill=&quot;none&quot; fill-rule=&quot;evenodd&quot;%3E%3Cg fill=&quot;%23ffffff&quot; fill-opacity=&quot;1&quot;%3E%3Cpath d=&quot;M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z&quot;/%3E%3C/g%3E%3C/g%3E%3C/svg%3E');"></div>
            </div>
            
            <!-- Content -->
            <div class="relative z-10 text-white text-center max-w-lg">
                <div class="w-20 h-20 rounded-2xl bg-white/10 backdrop-blur-sm flex items-center justify-center mx-auto mb-8">
                    <SparklesIcon class="w-10 h-10" />
                </div>
                <h2 class="text-2xl lg:text-3xl font-display font-bold mb-4">
                    Comece a Crescer Hoje
                </h2>
                <p class="text-white/80 text-lg mb-8">
                    Crie sua conta e tenha acesso às melhores ferramentas de IA para e-commerce
                </p>
                
                <!-- Benefits -->
                <div class="space-y-4 text-left">
                    <div class="flex items-center gap-3 bg-white/10 rounded-xl p-4">
                        <span class="text-2xl">✨</span>
                        <div>
                            <h4 class="font-medium">Análises Ilimitadas</h4>
                            <p class="text-sm text-white/60">Entenda seu negócio profundamente</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 bg-white/10 rounded-xl p-4">
                        <span class="text-2xl">💬</span>
                        <div>
                            <h4 class="font-medium">Chat com IA</h4>
                            <p class="text-sm text-white/60">Tire dúvidas em tempo real</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 bg-white/10 rounded-xl p-4">
                        <span class="text-2xl">📈</span>
                        <div>
                            <h4 class="font-medium">Aumente suas Vendas</h4>
                            <p class="text-sm text-white/60">Sugestões práticas e efetivas</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Side - Form -->
        <div class="flex-1 flex items-center justify-center p-4 sm:p-8">
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
                    <h1 class="text-2xl sm:text-3xl font-display font-bold text-gray-900 mb-2">
                        Criar Conta
                    </h1>
                    <p class="text-gray-500">
                        Preencha os dados abaixo para começar
                    </p>
                </div>

                <!-- Form -->
                <form @submit.prevent="handleSubmit" class="space-y-4">
                    <BaseInput
                        v-model="form.name"
                        type="text"
                        label="Nome"
                        placeholder="Seu nome completo"
                        :icon="UserIcon"
                        :error="errors.name"
                        required
                    />

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
                        v-model="form.phone"
                        type="tel"
                        label="Telefone"
                        placeholder="(00) 00000-0000"
                        :icon="PhoneIcon"
                        :error="errors.phone"
                        required
                    />

                    <BaseInput
                        v-model="form.password"
                        type="password"
                        label="Senha"
                        placeholder="••••••••"
                        :icon="LockClosedIcon"
                        :error="errors.password"
                        hint="Mínimo de 8 caracteres"
                        required
                    />

                    <BaseInput
                        v-model="form.password_confirmation"
                        type="password"
                        label="Confirmar Senha"
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
                        class="mt-6"
                    >
                        Criar Conta
                    </BaseButton>
                </form>

                <!-- Login Link -->
                <p class="mt-8 text-center text-gray-500">
                    Já tem uma conta?
                    <router-link
                        :to="{ name: 'login' }"
                        class="text-primary-600 hover:text-primary-700 font-medium"
                    >
                        Entrar
                    </router-link>
                </p>
            </div>
        </div>
    </div>
</template>

