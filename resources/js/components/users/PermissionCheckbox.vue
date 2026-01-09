<script setup>
import { computed } from 'vue';

const props = defineProps({
    permission: {
        type: String,
        required: true,
    },
    modelValue: {
        type: Boolean,
        default: false,
    },
    label: {
        type: String,
        required: true,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue']);

const isChecked = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
});

const permissionLabel = computed(() => {
    // Formata o nome da permissão (ex: "users.view" -> "Visualizar")
    const action = props.permission.split('.')[1];
    const labels = {
        view: 'Visualizar',
        create: 'Criar',
        edit: 'Editar',
        delete: 'Excluir',
        manage: 'Gerenciar',
        use: 'Usar',
        request: 'Solicitar',
        access: 'Acessar',
    };
    return labels[action] || action;
});
</script>

<template>
    <label
        :class="[
            'flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition-all duration-200',
            modelValue
                ? 'border-primary-300 bg-primary-50'
                : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 hover:border-gray-300 hover:bg-gray-50',
            disabled ? 'opacity-50 cursor-not-allowed' : ''
        ]"
    >
        <input
            type="checkbox"
            v-model="isChecked"
            :disabled="disabled"
            class="w-4 h-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500 disabled:cursor-not-allowed"
        />
        <div class="flex-1 min-w-0">
            <p
                :class="[
                    'text-sm font-medium truncate',
                    modelValue ? 'text-primary-900' : 'text-gray-900'
                ]"
            >
                {{ label || permissionLabel }}
            </p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ permission }}</p>
        </div>
    </label>
</template>
