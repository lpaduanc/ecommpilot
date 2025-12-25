<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../../services/api';
import BaseCard from '../../components/common/BaseCard.vue';
import BaseButton from '../../components/common/BaseButton.vue';
import LoadingSpinner from '../../components/common/LoadingSpinner.vue';
import {
    ArrowLeftIcon,
    UserCircleIcon,
    EnvelopeIcon,
    PhoneIcon,
    CalendarIcon,
    SparklesIcon,
    LinkIcon,
} from '@heroicons/vue/24/outline';

const route = useRoute();
const router = useRouter();

const client = ref(null);
const isLoading = ref(true);

async function fetchClient() {
    isLoading.value = true;
    try {
        const response = await api.get(`/admin/clients/${route.params.id}`);
        client.value = response.data;
    } catch {
        // Mock data
        client.value = {
            id: route.params.id,
            name: 'João Silva',
            email: 'joao@example.com',
            phone: '(11) 99999-9999',
            role: 'client',
            is_active: true,
            ai_credits: 10,
            created_at: '2024-01-15T10:00:00Z',
            last_login_at: '2024-03-10T14:30:00Z',
            store: {
                name: 'Loja do João',
                domain: 'lojadojoao.nuvemshop.com.br',
                sync_status: 'completed',
            },
        };
    } finally {
        isLoading.value = false;
    }
}

function goBack() {
    router.push({ name: 'admin-clients' });
}

function formatDate(date) {
    if (!date) return 'Nunca';
    return new Date(date).toLocaleString('pt-BR');
}

onMounted(() => {
    fetchClient();
});
</script>

<template>
    <div class="space-y-6">
        <!-- Back Button -->
        <BaseButton variant="ghost" @click="goBack">
            <ArrowLeftIcon class="w-4 h-4" />
            Voltar
        </BaseButton>

        <!-- Loading -->
        <div v-if="isLoading" class="flex items-center justify-center py-20">
            <LoadingSpinner size="xl" class="text-primary-500" />
        </div>

        <template v-else-if="client">
            <!-- Client Header -->
            <BaseCard padding="lg">
                <div class="flex items-start gap-6">
                    <div class="w-20 h-20 rounded-2xl bg-gradient-to-br from-primary-400 to-primary-600 flex items-center justify-center text-white text-2xl font-bold">
                        {{ client.name.charAt(0).toUpperCase() }}
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center gap-3 mb-2">
                            <h1 class="text-2xl font-display font-bold text-gray-900">{{ client.name }}</h1>
                            <span :class="[
                                'badge',
                                client.is_active ? 'badge-success' : 'badge-danger'
                            ]">
                                {{ client.is_active ? 'Ativo' : 'Inativo' }}
                            </span>
                        </div>
                        <div class="flex items-center gap-6 text-sm text-gray-500">
                            <span class="flex items-center gap-2">
                                <EnvelopeIcon class="w-4 h-4" />
                                {{ client.email }}
                            </span>
                            <span v-if="client.phone" class="flex items-center gap-2">
                                <PhoneIcon class="w-4 h-4" />
                                {{ client.phone }}
                            </span>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="flex items-center gap-2 text-lg">
                            <SparklesIcon class="w-5 h-5 text-primary-500" />
                            <span class="font-bold text-gray-900">{{ client.ai_credits }}</span>
                            <span class="text-gray-500">créditos</span>
                        </div>
                    </div>
                </div>
            </BaseCard>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Account Info -->
                <BaseCard padding="normal">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                        <UserCircleIcon class="w-5 h-5 text-gray-400" />
                        Informações da Conta
                    </h3>
                    <div class="space-y-4">
                        <div class="flex justify-between">
                            <span class="text-gray-500">Perfil</span>
                            <span class="font-medium text-gray-900 capitalize">{{ client.role }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Cadastro</span>
                            <span class="font-medium text-gray-900">{{ formatDate(client.created_at) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Último Acesso</span>
                            <span class="font-medium text-gray-900">{{ formatDate(client.last_login_at) }}</span>
                        </div>
                    </div>
                </BaseCard>

                <!-- Store Info -->
                <BaseCard padding="normal">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                        <LinkIcon class="w-5 h-5 text-gray-400" />
                        Loja Conectada
                    </h3>
                    <div v-if="client.store" class="space-y-4">
                        <div class="flex justify-between">
                            <span class="text-gray-500">Nome</span>
                            <span class="font-medium text-gray-900">{{ client.store.name }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Domínio</span>
                            <span class="font-medium text-gray-900">{{ client.store.domain }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Status Sync</span>
                            <span class="badge badge-success">{{ client.store.sync_status }}</span>
                        </div>
                    </div>
                    <div v-else class="text-center py-8 text-gray-400">
                        <LinkIcon class="w-12 h-12 mx-auto mb-2 opacity-50" />
                        <p>Nenhuma loja conectada</p>
                    </div>
                </BaseCard>
            </div>

            <!-- Actions -->
            <BaseCard padding="normal">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Ações</h3>
                <div class="flex items-center gap-4">
                    <BaseButton variant="secondary">
                        Adicionar Créditos
                    </BaseButton>
                    <BaseButton variant="secondary">
                        {{ client.is_active ? 'Desativar Conta' : 'Ativar Conta' }}
                    </BaseButton>
                    <BaseButton variant="secondary">
                        Resetar Senha
                    </BaseButton>
                </div>
            </BaseCard>
        </template>
    </div>
</template>

