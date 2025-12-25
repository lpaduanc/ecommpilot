<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useNotificationStore } from '../../stores/notificationStore';
import api from '../../services/api';
import BaseCard from '../../components/common/BaseCard.vue';
import BaseButton from '../../components/common/BaseButton.vue';
import BaseModal from '../../components/common/BaseModal.vue';
import BaseInput from '../../components/common/BaseInput.vue';
import LoadingSpinner from '../../components/common/LoadingSpinner.vue';
import {
    UsersIcon,
    MagnifyingGlassIcon,
    EyeIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
    PlusIcon,
    FunnelIcon,
    ArrowPathIcon,
    PencilIcon,
    LockClosedIcon,
    CurrencyDollarIcon,
    XMarkIcon,
    CheckIcon,
    ExclamationTriangleIcon,
} from '@heroicons/vue/24/outline';

const router = useRouter();
const notificationStore = useNotificationStore();

const clients = ref([]);
const isLoading = ref(false);
const searchQuery = ref('');
const statusFilter = ref('');
const creditsFilter = ref('');
const currentPage = ref(1);
const totalPages = ref(1);
const totalItems = ref(0);

// Modals
const showCreateModal = ref(false);
const showAddCreditsModal = ref(false);
const showResetPasswordModal = ref(false);
const showDeleteModal = ref(false);
const selectedClient = ref(null);
const isSubmitting = ref(false);

// Forms
const createForm = ref({
    name: '',
    email: '',
    phone: '',
    password: '',
    ai_credits: 10,
    is_active: true,
});
const creditsForm = ref({ amount: 10, reason: '' });
const passwordForm = ref({ password: '' });

const statusOptions = [
    { value: '', label: 'Todos os Status' },
    { value: 'active', label: 'Ativos' },
    { value: 'inactive', label: 'Inativos' },
];

const creditsOptions = [
    { value: '', label: 'Todos os Créditos' },
    { value: 'low', label: 'Créditos Baixos (<10)' },
    { value: 'zero', label: 'Sem Créditos' },
];

async function fetchClients() {
    isLoading.value = true;
    try {
        const response = await api.get('/admin/clients', {
            params: {
                search: searchQuery.value,
                status: statusFilter.value,
                credits_filter: creditsFilter.value,
                page: currentPage.value,
                per_page: 20,
            },
        });
        clients.value = response.data.data;
        totalPages.value = response.data.last_page;
        totalItems.value = response.data.total;
    } catch (error) {
        notificationStore.error('Erro ao carregar clientes');
        clients.value = [];
    } finally {
        isLoading.value = false;
    }
}

function handleSearch() {
    currentPage.value = 1;
    fetchClients();
}

function goToPage(page) {
    if (page < 1 || page > totalPages.value) return;
    currentPage.value = page;
    fetchClients();
}

function viewClient(client) {
    router.push({ name: 'admin-client-detail', params: { id: client.id } });
}

function formatDate(date) {
    if (!date) return 'Nunca';
    return new Date(date).toLocaleDateString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

// Create Client
function openCreateModal() {
    createForm.value = {
        name: '',
        email: '',
        phone: '',
        password: '',
        ai_credits: 10,
        is_active: true,
    };
    showCreateModal.value = true;
}

async function submitCreateClient() {
    isSubmitting.value = true;
    try {
        await api.post('/admin/clients', createForm.value);
        notificationStore.success('Cliente criado com sucesso!');
        showCreateModal.value = false;
        fetchClients();
    } catch (error) {
        notificationStore.error(error.response?.data?.message || 'Erro ao criar cliente');
    } finally {
        isSubmitting.value = false;
    }
}

// Toggle Status
async function toggleStatus(client) {
    try {
        const response = await api.post(`/admin/clients/${client.id}/toggle-status`);
        notificationStore.success(response.data.message);
        client.is_active = response.data.is_active;
    } catch (error) {
        notificationStore.error('Erro ao alterar status');
    }
}

// Add Credits
function openAddCreditsModal(client) {
    selectedClient.value = client;
    creditsForm.value = { amount: 10, reason: '' };
    showAddCreditsModal.value = true;
}

async function submitAddCredits() {
    isSubmitting.value = true;
    try {
        const response = await api.post(`/admin/clients/${selectedClient.value.id}/add-credits`, creditsForm.value);
        notificationStore.success(response.data.message);
        selectedClient.value.ai_credits = response.data.ai_credits;
        showAddCreditsModal.value = false;
    } catch (error) {
        notificationStore.error(error.response?.data?.message || 'Erro ao adicionar créditos');
    } finally {
        isSubmitting.value = false;
    }
}

// Reset Password
function openResetPasswordModal(client) {
    selectedClient.value = client;
    passwordForm.value = { password: '' };
    showResetPasswordModal.value = true;
}

async function submitResetPassword() {
    isSubmitting.value = true;
    try {
        await api.post(`/admin/clients/${selectedClient.value.id}/reset-password`, passwordForm.value);
        notificationStore.success('Senha redefinida com sucesso!');
        showResetPasswordModal.value = false;
    } catch (error) {
        notificationStore.error(error.response?.data?.message || 'Erro ao redefinir senha');
    } finally {
        isSubmitting.value = false;
    }
}

// Delete Client
function openDeleteModal(client) {
    selectedClient.value = client;
    showDeleteModal.value = true;
}

async function submitDeleteClient() {
    isSubmitting.value = true;
    try {
        await api.delete(`/admin/clients/${selectedClient.value.id}`);
        notificationStore.success('Cliente excluído com sucesso!');
        showDeleteModal.value = false;
        fetchClients();
    } catch (error) {
        notificationStore.error(error.response?.data?.message || 'Erro ao excluir cliente');
    } finally {
        isSubmitting.value = false;
    }
}

// Impersonate
async function impersonateClient(client) {
    try {
        const response = await api.post(`/admin/clients/${client.id}/impersonate`);
        // Store original admin token
        localStorage.setItem('admin_token', localStorage.getItem('token'));
        // Use client token
        localStorage.setItem('token', response.data.token);
        notificationStore.success('Você está acessando como: ' + client.name);
        window.location.href = '/';
    } catch (error) {
        notificationStore.error('Erro ao impersonar cliente');
    }
}

onMounted(() => {
    fetchClients();
});
</script>

<template>
    <div class="space-y-6">
        <!-- Page Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-display font-bold text-gray-900 flex items-center gap-3">
                    <UsersIcon class="w-8 h-8 text-primary-500" />
                    Gerenciar Clientes
                </h1>
                <p class="text-gray-500 mt-1">{{ totalItems }} clientes cadastrados</p>
            </div>
            <BaseButton @click="openCreateModal">
                <PlusIcon class="w-4 h-4" />
                Novo Cliente
            </BaseButton>
        </div>

        <!-- Filters -->
        <div class="flex flex-wrap items-center gap-4">
            <div class="flex-1 min-w-[200px] max-w-md">
                <div class="relative">
                    <MagnifyingGlassIcon class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
                    <input
                        v-model="searchQuery"
                        @keyup.enter="handleSearch"
                        type="text"
                        placeholder="Buscar por nome, e-mail ou telefone..."
                        class="input pl-12"
                    />
                </div>
            </div>
            <select v-model="statusFilter" @change="handleSearch" class="input w-40">
                <option v-for="option in statusOptions" :key="option.value" :value="option.value">
                    {{ option.label }}
                </option>
            </select>
            <select v-model="creditsFilter" @change="handleSearch" class="input w-48">
                <option v-for="option in creditsOptions" :key="option.value" :value="option.value">
                    {{ option.label }}
                </option>
            </select>
            <BaseButton variant="secondary" @click="handleSearch">
                <FunnelIcon class="w-4 h-4" />
                Filtrar
            </BaseButton>
        </div>

        <!-- Table -->
        <BaseCard padding="none">
            <div v-if="isLoading" class="flex items-center justify-center py-20">
                <LoadingSpinner size="lg" class="text-primary-500" />
            </div>

            <div v-else-if="clients.length > 0" class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="text-left px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                Cliente
                            </th>
                            <th class="text-left px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                Lojas
                            </th>
                            <th class="text-left px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                Créditos IA
                            </th>
                            <th class="text-left px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                Status
                            </th>
                            <th class="text-left px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                Último Login
                            </th>
                            <th class="text-left px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                Cadastro
                            </th>
                            <th class="text-left px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                Ações
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr
                            v-for="client in clients"
                            :key="client.id"
                            class="hover:bg-gray-50 transition-colors"
                        >
                            <td class="px-6 py-4">
                                <div>
                                    <p class="font-medium text-gray-900">{{ client.name }}</p>
                                    <p class="text-sm text-gray-500">{{ client.email }}</p>
                                    <p v-if="client.phone" class="text-xs text-gray-400">{{ client.phone }}</p>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-col gap-1">
                                    <span class="badge badge-primary">{{ client.stores_count }} lojas</span>
                                    <span
                                        v-for="store in client.stores?.slice(0, 2)"
                                        :key="store.id"
                                        class="text-xs text-gray-500"
                                    >
                                        {{ store.name }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span
                                    :class="[
                                        'badge',
                                        client.ai_credits === 0 ? 'badge-danger' :
                                        client.ai_credits < 10 ? 'badge-warning' : 'badge-success'
                                    ]"
                                >
                                    {{ client.ai_credits }} créditos
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <button
                                    @click="toggleStatus(client)"
                                    :class="[
                                        'badge cursor-pointer transition-colors',
                                        client.is_active ? 'badge-success hover:bg-success-200' : 'badge-danger hover:bg-danger-200'
                                    ]"
                                    :title="client.is_active ? 'Clique para desativar' : 'Clique para ativar'"
                                >
                                    {{ client.is_active ? 'Ativo' : 'Inativo' }}
                                </button>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                {{ formatDate(client.last_login_at) }}
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                {{ formatDate(client.created_at) }}
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-1">
                                    <BaseButton variant="ghost" size="sm" @click="viewClient(client)" title="Ver detalhes">
                                        <EyeIcon class="w-4 h-4" />
                                    </BaseButton>
                                    <BaseButton variant="ghost" size="sm" @click="openAddCreditsModal(client)" title="Adicionar créditos">
                                        <CurrencyDollarIcon class="w-4 h-4 text-success-500" />
                                    </BaseButton>
                                    <BaseButton variant="ghost" size="sm" @click="openResetPasswordModal(client)" title="Redefinir senha">
                                        <LockClosedIcon class="w-4 h-4 text-warning-500" />
                                    </BaseButton>
                                    <BaseButton variant="ghost" size="sm" @click="impersonateClient(client)" title="Acessar como cliente">
                                        <ArrowPathIcon class="w-4 h-4 text-primary-500" />
                                    </BaseButton>
                                    <BaseButton variant="ghost" size="sm" @click="openDeleteModal(client)" title="Excluir">
                                        <XMarkIcon class="w-4 h-4 text-danger-500" />
                                    </BaseButton>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-else class="text-center py-20">
                <UsersIcon class="w-16 h-16 text-gray-300 mx-auto mb-4" />
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Nenhum cliente encontrado</h3>
                <p class="text-gray-500">Tente ajustar os filtros ou crie um novo cliente.</p>
            </div>

            <!-- Pagination -->
            <div v-if="totalPages > 1" class="flex items-center justify-between px-6 py-4 border-t border-gray-100">
                <p class="text-sm text-gray-500">
                    Mostrando {{ (currentPage - 1) * 20 + 1 }} a {{ Math.min(currentPage * 20, totalItems) }} de {{ totalItems }}
                </p>
                <div class="flex items-center gap-2">
                    <BaseButton variant="ghost" size="sm" :disabled="currentPage === 1" @click="goToPage(currentPage - 1)">
                        <ChevronLeftIcon class="w-4 h-4" />
                    </BaseButton>
                    <span class="text-sm text-gray-600">{{ currentPage }} / {{ totalPages }}</span>
                    <BaseButton variant="ghost" size="sm" :disabled="currentPage === totalPages" @click="goToPage(currentPage + 1)">
                        <ChevronRightIcon class="w-4 h-4" />
                    </BaseButton>
                </div>
            </div>
        </BaseCard>

        <!-- Create Client Modal -->
        <BaseModal :show="showCreateModal" title="Novo Cliente" @close="showCreateModal = false">
            <form @submit.prevent="submitCreateClient" class="space-y-4">
                <BaseInput v-model="createForm.name" label="Nome *" placeholder="Nome completo" required />
                <BaseInput v-model="createForm.email" type="email" label="E-mail *" placeholder="email@exemplo.com" required />
                <BaseInput v-model="createForm.phone" label="Telefone" placeholder="(00) 00000-0000" />
                <BaseInput v-model="createForm.password" type="password" label="Senha *" placeholder="Mínimo 8 caracteres" required />
                <BaseInput v-model.number="createForm.ai_credits" type="number" label="Créditos IA" min="0" />
                <label class="flex items-center gap-2">
                    <input type="checkbox" v-model="createForm.is_active" class="rounded border-gray-300 text-primary-600" />
                    <span class="text-sm text-gray-700">Cliente ativo</span>
                </label>
            </form>
            <template #footer>
                <div class="flex justify-end gap-3">
                    <BaseButton variant="secondary" @click="showCreateModal = false">Cancelar</BaseButton>
                    <BaseButton @click="submitCreateClient" :loading="isSubmitting">Criar Cliente</BaseButton>
                </div>
            </template>
        </BaseModal>

        <!-- Add Credits Modal -->
        <BaseModal :show="showAddCreditsModal" title="Adicionar Créditos" @close="showAddCreditsModal = false">
            <div v-if="selectedClient" class="space-y-4">
                <div class="p-4 bg-gray-50 rounded-xl">
                    <p class="text-sm text-gray-500">Cliente</p>
                    <p class="font-semibold text-gray-900">{{ selectedClient.name }}</p>
                    <p class="text-sm text-gray-500 mt-1">Créditos atuais: {{ selectedClient.ai_credits }}</p>
                </div>
                <BaseInput v-model.number="creditsForm.amount" type="number" label="Quantidade de Créditos" min="1" max="10000" />
                <BaseInput v-model="creditsForm.reason" label="Motivo (opcional)" placeholder="Ex: Bônus, Promoção..." />
            </div>
            <template #footer>
                <div class="flex justify-end gap-3">
                    <BaseButton variant="secondary" @click="showAddCreditsModal = false">Cancelar</BaseButton>
                    <BaseButton @click="submitAddCredits" :loading="isSubmitting">Adicionar</BaseButton>
                </div>
            </template>
        </BaseModal>

        <!-- Reset Password Modal -->
        <BaseModal :show="showResetPasswordModal" title="Redefinir Senha" @close="showResetPasswordModal = false">
            <div v-if="selectedClient" class="space-y-4">
                <div class="p-4 bg-gray-50 rounded-xl">
                    <p class="text-sm text-gray-500">Cliente</p>
                    <p class="font-semibold text-gray-900">{{ selectedClient.name }}</p>
                    <p class="text-sm text-gray-500">{{ selectedClient.email }}</p>
                </div>
                <BaseInput v-model="passwordForm.password" type="password" label="Nova Senha" placeholder="Mínimo 8 caracteres" />
            </div>
            <template #footer>
                <div class="flex justify-end gap-3">
                    <BaseButton variant="secondary" @click="showResetPasswordModal = false">Cancelar</BaseButton>
                    <BaseButton @click="submitResetPassword" :loading="isSubmitting">Redefinir</BaseButton>
                </div>
            </template>
        </BaseModal>

        <!-- Delete Confirmation Modal -->
        <BaseModal :show="showDeleteModal" title="Excluir Cliente" @close="showDeleteModal = false">
            <div v-if="selectedClient" class="text-center">
                <div class="w-16 h-16 rounded-full bg-danger-100 flex items-center justify-center mx-auto mb-4">
                    <ExclamationTriangleIcon class="w-8 h-8 text-danger-600" />
                </div>
                <p class="text-gray-600 mb-2">
                    Tem certeza que deseja excluir o cliente <strong>{{ selectedClient.name }}</strong>?
                </p>
                <p class="text-sm text-gray-500">
                    Todos os dados, lojas, pedidos e histórico serão removidos permanentemente.
                </p>
            </div>
            <template #footer>
                <div class="flex justify-end gap-3">
                    <BaseButton variant="secondary" @click="showDeleteModal = false">Cancelar</BaseButton>
                    <BaseButton variant="danger" @click="submitDeleteClient" :loading="isSubmitting">Excluir</BaseButton>
                </div>
            </template>
        </BaseModal>
    </div>
</template>
