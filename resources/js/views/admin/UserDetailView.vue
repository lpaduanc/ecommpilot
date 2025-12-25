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
            <div class="flex items-center gap-6">
                <div class="w-20 h-20 rounded-2xl bg-gradient-to-br from-primary-400 to-primary-600 flex items-center justify-center">
                    <UserCircleIcon class="w-10 h-10 text-white" />
                </div>
                <div>
                    <h1 class="text-2xl font-display font-bold text-gray-900">{{ user.name }}</h1>
                    <p class="text-gray-500">{{ user.email }}</p>
                </div>
            </div>
        </BaseCard>
    </div>
</template>

