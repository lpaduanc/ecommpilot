<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useNotificationStore } from '../../stores/notificationStore';
import { useConfirmDialog } from '../../composables/useConfirmDialog';
import api from '../../services/api';
import BaseCard from '../../components/common/BaseCard.vue';
import BaseButton from '../../components/common/BaseButton.vue';
import BaseModal from '../../components/common/BaseModal.vue';
import LoadingSpinner from '../../components/common/LoadingSpinner.vue';
import {
    SparklesIcon,
    PlusIcon,
    PencilIcon,
    TrashIcon,
    CheckCircleIcon,
    XCircleIcon,
    UsersIcon,
} from '@heroicons/vue/24/outline';

const notificationStore = useNotificationStore();
const { confirm } = useConfirmDialog();

const plans = ref([]);
const isLoading = ref(true);
const isSaving = ref(false);
const showModal = ref(false);
const editingPlan = ref(null);

const defaultForm = {
    id: null,
    name: '',
    slug: '',
    description: '',
    price: 0,
    is_active: true,
    sort_order: 0,
    orders_limit: 750,
    stores_limit: 1,
    analysis_per_day: 1,
    analysis_history_limit: 4,
    data_retention_months: 12,
    has_ai_analysis: true,
    has_ai_chat: false,
    has_suggestion_discussion: false,
    has_suggestion_history: false,
    has_custom_dashboards: false,
    has_external_integrations: false,
    external_integrations_limit: 1,
    has_impact_dashboard: false,
};

const form = reactive({ ...defaultForm });

const isEditMode = computed(() => !!editingPlan.value);

async function fetchPlans() {
    isLoading.value = true;
    try {
        const response = await api.get('/admin/plans');
        plans.value = response.data.plans;
    } catch (error) {
        notificationStore.error('Erro ao carregar planos');
        console.error('Error fetching plans:', error);
    } finally {
        isLoading.value = false;
    }
}

function openModal(plan = null) {
    if (plan) {
        editingPlan.value = plan;
        Object.assign(form, plan);
    } else {
        editingPlan.value = null;
        resetForm();
    }
    showModal.value = true;
}

function closeModal() {
    showModal.value = false;
    editingPlan.value = null;
    resetForm();
}

function resetForm() {
    Object.assign(form, { ...defaultForm });
}

function generateSlug() {
    if (!form.slug && form.name) {
        form.slug = form.name
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-|-$/g, '');
    }
}

async function savePlan() {
    isSaving.value = true;
    try {
        if (form.id) {
            await api.put(`/admin/plans/${form.id}`, form);
            notificationStore.success('Plano atualizado com sucesso!');
        } else {
            await api.post('/admin/plans', form);
            notificationStore.success('Plano criado com sucesso!');
        }
        await fetchPlans();
        closeModal();
    } catch (error) {
        const message = error.response?.data?.message || 'Erro ao salvar plano';
        notificationStore.error(message);
        console.error('Error saving plan:', error);
    } finally {
        isSaving.value = false;
    }
}

async function deletePlan(plan) {
    const confirmed = await confirm({
        title: 'Excluir Plano',
        message: `Tem certeza que deseja excluir o plano "${plan.name}"?`,
        confirmText: 'Excluir',
        variant: 'danger',
    });

    if (!confirmed) return;

    try {
        await api.delete(`/admin/plans/${plan.id}`);
        notificationStore.success('Plano excluído com sucesso!');
        await fetchPlans();
    } catch (error) {
        const message = error.response?.data?.message || 'Erro ao excluir plano';
        notificationStore.error(message);
        console.error('Error deleting plan:', error);
    }
}

async function togglePlanStatus(plan) {
    try {
        await api.put(`/admin/plans/${plan.id}`, {
            is_active: !plan.is_active,
        });
        plan.is_active = !plan.is_active;
        notificationStore.success(`Plano ${plan.is_active ? 'ativado' : 'desativado'} com sucesso!`);
    } catch (error) {
        notificationStore.error('Erro ao alterar status do plano');
        console.error('Error toggling plan status:', error);
    }
}

function formatLimit(value) {
    return value === -1 ? 'Ilimitado' : value.toLocaleString('pt-BR');
}

function formatPrice(value) {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(value);
}

function formatRetention(value) {
    if (value === -1) return 'Ilimitado';
    return value === 1 ? '1 mês' : `${value} meses`;
}

function formatIntegrationsLimit(value) {
    return value === -1 ? 'Ilimitado' : value;
}

onMounted(fetchPlans);
</script>

<template>
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-display font-bold text-gray-900 dark:text-gray-100 flex items-center gap-3">
                    <SparklesIcon class="w-8 h-8 text-primary-500" />
                    Gerenciamento de Planos
                </h1>
                <p class="text-gray-500 dark:text-gray-400 mt-1">
                    Configure os planos e limites do sistema
                </p>
            </div>
            <BaseButton @click="openModal()">
                <PlusIcon class="w-4 h-4" />
                Novo Plano
            </BaseButton>
        </div>

        <!-- Loading -->
        <div v-if="isLoading" class="flex items-center justify-center py-20">
            <LoadingSpinner size="lg" class="text-primary-500" />
        </div>

        <!-- Empty State -->
        <BaseCard v-else-if="plans.length === 0" class="text-center py-12">
            <SparklesIcon class="w-12 h-12 text-gray-300 dark:text-gray-600 mx-auto mb-4" />
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">
                Nenhum plano cadastrado
            </h3>
            <p class="text-gray-500 dark:text-gray-400 mb-4">
                Crie o primeiro plano para começar
            </p>
            <BaseButton @click="openModal()">
                <PlusIcon class="w-4 h-4" />
                Criar Plano
            </BaseButton>
        </BaseCard>

        <!-- Plans Grid -->
        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <BaseCard
                v-for="plan in plans"
                :key="plan.id"
                class="relative"
            >
                <!-- Status Badge -->
                <div class="absolute top-4 right-4 flex items-center gap-2">
                    <span
                        v-if="plan.subscriptions_count > 0"
                        class="flex items-center gap-1 px-2 py-1 text-xs font-medium rounded-full bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400"
                    >
                        <UsersIcon class="w-3 h-3" />
                        {{ plan.subscriptions_count }}
                    </span>
                    <button
                        @click="togglePlanStatus(plan)"
                        :class="[
                            'px-2 py-1 text-xs font-medium rounded-full transition-colors cursor-pointer',
                            plan.is_active
                                ? 'bg-success-100 text-success-700 dark:bg-success-900/30 dark:text-success-400 hover:bg-success-200'
                                : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300 hover:bg-gray-200'
                        ]"
                    >
                        {{ plan.is_active ? 'Ativo' : 'Inativo' }}
                    </button>
                </div>

                <!-- Plan Info -->
                <div class="mb-4 pr-24">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                        {{ plan.name }}
                    </h3>
                    <p class="text-2xl font-bold text-primary-600 dark:text-primary-400 mt-1">
                        {{ formatPrice(plan.price) }}
                        <span class="text-sm font-normal text-gray-500">/mês</span>
                    </p>
                    <p v-if="plan.description" class="text-sm text-gray-500 dark:text-gray-400 mt-2 line-clamp-2">
                        {{ plan.description }}
                    </p>
                </div>

                <!-- Limits -->
                <div class="space-y-2 text-sm mb-4">
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-gray-400">Pedidos/mês</span>
                        <span class="font-medium text-gray-900 dark:text-gray-100">
                            {{ formatLimit(plan.orders_limit) }}
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-gray-400">Lojas</span>
                        <span class="font-medium text-gray-900 dark:text-gray-100">
                            {{ formatLimit(plan.stores_limit) }}
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-gray-400">Análises/dia</span>
                        <span class="font-medium text-gray-900 dark:text-gray-100">
                            {{ plan.analysis_per_day }}
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-gray-400">Histórico análises</span>
                        <span class="font-medium text-gray-900 dark:text-gray-100">
                            {{ formatLimit(plan.analysis_history_limit) }}
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-gray-400">Retenção dados</span>
                        <span class="font-medium text-gray-900 dark:text-gray-100">
                            {{ formatRetention(plan.data_retention_months) }}
                        </span>
                    </div>
                </div>

                <!-- Features -->
                <div class="space-y-2 text-sm border-t border-gray-200 dark:border-gray-700 pt-4 mb-4">
                    <div class="flex items-center gap-2">
                        <component
                            :is="plan.has_ai_analysis ? CheckCircleIcon : XCircleIcon"
                            :class="plan.has_ai_analysis ? 'text-success-500' : 'text-gray-300 dark:text-gray-600'"
                            class="w-4 h-4 flex-shrink-0"
                        />
                        <span :class="plan.has_ai_analysis ? 'text-gray-900 dark:text-gray-100' : 'text-gray-400 dark:text-gray-500'">
                            Análises IA
                        </span>
                    </div>
                    <div class="flex items-center gap-2">
                        <component
                            :is="plan.has_ai_chat ? CheckCircleIcon : XCircleIcon"
                            :class="plan.has_ai_chat ? 'text-success-500' : 'text-gray-300 dark:text-gray-600'"
                            class="w-4 h-4 flex-shrink-0"
                        />
                        <span :class="plan.has_ai_chat ? 'text-gray-900 dark:text-gray-100' : 'text-gray-400 dark:text-gray-500'">
                            Assistente IA (Chat)
                        </span>
                    </div>
                    <div class="flex items-center gap-2">
                        <component
                            :is="plan.has_suggestion_discussion ? CheckCircleIcon : XCircleIcon"
                            :class="plan.has_suggestion_discussion ? 'text-success-500' : 'text-gray-300 dark:text-gray-600'"
                            class="w-4 h-4 flex-shrink-0"
                        />
                        <span :class="plan.has_suggestion_discussion ? 'text-gray-900 dark:text-gray-100' : 'text-gray-400 dark:text-gray-500'">
                            Discutir Sugestões
                            <template v-if="plan.has_suggestion_discussion && plan.has_suggestion_history">
                                (com histórico)
                            </template>
                        </span>
                    </div>
                    <div class="flex items-center gap-2">
                        <component
                            :is="plan.has_custom_dashboards ? CheckCircleIcon : XCircleIcon"
                            :class="plan.has_custom_dashboards ? 'text-success-500' : 'text-gray-300 dark:text-gray-600'"
                            class="w-4 h-4 flex-shrink-0"
                        />
                        <span :class="plan.has_custom_dashboards ? 'text-gray-900 dark:text-gray-100' : 'text-gray-400 dark:text-gray-500'">
                            Dashboards personalizados
                        </span>
                    </div>
                    <div class="flex items-center gap-2">
                        <component
                            :is="plan.has_external_integrations ? CheckCircleIcon : XCircleIcon"
                            :class="plan.has_external_integrations ? 'text-success-500' : 'text-gray-300 dark:text-gray-600'"
                            class="w-4 h-4 flex-shrink-0"
                        />
                        <span :class="plan.has_external_integrations ? 'text-gray-900 dark:text-gray-100' : 'text-gray-400 dark:text-gray-500'">
                            Integrações externas
                            <template v-if="plan.has_external_integrations">
                                ({{ formatIntegrationsLimit(plan.external_integrations_limit) }})
                            </template>
                        </span>
                    </div>
                    <div class="flex items-center gap-2">
                        <component
                            :is="plan.has_impact_dashboard ? CheckCircleIcon : XCircleIcon"
                            :class="plan.has_impact_dashboard ? 'text-success-500' : 'text-gray-300 dark:text-gray-600'"
                            class="w-4 h-4 flex-shrink-0"
                        />
                        <span :class="plan.has_impact_dashboard ? 'text-gray-900 dark:text-gray-100' : 'text-gray-400 dark:text-gray-500'">
                            Dashboard de Impacto
                        </span>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center gap-2">
                    <BaseButton
                        variant="secondary"
                        size="sm"
                        class="flex-1"
                        @click="openModal(plan)"
                    >
                        <PencilIcon class="w-4 h-4" />
                        Editar
                    </BaseButton>
                    <BaseButton
                        variant="danger"
                        size="sm"
                        @click="deletePlan(plan)"
                        :disabled="plan.subscriptions_count > 0"
                        :title="plan.subscriptions_count > 0 ? 'Não é possível excluir um plano com assinaturas ativas' : ''"
                    >
                        <TrashIcon class="w-4 h-4" />
                    </BaseButton>
                </div>
            </BaseCard>
        </div>

        <!-- Modal Criar/Editar -->
        <BaseModal
            :show="showModal"
            :title="isEditMode ? 'Editar Plano' : 'Novo Plano'"
            size="xl"
            @close="closeModal"
        >
            <form @submit.prevent="savePlan" class="space-y-6">
                <!-- Informações Básicas -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Nome do Plano *
                        </label>
                        <input
                            type="text"
                            v-model="form.name"
                            @blur="generateSlug"
                            placeholder="Ex: Starter, Business"
                            required
                            class="w-full px-4 py-2.5 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-xl text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-colors"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Slug *
                        </label>
                        <input
                            type="text"
                            v-model="form.slug"
                            placeholder="Ex: starter, business"
                            required
                            class="w-full px-4 py-2.5 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-xl text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-colors"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Preço (R$) *
                        </label>
                        <input
                            type="number"
                            v-model.number="form.price"
                            step="0.01"
                            min="0"
                            required
                            class="w-full px-4 py-2.5 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-xl text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-colors"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Ordem de Exibição
                        </label>
                        <input
                            type="number"
                            v-model.number="form.sort_order"
                            min="0"
                            class="w-full px-4 py-2.5 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-xl text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-colors"
                        />
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Descrição
                        </label>
                        <textarea
                            v-model="form.description"
                            rows="2"
                            placeholder="Descrição do plano..."
                            class="w-full px-4 py-2.5 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-xl text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-colors resize-none"
                        ></textarea>
                    </div>
                </div>

                <!-- Limites -->
                <div class="p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl space-y-4">
                    <h3 class="font-medium text-gray-900 dark:text-gray-100">
                        Limites do Plano
                        <span class="text-xs text-gray-500 ml-2">(-1 = ilimitado)</span>
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm text-gray-600 dark:text-gray-400 mb-1">
                                Pedidos/mês
                            </label>
                            <input
                                type="number"
                                v-model.number="form.orders_limit"
                                min="-1"
                                required
                                class="w-full px-4 py-2.5 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-xl text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-colors"
                            />
                        </div>
                        <div>
                            <label class="block text-sm text-gray-600 dark:text-gray-400 mb-1">
                                Lojas
                            </label>
                            <input
                                type="number"
                                v-model.number="form.stores_limit"
                                min="-1"
                                required
                                class="w-full px-4 py-2.5 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-xl text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-colors"
                            />
                        </div>
                        <div>
                            <label class="block text-sm text-gray-600 dark:text-gray-400 mb-1">
                                Análises/dia
                            </label>
                            <input
                                type="number"
                                v-model.number="form.analysis_per_day"
                                min="0"
                                required
                                class="w-full px-4 py-2.5 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-xl text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-colors"
                            />
                        </div>
                        <div>
                            <label class="block text-sm text-gray-600 dark:text-gray-400 mb-1">
                                Histórico de análises
                            </label>
                            <input
                                type="number"
                                v-model.number="form.analysis_history_limit"
                                min="-1"
                                required
                                class="w-full px-4 py-2.5 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-xl text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-colors"
                            />
                        </div>
                        <div>
                            <label class="block text-sm text-gray-600 dark:text-gray-400 mb-1">
                                Retenção de dados (meses)
                            </label>
                            <input
                                type="number"
                                v-model.number="form.data_retention_months"
                                min="-1"
                                required
                                class="w-full px-4 py-2.5 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-xl text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-colors"
                            />
                        </div>
                    </div>
                </div>

                <!-- Features -->
                <div class="p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl space-y-3">
                    <h3 class="font-medium text-gray-900 dark:text-gray-100">Features</h3>

                    <label class="flex items-center gap-3 cursor-pointer">
                        <input
                            type="checkbox"
                            v-model="form.has_ai_analysis"
                            class="w-4 h-4 rounded border-gray-300 text-primary-500 focus:ring-primary-500"
                        />
                        <span class="text-sm text-gray-700 dark:text-gray-300">
                            Análises IA
                        </span>
                    </label>

                    <label class="flex items-center gap-3 cursor-pointer">
                        <input
                            type="checkbox"
                            v-model="form.has_ai_chat"
                            class="w-4 h-4 rounded border-gray-300 text-primary-500 focus:ring-primary-500"
                        />
                        <span class="text-sm text-gray-700 dark:text-gray-300">
                            Assistente IA (Chat)
                        </span>
                    </label>

                    <label class="flex items-center gap-3 cursor-pointer">
                        <input
                            type="checkbox"
                            v-model="form.has_suggestion_discussion"
                            class="w-4 h-4 rounded border-gray-300 text-primary-500 focus:ring-primary-500"
                        />
                        <span class="text-sm text-gray-700 dark:text-gray-300">
                            Discutir Sugestões com IA
                        </span>
                    </label>

                    <label v-if="form.has_suggestion_discussion" class="flex items-center gap-3 cursor-pointer ml-7">
                        <input
                            type="checkbox"
                            v-model="form.has_suggestion_history"
                            class="w-4 h-4 rounded border-gray-300 text-primary-500 focus:ring-primary-500"
                        />
                        <span class="text-sm text-gray-700 dark:text-gray-300">
                            Persistir Histórico de Discussões
                        </span>
                    </label>

                    <label class="flex items-center gap-3 cursor-pointer">
                        <input
                            type="checkbox"
                            v-model="form.has_custom_dashboards"
                            class="w-4 h-4 rounded border-gray-300 text-primary-500 focus:ring-primary-500"
                        />
                        <span class="text-sm text-gray-700 dark:text-gray-300">
                            Dashboards Personalizados
                        </span>
                    </label>

                    <label class="flex items-center gap-3 cursor-pointer">
                        <input
                            type="checkbox"
                            v-model="form.has_external_integrations"
                            class="w-4 h-4 rounded border-gray-300 text-primary-500 focus:ring-primary-500"
                        />
                        <span class="text-sm text-gray-700 dark:text-gray-300">
                            Integrações Externas
                        </span>
                    </label>

                    <div v-if="form.has_external_integrations" class="ml-7">
                        <label class="block text-sm text-gray-600 dark:text-gray-400 mb-1">
                            Quantidade de integrações
                        </label>
                        <input
                            type="number"
                            v-model.number="form.external_integrations_limit"
                            min="-1"
                            placeholder="-1 = ilimitado"
                            class="w-full px-4 py-2.5 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-xl text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-colors"
                        />
                    </div>

                    <label class="flex items-center gap-3 cursor-pointer">
                        <input
                            type="checkbox"
                            v-model="form.has_impact_dashboard"
                            class="w-4 h-4 rounded border-gray-300 text-primary-500 focus:ring-primary-500"
                        />
                        <span class="text-sm text-gray-700 dark:text-gray-300">
                            Dashboard de Impacto nas Vendas
                        </span>
                    </label>

                    <div class="border-t border-gray-200 dark:border-gray-700 pt-3 mt-3">
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input
                                type="checkbox"
                                v-model="form.is_active"
                                class="w-4 h-4 rounded border-gray-300 text-primary-500 focus:ring-primary-500"
                            />
                            <span class="text-sm text-gray-700 dark:text-gray-300">
                                Plano Ativo (visível para novos clientes)
                            </span>
                        </label>
                    </div>
                </div>
            </form>

            <template #footer>
                <div class="flex justify-end gap-3">
                    <BaseButton variant="secondary" @click="closeModal">
                        Cancelar
                    </BaseButton>
                    <BaseButton @click="savePlan" :loading="isSaving">
                        {{ isEditMode ? 'Atualizar' : 'Criar' }} Plano
                    </BaseButton>
                </div>
            </template>
        </BaseModal>
    </div>
</template>
