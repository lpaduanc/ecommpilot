<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue';
import { useUserManagementStore } from '../../stores/userManagementStore';
import { useNotificationStore } from '../../stores/notificationStore';
import BaseModal from '../common/BaseModal.vue';
import BaseButton from '../common/BaseButton.vue';

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
    phone: '',
    is_active: true,
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

const title = computed(() => isEditMode.value ? 'Editar Cliente' : 'Novo Usuário');

// Estrutura de permissões agrupadas por categoria
const permissionCategories = ref([
    {
        name: 'Usuários',
        key: 'users',
        permissions: [
            { key: 'users.view', label: 'Visualizar usuários' },
            { key: 'users.create', label: 'Criar usuários' },
            { key: 'users.edit', label: 'Editar usuários' },
            { key: 'users.delete', label: 'Excluir usuários' },
        ],
    },
    {
        name: 'Dashboard',
        key: 'dashboard',
        permissions: [
            { key: 'dashboard.view', label: 'Ver dashboard' },
        ],
    },
    {
        name: 'Produtos',
        key: 'products',
        permissions: [
            { key: 'products.view', label: 'Ver produtos' },
        ],
    },
    {
        name: 'Pedidos',
        key: 'orders',
        permissions: [
            { key: 'orders.view', label: 'Ver pedidos' },
        ],
    },
    {
        name: 'Análises IA',
        key: 'analysis',
        permissions: [
            { key: 'analysis.view', label: 'Ver análises' },
            { key: 'analysis.request', label: 'Solicitar análises' },
        ],
    },
    {
        name: 'Chat IA',
        key: 'chat',
        permissions: [
            { key: 'chat.use', label: 'Usar chat IA' },
        ],
    },
    {
        name: 'Configurações',
        key: 'settings',
        permissions: [
            { key: 'settings.view', label: 'Ver configurações' },
            { key: 'settings.edit', label: 'Editar configurações' },
        ],
    },
    {
        name: 'Integrações',
        key: 'integrations',
        permissions: [
            { key: 'integrations.manage', label: 'Gerenciar integrações' },
        ],
    },
    {
        name: 'Marketing',
        key: 'marketing',
        permissions: [
            { key: 'marketing.access', label: 'Acessar marketing' },
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
    form.phone = '';
    form.is_active = true;
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
        form.phone = result.user.phone || '';
        form.is_active = result.user.is_active !== undefined ? result.user.is_active : true;
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

            <!-- Dados do Cliente -->
            <div class="space-y-4">
                <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    Dados do Cliente
                </h4>

                <!-- Linha 1: Nome + E-mail -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Nome <span class="text-red-500">*</span>
                        </label>
                        <input
                            v-model="form.name"
                            type="text"
                            placeholder="Nome completo"
                            :disabled="isLoading"
                            :class="[
                                'w-full px-4 py-2.5 rounded-lg border bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 transition-colors',
                                errors.name
                                    ? 'border-red-500 focus:border-red-500 focus:ring-red-500/20'
                                    : 'border-gray-200 dark:border-gray-600 focus:border-primary-500 dark:focus:border-primary-400 focus:ring-primary-500/20',
                                isLoading ? 'opacity-70 cursor-not-allowed' : '',
                            ]"
                        />
                        <p v-if="errors.name" class="text-sm text-red-500">{{ errors.name }}</p>
                    </div>

                    <div class="space-y-1.5">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            E-mail <span class="text-red-500">*</span>
                        </label>
                        <input
                            v-model="form.email"
                            type="email"
                            placeholder="email@exemplo.com"
                            :disabled="isLoading"
                            :class="[
                                'w-full px-4 py-2.5 rounded-lg border bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 transition-colors',
                                errors.email
                                    ? 'border-red-500 focus:border-red-500 focus:ring-red-500/20'
                                    : 'border-gray-200 dark:border-gray-600 focus:border-primary-500 dark:focus:border-primary-400 focus:ring-primary-500/20',
                                isLoading ? 'opacity-70 cursor-not-allowed' : '',
                            ]"
                        />
                        <p v-if="errors.email" class="text-sm text-red-500">{{ errors.email }}</p>
                    </div>
                </div>

                <!-- Linha 2: Telefone + Cliente ativo -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Telefone
                        </label>
                        <input
                            v-model="form.phone"
                            type="tel"
                            placeholder="(00) 00000-0000"
                            :disabled="isLoading"
                            class="w-full px-4 py-2.5 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:border-primary-500 dark:focus:border-primary-400 focus:ring-primary-500/20 transition-colors"
                            :class="isLoading ? 'opacity-70 cursor-not-allowed' : ''"
                        />
                    </div>

                    <!-- Checkbox Cliente ativo -->
                    <div class="flex items-center md:pt-7">
                        <label class="flex items-center gap-3 cursor-pointer select-none group">
                            <div class="relative flex-shrink-0">
                                <input
                                    v-model="form.is_active"
                                    type="checkbox"
                                    :disabled="isLoading"
                                    class="sr-only peer"
                                />
                                <div :class="[
                                    'w-5 h-5 rounded border-2 flex items-center justify-center transition-colors',
                                    form.is_active
                                        ? 'bg-primary-600 border-primary-600 dark:bg-primary-500 dark:border-primary-500'
                                        : 'bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-500 group-hover:border-primary-400',
                                    isLoading ? 'opacity-70' : '',
                                ]" @click="!isLoading && (form.is_active = !form.is_active)">
                                    <svg v-if="form.is_active" class="w-3 h-3 text-white" fill="none" viewBox="0 0 12 12" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2 6l3 3 5-5" />
                                    </svg>
                                </div>
                            </div>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Cliente ativo</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Permissões -->
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        Permissões
                    </h4>
                    <button
                        type="button"
                        :disabled="isLoading"
                        class="text-sm font-medium text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        @click="allSelected ? (allSelected = false) : (allSelected = true)"
                    >
                        Selecionar todas
                    </button>
                </div>

                <div class="max-h-[800px] overflow-y-auto pr-1 scrollbar-thin">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div
                            v-for="category in permissionCategories"
                            :key="category.key"
                            class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4"
                        >
                            <!-- Cabeçalho da categoria com checkbox -->
                            <label class="flex items-center gap-2.5 cursor-pointer group mb-3">
                                <div class="relative flex-shrink-0">
                                    <input
                                        type="checkbox"
                                        :checked="isCategorySelected(category)"
                                        @change="toggleCategory(category)"
                                        :disabled="isLoading"
                                        class="sr-only"
                                    />
                                    <div :class="[
                                        'w-4 h-4 rounded border-2 flex items-center justify-center transition-colors',
                                        isCategorySelected(category)
                                            ? 'bg-primary-600 border-primary-600 dark:bg-primary-500 dark:border-primary-500'
                                            : 'bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-500 group-hover:border-primary-400',
                                        isLoading ? 'opacity-70' : '',
                                    ]" @click="!isLoading && toggleCategory(category)">
                                        <svg v-if="isCategorySelected(category)" class="w-2.5 h-2.5 text-white" fill="none" viewBox="0 0 12 12" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2 6l3 3 5-5" />
                                        </svg>
                                    </div>
                                </div>
                                <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ category.name }}</span>
                            </label>

                            <!-- Sub-permissões indentadas -->
                            <div class="space-y-2 pl-6">
                                <label
                                    v-for="permission in category.permissions"
                                    :key="permission.key"
                                    class="flex items-center gap-2.5 cursor-pointer group"
                                >
                                    <div class="relative flex-shrink-0">
                                        <input
                                            type="checkbox"
                                            :checked="isPermissionSelected(permission.key)"
                                            @change="togglePermission(permission.key)"
                                            :disabled="isLoading"
                                            class="sr-only"
                                        />
                                        <div :class="[
                                            'w-3.5 h-3.5 rounded border-2 flex items-center justify-center transition-colors',
                                            isPermissionSelected(permission.key)
                                                ? 'bg-primary-600 border-primary-600 dark:bg-primary-500 dark:border-primary-500'
                                                : 'bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-500 group-hover:border-primary-400',
                                            isLoading ? 'opacity-70' : '',
                                        ]" @click="!isLoading && togglePermission(permission.key)">
                                            <svg v-if="isPermissionSelected(permission.key)" class="w-2 h-2 text-white" fill="none" viewBox="0 0 12 12" stroke="currentColor" stroke-width="3">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2 6l3 3 5-5" />
                                            </svg>
                                        </div>
                                    </div>
                                    <span class="text-sm text-gray-600 dark:text-gray-400">{{ permission.label }}</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <template #footer>
            <div class="flex items-center justify-between gap-3">
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
