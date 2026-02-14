<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import BaseCard from '../../components/common/BaseCard.vue';
import BaseButton from '../../components/common/BaseButton.vue';
import { ArrowLeftIcon, UserCircleIcon } from '@heroicons/vue/24/outline';

const route = useRoute();
const router = useRouter();

const user = ref(null);

function goBack() {
    router.push({ name: 'admin-users' });
}

onMounted(() => {
    // Mock user data
    user.value = {
        id: route.params.id,
        name: 'Administrador',
        email: 'admin@plataforma.com',
        role: 'admin',
        is_active: true,
        created_at: '2024-01-01T00:00:00Z',
    };
});
</script>

<template>
    <div class="space-y-6">
        <BaseButton variant="ghost" @click="goBack">
            <ArrowLeftIcon class="w-4 h-4" />
            Voltar
        </BaseButton>

        <BaseCard v-if="user" padding="lg">
            <div class="flex flex-col sm:flex-row items-center sm:items-start gap-4 sm:gap-6">
                <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-2xl bg-gradient-to-br from-primary-400 to-primary-600 flex items-center justify-center flex-shrink-0">
                    <UserCircleIcon class="w-8 h-8 sm:w-10 sm:h-10 text-white" />
                </div>
                <div class="text-center sm:text-left min-w-0">
                    <h1 class="text-xl sm:text-2xl font-display font-bold text-gray-900 dark:text-gray-100">{{ user.name }}</h1>
                    <p class="text-gray-500 dark:text-gray-400">{{ user.email }}</p>
                </div>
            </div>
        </BaseCard>
    </div>
</template>

