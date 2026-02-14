<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import api from '../../services/api';
import BaseCard from '../../components/common/BaseCard.vue';
import BaseButton from '../../components/common/BaseButton.vue';
import LoadingSpinner from '../../components/common/LoadingSpinner.vue';
import {
    UsersIcon,
    MagnifyingGlassIcon,
    EyeIcon,
    PencilIcon,
    PlusIcon,
} from '@heroicons/vue/24/outline';

const router = useRouter();

const users = ref([]);
const isLoading = ref(false);
const searchQuery = ref('');

async function fetchUsers() {
    isLoading.value = true;
    try {
        const response = await api.get('/admin/users', {
            params: { search: searchQuery.value },
        });
        users.value = response.data.data;
    } catch {
        // Mock data
        users.value = [
            { id: 1, name: 'Administrador', email: 'admin@plataforma.com', role: 'admin', is_active: true, created_at: '2024-01-01' },
        ];
    } finally {
        isLoading.value = false;
    }
}

function handleSearch() {
    fetchUsers();
}

function viewUser(user) {
    router.push({ name: 'admin-user-detail', params: { id: user.id } });
}

function formatDate(date) {
    return new Date(date).toLocaleDateString('pt-BR');
}

function getRoleBadge(role) {
    return role === 'admin' ? 'badge-primary' : 'badge-gray';
}

function getRoleLabel(role) {
    return role === 'admin' ? 'Administrador' : 'Cliente';
}

onMounted(() => {
    fetchUsers();
});
</script>

<template>
    <div class="space-y-6">
        <!-- Page Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-xl sm:text-2xl lg:text-3xl font-display font-bold text-gray-900 dark:text-gray-100 flex items-center gap-3">
                    <UsersIcon class="w-8 sm:w-10 lg:w-12 h-8 sm:h-10 lg:h-12 text-primary-500" />
                    Usuários
                </h1>
                <p class="text-gray-500 dark:text-gray-400 mt-1 text-sm sm:text-base">Gerenciamento de usuários do sistema</p>
            </div>
            <BaseButton class="w-full sm:w-auto">
                <PlusIcon class="w-4 h-4" />
                Novo Usuário
            </BaseButton>
        </div>

        <!-- Search -->
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-4">
            <div class="flex-1 sm:max-w-md">
                <div class="relative">
                    <MagnifyingGlassIcon class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
                    <input
                        v-model="searchQuery"
                        @keyup.enter="handleSearch"
                        type="text"
                        placeholder="Buscar por nome ou e-mail..."
                        class="input pl-12"
                    />
                </div>
            </div>
        </div>

        <!-- Table -->
        <BaseCard padding="none">
            <div v-if="isLoading" class="flex items-center justify-center py-20">
                <LoadingSpinner size="lg" class="text-primary-500" />
            </div>

            <div v-else-if="users.length > 0" class="overflow-x-auto">
                <table class="w-full min-w-[700px]">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="text-left px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                Usuário
                            </th>
                            <th class="text-left px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                Perfil
                            </th>
                            <th class="text-left px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                Status
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
                            v-for="user in users"
                            :key="user.id"
                            class="hover:bg-gray-50 transition-colors"
                        >
                            <td class="px-6 py-4">
                                <div>
                                    <p class="font-medium text-gray-900">{{ user.name }}</p>
                                    <p class="text-sm text-gray-500">{{ user.email }}</p>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span :class="['badge', getRoleBadge(user.role)]">
                                    {{ getRoleLabel(user.role) }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <span :class="[
                                    'badge',
                                    user.is_active ? 'badge-success' : 'badge-danger'
                                ]">
                                    {{ user.is_active ? 'Ativo' : 'Inativo' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                {{ formatDate(user.created_at) }}
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <BaseButton variant="ghost" size="sm" @click="viewUser(user)">
                                        <EyeIcon class="w-4 h-4" />
                                    </BaseButton>
                                    <BaseButton variant="ghost" size="sm">
                                        <PencilIcon class="w-4 h-4" />
                                    </BaseButton>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-else class="text-center py-20">
                <UsersIcon class="w-16 h-16 text-gray-300 mx-auto mb-4" />
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Nenhum usuário encontrado</h3>
            </div>
        </BaseCard>
    </div>
</template>

