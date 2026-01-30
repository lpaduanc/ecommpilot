<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useNotificationStore } from '../../stores/notificationStore';
import { useConfirmDialog } from '../../composables/useConfirmDialog';
import api from '../../services/api';
import BaseCard from '../../components/common/BaseCard.vue';
import BaseButton from '../../components/common/BaseButton.vue';
import BaseModal from '../../components/common/BaseModal.vue';
import LoadingSpinner from '../../components/common/LoadingSpinner.vue';
import {
    ArrowLeftIcon,
    UserCircleIcon,
    EnvelopeIcon,
    PhoneIcon,
    CalendarIcon,
    SparklesIcon,
    LinkIcon,
    CreditCardIcon,
    XMarkIcon,
    CheckCircleIcon,
} from '@heroicons/vue/24/outline';

const route = useRoute();
const router = useRouter();
const notificationStore = useNotificationStore();
const { confirm } = useConfirmDialog();

const client = ref(null);
const isLoading = ref(true);
const subscription = ref(null);
const isLoadingSubscription = ref(false);

// Modal e planos
const showPlanModal = ref(false);
const availablePlans = ref([]);
const isLoadingPlans = ref(false);
const isSubmitting = ref(false);
const selectedPlanId = ref(null);

async function fetchClient() {
    isLoading.value = true;
    try {
        const response = await api.get(`/admin/clients/${route.params.id}`);
        client.value = response.data;
        await fetchSubscription();
    } catch {
        notificationStore.error('Erro ao carregar dados do cliente');
    } finally {
        isLoading.value = false;
    }
}

async function fetchSubscription() {
    isLoadingSubscription.value = true;
    try {
        const response = await api.get(`/admin/clients/${route.params.id}/subscription`);
        subscription.value = response.data.subscription;
    } catch (error) {
        // Cliente sem plano
        subscription.value = null;
    } finally {
        isLoadingSubscription.value = false;
    }
}

async function fetchPlans() {
    isLoadingPlans.value = true;
    try {
        const response = await api.get('/admin/plans');
        availablePlans.value = response.data.plans.filter(plan => plan.is_active);
    } catch {
        notificationStore.error('Erro ao carregar planos disponíveis');
    } finally {
        isLoadingPlans.value = false;
    }
}

function openPlanModal() {
    selectedPlanId.value = subscription.value?.plan_id || null;
    showPlanModal.value = true;
    if (availablePlans.value.length === 0) {
        fetchPlans();
    }
}

async function assignPlan() {
    if (!selectedPlanId.value) {
        notificationStore.error('Selecione um plano');
        return;
    }

    isSubmitting.value = true;
    try {
        await api.post(`/admin/plans/${selectedPlanId.value}/assign`, {
            user_uuid: client.value.uuid,
        });
        notificationStore.success('Plano atribuído com sucesso!');
        showPlanModal.value = false;
        await fetchSubscription();
    } catch (error) {
        const message = error.response?.data?.message || 'Erro ao atribuir plano';
        notificationStore.error(message);
    } finally {
        isSubmitting.value = false;
    }
}

async function removePlan() {
    const confirmed = await confirm({
        title: 'Remover Plano',
        message: 'Tem certeza que deseja remover o plano deste cliente?',
        confirmText: 'Remover',
        variant: 'danger',
    });

    if (!confirmed) return;

    isSubmitting.value = true;
    try {
        await api.delete(`/admin/clients/${client.value.uuid}/subscription`);
        notificationStore.success('Plano removido com sucesso!');
        showPlanModal.value = false;
        subscription.value = null;
    } catch (error) {
        const message = error.response?.data?.message || 'Erro ao remover plano';
        notificationStore.error(message);
    } finally {
        isSubmitting.value = false;
    }
}

function goBack() {
    router.push({ name: 'admin-clients' });
}

function formatDate(date) {
    if (!date) return 'Nunca';
    return new Date(date).toLocaleString('pt-BR');
}

function formatPrice(value) {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(value);
}

function formatLimit(value) {
    return value === -1 ? 'Ilimitado' : value.toLocaleString('pt-BR');
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
                            <h1 class="text-2xl font-display font-bold text-gray-900 dark:text-gray-100">{{ client.name }}</h1>
                            <span :class="[
                                'badge',
                                client.is_active ? 'badge-success' : 'badge-danger'
                            ]">
                                {{ client.is_active ? 'Ativo' : 'Inativo' }}
                            </span>
                        </div>
                        <div class="flex items-center gap-6 text-sm text-gray-500 dark:text-gray-400">
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
                </div>
            </BaseCard>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Account Info -->
                <BaseCard padding="normal">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4 flex items-center gap-2">
                        <UserCircleIcon class="w-5 h-5 text-gray-400" />
                        Informações da Conta
                    </h3>
                    <div class="space-y-4">
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Perfil</span>
                            <span class="font-medium text-gray-900 dark:text-gray-100 capitalize">{{ client.role }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Cadastro</span>
                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ formatDate(client.created_at) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Último Acesso</span>
                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ formatDate(client.last_login_at) }}</span>
                        </div>
                    </div>
                </BaseCard>

                <!-- Store Info -->
                <BaseCard padding="normal">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4 flex items-center gap-2">
                        <LinkIcon class="w-5 h-5 text-gray-400" />
                        Lojas Conectadas
                        <span v-if="client.stores?.length" class="text-sm font-normal text-gray-500 dark:text-gray-400">
                            ({{ client.stores.length }})
                        </span>
                    </h3>
                    <div v-if="client.stores?.length" class="space-y-4">
                        <div
                            v-for="store in client.stores"
                            :key="store.id"
                            class="p-3 bg-gray-50 dark:bg-gray-800/50 rounded-lg border border-gray-200 dark:border-gray-700"
                        >
                            <div class="flex items-center justify-between mb-2">
                                <span class="font-medium text-gray-900 dark:text-gray-100">{{ store.name }}</span>
                                <span :class="[
                                    'badge',
                                    store.sync_status === 'completed' ? 'badge-success' :
                                    store.sync_status === 'syncing' ? 'badge-warning' : 'badge-secondary'
                                ]">
                                    {{ store.sync_status }}
                                </span>
                            </div>
                            <div class="text-sm text-gray-500 dark:text-gray-400 space-y-1">
                                <p v-if="store.domain">{{ store.domain }}</p>
                                <div class="flex items-center gap-4 text-xs">
                                    <span>{{ store.products_count ?? 0 }} produtos</span>
                                    <span>{{ store.orders_count ?? 0 }} pedidos</span>
                                    <span>{{ store.customers_count ?? 0 }} clientes</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-8 text-gray-400 dark:text-gray-500">
                        <LinkIcon class="w-12 h-12 mx-auto mb-2 opacity-50" />
                        <p>Nenhuma loja conectada</p>
                    </div>
                </BaseCard>

                <!-- Plan Info -->
                <BaseCard padding="normal">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                            <CreditCardIcon class="w-5 h-5 text-gray-400" />
                            Plano Atual
                        </h3>
                        <BaseButton variant="secondary" size="sm" @click="openPlanModal">
                            {{ subscription ? 'Alterar' : 'Atribuir' }}
                        </BaseButton>
                    </div>

                    <div v-if="isLoadingSubscription" class="flex justify-center py-8">
                        <LoadingSpinner size="sm" class="text-primary-500" />
                    </div>

                    <div v-else-if="subscription && subscription.plan" class="space-y-4">
                        <div class="p-4 bg-primary-50 dark:bg-primary-900/20 rounded-xl border border-primary-200 dark:border-primary-800">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="font-bold text-gray-900 dark:text-gray-100">{{ subscription.plan.name }}</h4>
                                <span class="badge badge-primary">Ativo</span>
                            </div>
                            <p class="text-2xl font-bold text-primary-600 dark:text-primary-400">
                                {{ formatPrice(subscription.plan.price) }}
                                <span class="text-sm font-normal text-gray-500 dark:text-gray-400">/mês</span>
                            </p>
                        </div>

                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-500 dark:text-gray-400">Pedidos/mês</span>
                                <span class="font-medium text-gray-900 dark:text-gray-100">
                                    {{ formatLimit(subscription.plan.orders_limit) }}
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500 dark:text-gray-400">Lojas</span>
                                <span class="font-medium text-gray-900 dark:text-gray-100">
                                    {{ formatLimit(subscription.plan.stores_limit) }}
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500 dark:text-gray-400">Análises/dia</span>
                                <span class="font-medium text-gray-900 dark:text-gray-100">
                                    {{ subscription.plan.analysis_per_day }}
                                </span>
                            </div>
                        </div>

                        <div class="pt-3 border-t border-gray-200 dark:border-gray-700">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500 dark:text-gray-400">Iniciou em</span>
                                <span class="font-medium text-gray-900 dark:text-gray-100">
                                    {{ formatDate(subscription.starts_at) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div v-else class="text-center py-8 text-gray-400 dark:text-gray-500">
                        <CreditCardIcon class="w-12 h-12 mx-auto mb-2 opacity-50" />
                        <p class="mb-3">Nenhum plano atribuído</p>
                        <BaseButton size="sm" @click="openPlanModal">
                            Atribuir Plano
                        </BaseButton>
                    </div>
                </BaseCard>
            </div>

            <!-- Actions -->
            <BaseCard padding="normal">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Ações</h3>
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

        <!-- Plan Modal -->
        <BaseModal :show="showPlanModal" @close="showPlanModal = false" title="Gerenciar Plano" size="lg">
            <div v-if="isLoadingPlans" class="flex justify-center py-12">
                <LoadingSpinner size="lg" class="text-primary-500" />
            </div>

            <div v-else class="space-y-6">
                <!-- Current Plan Info -->
                <div v-if="subscription && subscription.plan" class="p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Plano Atual</p>
                        <span class="badge badge-primary">Ativo</span>
                    </div>
                    <p class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ subscription.plan.name }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ formatPrice(subscription.plan.price) }}/mês</p>
                </div>

                <!-- Plans Selection -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                        Selecionar Plano
                    </label>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <button
                            v-for="plan in availablePlans"
                            :key="plan.id"
                            @click="selectedPlanId = plan.id"
                            :class="[
                                'relative p-4 rounded-xl border-2 transition-all text-left',
                                selectedPlanId === plan.id
                                    ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20'
                                    : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 hover:border-primary-300 dark:hover:border-primary-700'
                            ]"
                        >
                            <!-- Selected Indicator -->
                            <div v-if="selectedPlanId === plan.id" class="absolute top-3 right-3">
                                <CheckCircleIcon class="w-6 h-6 text-primary-500" />
                            </div>

                            <div class="mb-2">
                                <h4 class="font-bold text-gray-900 dark:text-gray-100">{{ plan.name }}</h4>
                                <p class="text-xl font-bold text-primary-600 dark:text-primary-400 mt-1">
                                    {{ formatPrice(plan.price) }}
                                    <span class="text-xs font-normal text-gray-500 dark:text-gray-400">/mês</span>
                                </p>
                            </div>

                            <div class="space-y-1 text-xs text-gray-600 dark:text-gray-400">
                                <p>Pedidos: {{ formatLimit(plan.orders_limit) }}/mês</p>
                                <p>Lojas: {{ formatLimit(plan.stores_limit) }}</p>
                                <p>Análises: {{ plan.analysis_per_day }}/dia</p>
                            </div>
                        </button>
                    </div>

                    <p v-if="availablePlans.length === 0" class="text-center py-8 text-gray-500 dark:text-gray-400">
                        Nenhum plano ativo disponível
                    </p>
                </div>

                <!-- Remove Plan Option -->
                <div v-if="subscription" class="pt-4 border-t border-gray-200 dark:border-gray-700">
                    <button
                        @click="removePlan"
                        :disabled="isSubmitting"
                        class="flex items-center gap-2 text-sm text-danger-600 dark:text-danger-400 hover:text-danger-700 dark:hover:text-danger-300 transition-colors disabled:opacity-50"
                    >
                        <XMarkIcon class="w-4 h-4" />
                        Remover plano do cliente
                    </button>
                </div>
            </div>

            <template #footer>
                <div class="flex justify-end gap-3">
                    <BaseButton variant="secondary" @click="showPlanModal = false" :disabled="isSubmitting">
                        Cancelar
                    </BaseButton>
                    <BaseButton @click="assignPlan" :loading="isSubmitting" :disabled="!selectedPlanId">
                        {{ subscription ? 'Alterar Plano' : 'Atribuir Plano' }}
                    </BaseButton>
                </div>
            </template>
        </BaseModal>
    </div>
</template>

