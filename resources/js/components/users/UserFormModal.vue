<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue';
import { useUserManagementStore } from '../../stores/userManagementStore';
import { useNotificationStore } from '../../stores/notificationStore';
import BaseModal from '../common/BaseModal.vue';
import BaseInput from '../common/BaseInput.vue';
import BaseButton from '../common/BaseButton.vue';
import PermissionCheckbox from './PermissionCheckbox.vue';
import LoadingSpinner from '../common/LoadingSpinner.vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    userId: {
        type: Number,
        default: null,
    },
});

const emit = defineEmits(['close', 'saved']);

const userStore = useUserManagementStore();
const notificationStore = useNotificationStore();

const isLoading = ref(false);
const isLoadingPermissions = ref(false);
const isLoadingStores = ref(false);
const availableStores = ref([]);

const form = reactive({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    permissions: [],
    store_ids: [],
});

const errors = reactive({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const isEditMode = computed(() => !!props.userId);

const title = computed(() => isEditMode.value ? 'Editar Usuário' : 'Novo Usuário');

// Estrutura de permissões agrupadas por categoria
const permissionCategories = ref([
    {
        name: 'Dashboard',
        key: 'dashboard',
        permissions: [
            { key: 'dashboard.view', label: 'Visualizar' },
        ],
    },
    {
        name: 'Produtos',
        key: 'products',
        permissions: [
            { key: 'products.view', label: 'Visualizar' },
        ],
    },
    {
        name: 'Pedidos',
        key: 'orders',
        permissions: [
            { key: 'orders.view', label: 'Visualizar' },
        ],
    },
    {
        name: 'Análises IA',
        key: 'analysis',
        permissions: [
            { key: 'analysis.view', label: 'Visualizar' },
            { key: 'analysis.request', label: 'Solicitar' },
        ],
    },
    {
        name: 'Chat IA',
        key: 'chat',
        permissions: [
            { key: 'chat.use', label: 'Usar' },
        ],
    },
    {
        name: 'Configurações',
        key: 'settings',
        permissions: [
            { key: 'settings.view', label: 'Visualizar' },
            { key: 'settings.edit', label: 'Editar' },
        ],
    },
    {
        name: 'Integrações',
        key: 'integrations',
        permissions: [
            { key: 'integrations.manage', label: 'Gerenciar' },
        ],
    },
    {
        name: 'Marketing e Descontos',
        key: 'marketing',
        permissions: [
            { key: 'marketing.access', label: 'Acessar' },
        ],
    },
]);

const allPermissions = computed(() => {
    return permissionCategories.value.flatMap(cat => cat.permissions.map(p => p.key));
});

const allSelected = computed({
    get: () => form.permissions.length === allPermissions.value.length,
    set: (value) => {
        if (value) {
            form.permissions = [...allPermissions.value];
        } else {
            form.permissions = [];
        }
    },
});

function isCategorySelected(category) {
    const categoryPerms = category.permissions.map(p => p.key);
    return categoryPerms.every(perm => form.permissions.includes(perm));
}

function toggleCategory(category) {
    const categoryPerms = category.permissions.map(p => p.key);
    const isSelected = isCategorySelected(category);

    if (isSelected) {
        // Remove todas as permissões da categoria
        form.permissions = form.permissions.filter(perm => !categoryPerms.includes(perm));
    } else {
        // Adiciona todas as permissões da categoria
        categoryPerms.forEach(perm => {
            if (!form.permissions.includes(perm)) {
                form.permissions.push(perm);
            }
        });
    }
}

function isPermissionSelected(permissionKey) {
    return form.permissions.includes(permissionKey);
}

function togglePermission(permissionKey) {
    const index = form.permissions.indexOf(permissionKey);
    if (index > -1) {
        form.permissions.splice(index, 1);
    } else {
        form.permissions.push(permissionKey);
    }
}

const allStoresSelected = computed({
    get: () => availableStores.value.length > 0 && form.store_ids.length === availableStores.value.length,
    set: (value) => {
        if (value) {
            form.store_ids = availableStores.value.map(s => s.id);
        } else {
            form.store_ids = [];
        }
    },
});

function isStoreSelected(storeId) {
    return form.store_ids.includes(storeId);
}

function toggleStore(storeId) {
    const index = form.store_ids.indexOf(storeId);
    if (index > -1) {
        form.store_ids.splice(index, 1);
    } else {
        form.store_ids.push(storeId);
    }
}

function clearForm() {
    form.name = '';
    form.email = '';
    form.password = '';
    form.password_confirmation = '';
    form.permissions = [];
    form.store_ids = [];
    clearErrors();
}

function clearErrors() {
    errors.name = '';
    errors.email = '';
    errors.password = '';
    errors.password_confirmation = '';
}

function validateForm() {
    clearErrors();
    let isValid = true;

    if (!form.name.trim()) {
        errors.name = 'O nome é obrigatório';
        isValid = false;
    }

    if (!form.email.trim()) {
        errors.email = 'O e-mail é obrigatório';
        isValid = false;
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) {
        errors.email = 'E-mail inválido';
        isValid = false;
    }

    // Senha é obrigatória apenas na criação
    if (!isEditMode.value) {
        if (!form.password) {
            errors.password = 'A senha é obrigatória';
            isValid = false;
        } else if (form.password.length < 8) {
            errors.password = 'A senha deve ter pelo menos 8 caracteres';
            isValid = false;
        }

        if (form.password !== form.password_confirmation) {
            errors.password_confirmation = 'As senhas não conferem';
            isValid = false;
        }
    } else {
        // Na edição, valida apenas se a senha foi preenchida
        if (form.password) {
            if (form.password.length < 8) {
                errors.password = 'A senha deve ter pelo menos 8 caracteres';
                isValid = false;
            }
            if (form.password !== form.password_confirmation) {
                errors.password_confirmation = 'As senhas não conferem';
                isValid = false;
            }
        }
    }

    if (form.store_ids.length === 0 && availableStores.value.length > 0) {
        notificationStore.warning('Selecione pelo menos uma loja para o usuário.');
        isValid = false;
    }

    return isValid;
}

async function loadUser() {
    if (!props.userId) return;

    isLoading.value = true;
    const result = await userStore.fetchUser(props.userId);
    isLoading.value = false;

    if (result.success) {
        form.name = result.user.name;
        form.email = result.user.email;
        form.permissions = result.user.permissions || [];
        form.store_ids = result.user.store_ids || [];
    } else {
        notificationStore.error(result.message);
        emit('close');
    }
}

async function handleSubmit() {
    if (!validateForm()) return;

    isLoading.value = true;

    // Prepara os dados para envio
    const userData = {
        name: form.name,
        email: form.email,
        permissions: form.permissions,
        store_ids: form.store_ids,
    };

    // Adiciona senha apenas se foi preenchida
    if (form.password) {
        userData.password = form.password;
        userData.password_confirmation = form.password_confirmation;
    }

    let result;
    if (isEditMode.value) {
        result = await userStore.updateUser(props.userId, userData);
    } else {
        result = await userStore.createUser(userData);
    }

    isLoading.value = false;

    if (result.success) {
        notificationStore.success(
            isEditMode.value ? 'Usuário atualizado com sucesso!' : 'Usuário criado com sucesso!'
        );
        emit('saved');
        emit('close');
        clearForm();
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

function handleClose() {
    clearForm();
    emit('close');
}

// Carrega o usuário quando o modal abre em modo de edição
watch(() => props.show, async (newVal) => {
    if (newVal) {
        // Carrega as lojas disponíveis
        isLoadingStores.value = true;
        const storesResult = await userStore.fetchClientStores();
        if (storesResult.success) {
            availableStores.value = storesResult.stores;
        }
        isLoadingStores.value = false;

        if (isEditMode.value) {
            await loadUser();
        } else {
            clearForm();
        }
    }
});

onMounted(async () => {
    // Carrega a lista de permissões disponíveis se necessário
    if (!userStore.permissions || userStore.permissions.length === 0) {
        isLoadingPermissions.value = true;
        await userStore.fetchPermissions();
        isLoadingPermissions.value = false;
    }
});
</script>

<template>
    <BaseModal :show="show" :title="title" size="xl" @close="handleClose">
        <form @submit.prevent="handleSubmit" class="space-y-6">
            <!-- Informações Básicas -->
            <div class="space-y-4">
                <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100 uppercase tracking-wider">
                    Informações Básicas
                </h4>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <BaseInput
                        v-model="form.name"
                        type="text"
                        label="Nome"
                        placeholder="Nome completo"
                        :error="errors.name"
                        :disabled="isLoading"
                    />

                    <BaseInput
                        v-model="form.email"
                        type="email"
                        label="E-mail"
                        placeholder="email@exemplo.com"
                        :error="errors.email"
                        :disabled="isLoading"
                    />
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <BaseInput
                        v-model="form.password"
                        type="password"
                        label="Senha"
                        :placeholder="isEditMode ? 'Deixe em branco para manter a atual' : '••••••••'"
                        :hint="isEditMode ? 'Deixe em branco para não alterar' : 'Mínimo de 8 caracteres'"
                        :error="errors.password"
                        :disabled="isLoading"
                    />

                    <BaseInput
                        v-model="form.password_confirmation"
                        type="password"
                        label="Confirmar Senha"
                        placeholder="••••••••"
                        :error="errors.password_confirmation"
                        :disabled="isLoading"
                    />
                </div>
            </div>

            <!-- Lojas -->
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100 uppercase tracking-wider">
                        Acesso às Lojas
                    </h4>
                    <label v-if="availableStores.length > 1" class="flex items-center gap-2 text-sm font-medium text-primary-600 cursor-pointer">
                        <input
                            type="checkbox"
                            v-model="allStoresSelected"
                            :disabled="isLoading"
                            class="w-4 h-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                        />
                        Selecionar todas
                    </label>
                </div>

                <div v-if="isLoadingStores" class="flex items-center justify-center py-4">
                    <LoadingSpinner size="sm" />
                    <span class="ml-2 text-sm text-gray-500">Carregando lojas...</span>
                </div>

                <div v-else-if="availableStores.length === 0" class="text-sm text-gray-500 dark:text-gray-400 py-2">
                    Nenhuma loja cadastrada. Cadastre uma loja primeiro.
                </div>

                <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-2">
                    <label
                        v-for="store in availableStores"
                        :key="store.id"
                        :class="[
                            'flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition-all duration-200',
                            isStoreSelected(store.id)
                                ? 'border-primary-300 bg-primary-50 dark:border-primary-700 dark:bg-primary-900/30'
                                : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 hover:border-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/50',
                            isLoading ? 'opacity-50 cursor-not-allowed' : ''
                        ]"
                    >
                        <input
                            type="checkbox"
                            :checked="isStoreSelected(store.id)"
                            @change="toggleStore(store.id)"
                            :disabled="isLoading"
                            class="w-4 h-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                        />
                        <div class="flex-1 min-w-0">
                            <p :class="[
                                'text-sm font-medium truncate',
                                isStoreSelected(store.id) ? 'text-primary-900 dark:text-primary-100' : 'text-gray-900 dark:text-gray-100'
                            ]">
                                {{ store.name }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                {{ store.platform || 'Nuvemshop' }}
                            </p>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Permissões -->
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100 uppercase tracking-wider">
                        Permissões
                    </h4>
                    <label class="flex items-center gap-2 text-sm font-medium text-primary-600 cursor-pointer">
                        <input
                            type="checkbox"
                            v-model="allSelected"
                            :disabled="isLoading"
                            class="w-4 h-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                        />
                        Selecionar todas
                    </label>
                </div>

                <div class="max-h-96 overflow-y-auto pr-2 space-y-4 scrollbar-thin">
                    <div
                        v-for="category in permissionCategories"
                        :key="category.key"
                        class="bg-gray-50 dark:bg-gray-900 rounded-xl p-4"
                    >
                        <div class="flex items-center justify-between mb-3">
                            <h5 class="text-sm font-semibold text-gray-900">{{ category.name }}</h5>
                            <label class="flex items-center gap-2 text-xs font-medium text-primary-600 cursor-pointer">
                                <input
                                    type="checkbox"
                                    :checked="isCategorySelected(category)"
                                    @change="toggleCategory(category)"
                                    :disabled="isLoading"
                                    class="w-3.5 h-3.5 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                />
                                Todas
                            </label>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                            <PermissionCheckbox
                                v-for="permission in category.permissions"
                                :key="permission.key"
                                :permission="permission.key"
                                :label="permission.label"
                                :model-value="isPermissionSelected(permission.key)"
                                @update:model-value="togglePermission(permission.key)"
                                :disabled="isLoading"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <template #footer>
            <div class="flex justify-end gap-3">
                <BaseButton
                    variant="secondary"
                    @click="handleClose"
                    :disabled="isLoading"
                >
                    Cancelar
                </BaseButton>
                <BaseButton
                    @click="handleSubmit"
                    :loading="isLoading"
                >
                    {{ isEditMode ? 'Salvar Alterações' : 'Criar Usuário' }}
                </BaseButton>
            </div>
        </template>
    </BaseModal>
</template>
