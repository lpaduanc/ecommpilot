<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useAuthStore } from '../stores/authStore';
import { useUserManagementStore } from '../stores/userManagementStore';
import { useNotificationStore } from '../stores/notificationStore';
import BaseCard from '../components/common/BaseCard.vue';
import BaseButton from '../components/common/BaseButton.vue';
import BaseInput from '../components/common/BaseInput.vue';
import LoadingSpinner from '../components/common/LoadingSpinner.vue';
import UserFormModal from '../components/users/UserFormModal.vue';
import {
    UsersIcon,
    PlusIcon,
    PencilIcon,
    TrashIcon,
    MagnifyingGlassIcon,
    ShieldCheckIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
} from '@heroicons/vue/24/outline';

const authStore = useAuthStore();
const userStore = useUserManagementStore();
const notificationStore = useNotificationStore();

const searchQuery = ref('');
const showUserModal = ref(false);
const selectedUserId = ref(null);
const showDeleteConfirm = ref(false);
const userToDelete = ref(null);

const canCreate = computed(() => authStore.hasPermission('users.create'));
const canEdit = computed(() => authStore.hasPermission('users.edit'));
const canDelete = computed(() => authStore.hasPermission('users.delete'));

const filteredUsers = computed(() => (userStore.users || []).filter(u => u && u.id));

const hasUsers = computed(() => filteredUsers.value.length > 0);

function openCreateModal() {
    selectedUserId.value = null;
    showUserModal.value = true;
}

function openEditModal(userId) {
    selectedUserId.value = userId;
    showUserModal.value = true;
}

function confirmDelete(user) {
    userToDelete.value = user;
    showDeleteConfirm.value = true;
}

async function handleDelete() {
    if (!userToDelete.value) return;

    const result = await userStore.deleteUser(userToDelete.value.id);

    if (result.success) {
        notificationStore.success('Usuário excluído com sucesso!');
        showDeleteConfirm.value = false;
        userToDelete.value = null;
    } else {
        notificationStore.error(result.message);
    }
}

function cancelDelete() {
    showDeleteConfirm.value = false;
    userToDelete.value = null;
}

async function handleSearch() {
    await userStore.fetchUsers(1, searchQuery.value);
}

async function handlePageChange(page) {
    await userStore.fetchUsers(page, searchQuery.value);
}

function handleModalSaved() {
    // Recarrega a lista de usuários
    userStore.fetchUsers(userStore.currentPage, searchQuery.value);
}

function formatDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return new Intl.DateTimeFormat('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(date);
}

function getPermissionsCount(user) {
    return user.permissions?.length || 0;
}

// Debounce para o search
let searchTimeout = null;
watch(searchQuery, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        handleSearch();
    }, 500);
});

onMounted(() => {
    userStore.fetchUsers();
});
</script>

<template>
    <div class="min-h-screen -m-4 sm:-m-6 lg:-m-8 -mt-4 sm:-mt-6 lg:-mt-8">
        <!-- Hero Header with Gradient -->
        <div class="relative overflow-hidden bg-gradient-to-br from-slate-900 via-primary-950 to-secondary-950 dark:from-gray-950 dark:via-gray-900 dark:to-gray-950 px-4 sm:px-6 lg:px-8 py-6 sm:py-8 lg:py-12">
            <!-- Background Elements -->
            <div class="absolute inset-0 overflow-hidden">
                <div class="absolute -top-40 -right-40 w-80 h-80 bg-primary-500/20 rounded-full blur-3xl"></div>
                <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-secondary-500/20 rounded-full blur-3xl"></div>
                <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-96 h-96 bg-accent-500/10 rounded-full blur-3xl"></div>
                <!-- Grid Pattern -->
                <div class="absolute inset-0" style="background-image: radial-gradient(rgba(255,255,255,0.03) 1px, transparent 1px); background-size: 40px 40px;"></div>
            </div>

            <div class="relative z-10 max-w-7xl mx-auto">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 sm:w-14 h-10 sm:h-14 rounded-xl sm:rounded-2xl bg-gradient-to-br from-primary-400 to-secondary-500 flex items-center justify-center shadow-lg shadow-primary-500/30">
                            <UsersIcon class="w-5 sm:w-7 h-5 sm:h-7 text-white" />
                        </div>
                        <div>
                            <h1 class="text-xl sm:text-2xl lg:text-4xl font-display font-bold text-white">
                                Usuários da Loja
                            </h1>
                            <p class="text-primary-200/80 text-xs sm:text-sm lg:text-base">
                                Gerencie os funcionários que terão acesso à sua loja
                            </p>
                        </div>
                    </div>

                    <BaseButton
                        v-if="canCreate"
                        @click="openCreateModal"
                        variant="primary"
                        class="shadow-xl shadow-primary-500/30 w-full sm:w-auto"
                    >
                        <PlusIcon class="w-5 h-5" />
                        Novo Usuário
                    </BaseButton>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="px-4 sm:px-6 lg:px-8 py-4 sm:py-6 lg:py-8 bg-gradient-to-b from-gray-100 to-gray-50 dark:from-gray-900 dark:to-gray-950 min-h-[calc(100vh-200px)]">
            <div class="max-w-7xl mx-auto">
                <!-- Search and Filters -->
                <BaseCard padding="sm" class="mb-6">
                    <div class="flex flex-col lg:flex-row gap-4">
                        <div class="flex-1 relative">
                            <MagnifyingGlassIcon class="w-5 h-5 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" />
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Buscar por nome ou e-mail..."
                                class="w-full pl-10 pr-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                            />
                        </div>
                    </div>
                </BaseCard>

                <!-- Users List -->
                <BaseCard padding="none">
                    <!-- Loading State -->
                    <div v-if="userStore.isLoading" class="flex items-center justify-center py-12">
                        <LoadingSpinner size="lg" />
                    </div>

                    <!-- Empty State -->
                    <div v-else-if="!hasUsers" class="flex flex-col items-center justify-center py-12 px-4">
                        <div class="w-16 h-16 rounded-full bg-gray-100 flex items-center justify-center mb-4">
                            <UsersIcon class="w-8 h-8 text-gray-400" />
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">
                            Nenhum usuário encontrado
                        </h3>
                        <p class="text-gray-500 text-center mb-6">
                            {{ searchQuery ? 'Tente ajustar sua busca' : 'Comece criando seu primeiro usuário' }}
                        </p>
                        <BaseButton v-if="canCreate && !searchQuery" @click="openCreateModal">
                            <PlusIcon class="w-5 h-5" />
                            Criar Primeiro Usuário
                        </BaseButton>
                    </div>

                    <!-- Mobile/Tablet Cards -->
                    <div v-else class="xl:hidden">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 p-4">
                            <div
                                v-for="user in filteredUsers"
                                :key="'card-' + user.id"
                                class="bg-white dark:bg-gray-800 rounded-xl p-4 transition-all duration-200 border border-gray-200 dark:border-gray-700"
                            >
                                <!-- Header: Avatar + Name -->
                                <div class="flex items-start gap-3 mb-3">
                                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-primary-500 to-secondary-600 flex items-center justify-center text-white font-semibold flex-shrink-0">
                                        {{ user.name.charAt(0).toUpperCase() }}
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="font-bold text-gray-900 dark:text-gray-100 truncate">{{ user.name }}</p>
                                        <p class="text-sm text-gray-500 dark:text-gray-400 truncate">{{ user.email }}</p>
                                        <p class="text-xs text-gray-400 mt-0.5">
                                            {{ user.is_employee ? 'Funcionário' : (user.is_first_user ? 'Proprietário' : 'Usuário') }}
                                        </p>
                                    </div>
                                </div>

                                <!-- Stats -->
                                <div class="grid grid-cols-2 gap-3 text-sm mb-3">
                                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-2.5">
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Permissões</p>
                                        <div class="flex items-center gap-1">
                                            <ShieldCheckIcon class="w-4 h-4 text-primary-600" />
                                            <span class="font-semibold text-gray-900 dark:text-gray-100">{{ getPermissionsCount(user) }}</span>
                                        </div>
                                    </div>
                                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-2.5">
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Criado em</p>
                                        <p class="font-semibold text-gray-900 dark:text-gray-100">{{ formatDate(user.created_at) }}</p>
                                    </div>
                                </div>

                                <!-- Actions -->
                                <div class="flex items-center gap-2 pt-3 border-t border-gray-100 dark:border-gray-700">
                                    <button
                                        v-if="canEdit"
                                        @click="openEditModal(user.id)"
                                        class="flex-1 p-2 rounded-lg text-gray-600 dark:text-gray-400 bg-gray-50 dark:bg-gray-700/50 hover:bg-primary-50 dark:hover:bg-primary-900/30 hover:text-primary-600 transition-colors text-sm flex items-center justify-center gap-1"
                                    >
                                        <PencilIcon class="w-4 h-4" />
                                        Editar
                                    </button>
                                    <button
                                        v-if="canDelete && user.id !== authStore.user?.id"
                                        @click="confirmDelete(user)"
                                        class="flex-1 p-2 rounded-lg text-gray-600 dark:text-gray-400 bg-gray-50 dark:bg-gray-700/50 hover:bg-danger-50 dark:hover:bg-danger-900/30 hover:text-danger-600 transition-colors text-sm flex items-center justify-center gap-1"
                                    >
                                        <TrashIcon class="w-4 h-4" />
                                        Excluir
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Desktop Table -->
                    <div v-if="hasUsers" class="hidden xl:block overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gradient-to-r from-gray-50 to-gray-100 dark:from-gray-800 dark:to-gray-800 border-b border-gray-200 dark:border-gray-700">
                                <tr>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Usuário
                                    </th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        E-mail
                                    </th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Permissões
                                    </th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Data de Criação
                                    </th>
                                    <th class="px-6 py-4 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Ações
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <tr
                                    v-for="user in filteredUsers"
                                    :key="user.id"
                                    class="hover:bg-gradient-to-r hover:from-primary-50/50 dark:hover:from-primary-900/30 hover:to-transparent transition-all duration-200"
                                >
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-full bg-gradient-to-br from-primary-500 to-secondary-600 flex items-center justify-center text-white font-semibold">
                                                {{ user.name.charAt(0).toUpperCase() }}
                                            </div>
                                            <div>
                                                <p class="font-medium text-gray-900 dark:text-gray-100">{{ user.name }}</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                                    {{ user.is_employee ? 'Funcionário' : (user.is_first_user ? 'Proprietário' : 'Usuário') }}
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <p class="text-sm text-gray-900 dark:text-gray-100">{{ user.email }}</p>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-2">
                                            <ShieldCheckIcon class="w-4 h-4 text-primary-600" />
                                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                {{ getPermissionsCount(user) }}
                                            </span>
                                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                                {{ getPermissionsCount(user) === 1 ? 'permissão' : 'permissões' }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <p class="text-sm text-gray-900 dark:text-gray-100">{{ formatDate(user.created_at) }}</p>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center justify-end gap-2">
                                            <button
                                                v-if="canEdit"
                                                @click="openEditModal(user.id)"
                                                class="p-2 rounded-lg text-gray-600 hover:bg-primary-50 hover:text-primary-600 transition-colors"
                                                title="Editar usuário"
                                            >
                                                <PencilIcon class="w-5 h-5" />
                                            </button>
                                            <button
                                                v-if="canDelete && user.id !== authStore.user?.id"
                                                @click="confirmDelete(user)"
                                                class="p-2 rounded-lg text-gray-600 hover:bg-danger-50 hover:text-danger-600 transition-colors"
                                                title="Excluir usuário"
                                            >
                                                <TrashIcon class="w-5 h-5" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div v-if="hasUsers && (userStore.totalPages > 1 || userStore.total > 0)" class="px-4 sm:px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                        <div class="flex flex-col sm:flex-row items-center justify-between gap-3">
                            <div class="flex flex-col sm:flex-row items-center gap-3 sm:gap-4 w-full sm:w-auto">
                                <p class="text-sm text-gray-600 dark:text-gray-400 text-center sm:text-left">
                                    <span class="hidden sm:inline">Mostrando </span>{{ filteredUsers.length }} de {{ userStore.total }}
                                </p>
                                <select
                                    :value="userStore.perPage"
                                    @change="userStore.setPerPage(Number($event.target.value), searchQuery)"
                                    class="px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 cursor-pointer"
                                >
                                    <option v-for="option in userStore.perPageOptions" :key="option" :value="option">
                                        {{ option }}/pág
                                    </option>
                                </select>
                            </div>
                            <div class="flex items-center gap-2">
                                <BaseButton
                                    variant="secondary"
                                    size="sm"
                                    @click="handlePageChange(userStore.currentPage - 1)"
                                    :disabled="userStore.currentPage === 1"
                                >
                                    <ChevronLeftIcon class="w-4 h-4" />
                                </BaseButton>
                                <span class="text-sm font-medium text-gray-900 dark:text-gray-100 px-2">
                                    {{ userStore.currentPage }}/{{ userStore.totalPages }}
                                </span>
                                <BaseButton
                                    variant="secondary"
                                    size="sm"
                                    @click="handlePageChange(userStore.currentPage + 1)"
                                    :disabled="userStore.currentPage === userStore.totalPages"
                                >
                                    <ChevronRightIcon class="w-4 h-4" />
                                </BaseButton>
                            </div>
                        </div>
                    </div>
                </BaseCard>
            </div>
        </div>

        <!-- User Form Modal -->
        <UserFormModal
            :show="showUserModal"
            :user-id="selectedUserId"
            @close="showUserModal = false"
            @saved="handleModalSaved"
        />

        <!-- Delete Confirmation Modal -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition ease-out duration-200"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition ease-in duration-150"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="showDeleteConfirm"
                    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/50 backdrop-blur-sm"
                    @click="cancelDelete"
                >
                    <div
                        class="bg-white rounded-2xl shadow-2xl p-6 max-w-md w-full"
                        @click.stop
                    >
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-12 h-12 rounded-full bg-danger-100 flex items-center justify-center">
                                <TrashIcon class="w-6 h-6 text-danger-600" />
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                Confirmar Exclusão
                            </h3>
                        </div>
                        <p class="text-gray-600 mb-6">
                            Tem certeza que deseja excluir o usuário
                            <strong>{{ userToDelete?.name }}</strong>?
                            Esta ação não pode ser desfeita.
                        </p>
                        <div class="flex justify-end gap-3">
                            <BaseButton
                                variant="secondary"
                                @click="cancelDelete"
                            >
                                Cancelar
                            </BaseButton>
                            <BaseButton
                                variant="danger"
                                @click="handleDelete"
                                :loading="userStore.isLoading"
                            >
                                Excluir
                            </BaseButton>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>
