import { defineStore } from 'pinia';
import { ref } from 'vue';
import api from '../services/api';

export const useUserManagementStore = defineStore('userManagement', () => {
    const users = ref([]);
    const currentUser = ref(null);
    const permissions = ref([]);
    const isLoading = ref(false);
    const error = ref(null);

    // Pagination
    const currentPage = ref(1);
    const totalPages = ref(1);
    const perPage = ref(15);
    const total = ref(0);

    async function fetchUsers(page = 1, search = '') {
        isLoading.value = true;
        error.value = null;

        try {
            const response = await api.get('/users', {
                params: {
                    page,
                    per_page: perPage.value,
                    search,
                },
            });

            users.value = response.data.data;
            currentPage.value = response.data.current_page;
            totalPages.value = response.data.last_page;
            total.value = response.data.total;

            return { success: true };
        } catch (err) {
            error.value = err.response?.data?.message || 'Erro ao carregar usuários';
            return { success: false, message: error.value };
        } finally {
            isLoading.value = false;
        }
    }

    async function fetchUser(id) {
        isLoading.value = true;
        error.value = null;

        try {
            const response = await api.get(`/users/${id}`);
            currentUser.value = response.data.user;
            return { success: true, user: response.data.user };
        } catch (err) {
            error.value = err.response?.data?.message || 'Erro ao carregar usuário';
            return { success: false, message: error.value };
        } finally {
            isLoading.value = false;
        }
    }

    async function createUser(userData) {
        isLoading.value = true;
        error.value = null;

        try {
            const response = await api.post('/users', userData);

            // Adiciona o novo usuário à lista
            users.value.unshift(response.data.user);

            return { success: true, user: response.data.user };
        } catch (err) {
            error.value = err.response?.data?.message || 'Erro ao criar usuário';
            return {
                success: false,
                message: error.value,
                errors: err.response?.data?.errors || {},
            };
        } finally {
            isLoading.value = false;
        }
    }

    async function updateUser(id, userData) {
        isLoading.value = true;
        error.value = null;

        try {
            const response = await api.put(`/users/${id}`, userData);

            // Atualiza o usuário na lista
            const index = users.value.findIndex(u => u.id === id);
            if (index !== -1) {
                users.value[index] = response.data.user;
            }

            return { success: true, user: response.data.user };
        } catch (err) {
            error.value = err.response?.data?.message || 'Erro ao atualizar usuário';
            return {
                success: false,
                message: error.value,
                errors: err.response?.data?.errors || {},
            };
        } finally {
            isLoading.value = false;
        }
    }

    async function deleteUser(id) {
        isLoading.value = true;
        error.value = null;

        try {
            await api.delete(`/users/${id}`);

            // Remove o usuário da lista
            users.value = users.value.filter(u => u.id !== id);

            return { success: true };
        } catch (err) {
            error.value = err.response?.data?.message || 'Erro ao excluir usuário';
            return { success: false, message: error.value };
        } finally {
            isLoading.value = false;
        }
    }

    async function fetchPermissions() {
        isLoading.value = true;
        error.value = null;

        try {
            const response = await api.get('/users/permissions');
            permissions.value = response.data.permissions;
            return { success: true };
        } catch (err) {
            error.value = err.response?.data?.message || 'Erro ao carregar permissões';
            return { success: false, message: error.value };
        } finally {
            isLoading.value = false;
        }
    }

    function clearCurrentUser() {
        currentUser.value = null;
    }

    function clearError() {
        error.value = null;
    }

    return {
        users,
        currentUser,
        permissions,
        isLoading,
        error,
        currentPage,
        totalPages,
        perPage,
        total,
        fetchUsers,
        fetchUser,
        createUser,
        updateUser,
        deleteUser,
        fetchPermissions,
        clearCurrentUser,
        clearError,
    };
});
