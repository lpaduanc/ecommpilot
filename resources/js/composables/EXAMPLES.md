# Exemplos de Uso dos Composables

Este arquivo contém exemplos práticos de como usar os composables em componentes Vue reais do projeto ecommpilot.

## Exemplo 1: Dashboard - Exibindo Estatísticas Formatadas

```vue
<!-- resources/js/views/DashboardView.vue -->
<script setup lang="ts">
import { computed } from 'vue';
import { useDashboardStore } from '@/stores/dashboardStore';
import { useFormatters } from '@/composables';
import StatCard from '@/components/dashboard/StatCard.vue';

const dashboardStore = useDashboardStore();
const { formatCurrency, formatPercentage } = useFormatters();

const stats = computed(() => dashboardStore.stats);

const statCards = computed(() => {
  if (!stats.value) return [];

  return [
    {
      title: 'Receita Total',
      value: formatCurrency(stats.value.total_revenue || 0),
      change: stats.value.revenue_change
        ? formatPercentage(stats.value.revenue_change)
        : null,
      icon: 'currency-dollar',
      color: 'primary',
    },
    {
      title: 'Total de Pedidos',
      value: stats.value.total_orders?.toString() || '0',
      change: stats.value.orders_change
        ? formatPercentage(stats.value.orders_change)
        : null,
      icon: 'shopping-cart',
      color: 'success',
    },
    {
      title: 'Ticket Médio',
      value: formatCurrency(stats.value.average_ticket || 0),
      icon: 'chart-bar',
      color: 'info',
    },
    {
      title: 'Taxa de Conversão',
      value: formatPercentage(stats.value.conversion_rate || 0),
      icon: 'arrow-trending-up',
      color: 'warning',
    },
  ];
});
</script>

<template>
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
    <StatCard
      v-for="(stat, index) in statCards"
      :key="index"
      :title="stat.title"
      :value="stat.value"
      :change="stat.change"
      :icon="stat.icon"
      :color="stat.color"
    />
  </div>
</template>
```

## Exemplo 2: Formulário de Login com Validação

```vue
<!-- resources/js/views/auth/LoginView.vue -->
<script setup lang="ts">
import { ref, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/authStore';
import { useValidation } from '@/composables';
import { useNotificationStore } from '@/stores/notificationStore';

const router = useRouter();
const authStore = useAuthStore();
const notificationStore = useNotificationStore();
const { isValidEmail } = useValidation();

const email = ref('');
const password = ref('');
const isLoading = ref(false);

// Validação reativa de email
const emailError = computed(() => {
  if (!email.value) return null;
  return isValidEmail(email.value) ? null : 'Email inválido';
});

// Validação de formulário completo
const isFormValid = computed(() => {
  return email.value &&
         password.value &&
         isValidEmail(email.value) &&
         password.value.length >= 6;
});

const handleSubmit = async () => {
  if (!isFormValid.value) {
    notificationStore.error('Por favor, preencha todos os campos corretamente');
    return;
  }

  isLoading.value = true;

  const result = await authStore.login({
    email: email.value,
    password: password.value,
  });

  isLoading.value = false;

  if (result.success) {
    notificationStore.success('Login realizado com sucesso!');
    router.push({ name: 'dashboard' });
  } else {
    notificationStore.error(result.message || 'Erro ao fazer login');
  }
};
</script>

<template>
  <form @submit.prevent="handleSubmit" class="space-y-4">
    <!-- Campo Email -->
    <div>
      <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
        Email
      </label>
      <input
        id="email"
        v-model="email"
        type="email"
        autocomplete="email"
        placeholder="seu@email.com"
        class="w-full px-4 py-2 border rounded-lg"
        :class="{ 'border-red-500': emailError }"
      />
      <p v-if="emailError" class="mt-1 text-sm text-red-600">
        {{ emailError }}
      </p>
    </div>

    <!-- Campo Senha -->
    <div>
      <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
        Senha
      </label>
      <input
        id="password"
        v-model="password"
        type="password"
        autocomplete="current-password"
        placeholder="••••••••"
        class="w-full px-4 py-2 border rounded-lg"
      />
      <p v-if="password && password.length < 6" class="mt-1 text-sm text-red-600">
        A senha deve ter no mínimo 6 caracteres
      </p>
    </div>

    <!-- Botão Submit -->
    <button
      type="submit"
      :disabled="!isFormValid || isLoading"
      class="w-full px-4 py-2 bg-primary-600 text-white rounded-lg disabled:opacity-50"
    >
      {{ isLoading ? 'Entrando...' : 'Entrar' }}
    </button>
  </form>
</template>
```

## Exemplo 3: Chat com Scroll Automático e Sanitização

```vue
<!-- resources/js/components/chat/ChatMessages.vue -->
<script setup lang="ts">
import { ref, watch, computed, onMounted } from 'vue';
import { useChatStore } from '@/stores/chatStore';
import { useScroll, useSanitizeArray } from '@/composables';

const chatStore = useChatStore();
const chatContainer = ref<HTMLElement | null>(null);
const { scrollToBottom } = useScroll();

// Extrair conteúdo das mensagens
const messagesContent = computed(() =>
  chatStore.messages.map(m => m.content)
);

// Sanitizar todas as mensagens
const { sanitized: sanitizedMessages } = useSanitizeArray(messagesContent);

// Scroll automático ao receber nova mensagem
watch(
  () => chatStore.messages.length,
  () => {
    scrollToBottom(chatContainer);
  }
);

// Scroll inicial ao montar
onMounted(() => {
  scrollToBottom(chatContainer);
});
</script>

<template>
  <div
    ref="chatContainer"
    class="chat-messages overflow-y-auto h-[600px] p-4 space-y-4"
  >
    <div
      v-for="(message, index) in chatStore.messages"
      :key="message.id"
      class="message"
      :class="{
        'message-user': message.role === 'user',
        'message-assistant': message.role === 'assistant'
      }"
    >
      <!-- Avatar -->
      <div class="avatar">
        <img
          :src="message.role === 'user' ? '/images/user-avatar.png' : '/images/ai-avatar.png'"
          :alt="`Avatar ${message.role}`"
          class="w-8 h-8 rounded-full"
        />
      </div>

      <!-- Conteúdo Sanitizado -->
      <div class="message-content">
        <div class="message-header">
          <span class="font-semibold">
            {{ message.role === 'user' ? 'Você' : 'Assistente IA' }}
          </span>
          <span class="text-xs text-gray-500">
            {{ formatDate(message.created_at) }}
          </span>
        </div>

        <!-- Renderizar HTML sanitizado -->
        <div
          v-html="sanitizedMessages[index]"
          class="message-text prose prose-sm max-w-none"
        ></div>
      </div>
    </div>

    <!-- Loading indicator -->
    <div v-if="chatStore.isLoading" class="message message-assistant">
      <div class="avatar">
        <div class="w-8 h-8 rounded-full bg-primary-100 animate-pulse"></div>
      </div>
      <div class="message-content">
        <div class="flex gap-2">
          <span class="w-2 h-2 bg-primary-500 rounded-full animate-bounce"></span>
          <span class="w-2 h-2 bg-primary-500 rounded-full animate-bounce" style="animation-delay: 0.2s"></span>
          <span class="w-2 h-2 bg-primary-500 rounded-full animate-bounce" style="animation-delay: 0.4s"></span>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.message {
  @apply flex gap-3;
}

.message-user {
  @apply flex-row-reverse;
}

.message-content {
  @apply flex-1 max-w-[80%];
}

.message-user .message-content {
  @apply bg-primary-100 rounded-lg p-4;
}

.message-assistant .message-content {
  @apply bg-gray-100 rounded-lg p-4;
}

.message-header {
  @apply flex justify-between items-center mb-2;
}

.message-text :deep(p) {
  @apply mb-2;
}

.message-text :deep(code) {
  @apply bg-gray-800 text-gray-100 px-2 py-1 rounded text-sm;
}

.message-text :deep(pre) {
  @apply bg-gray-800 text-gray-100 p-4 rounded-lg overflow-x-auto;
}

.message-text :deep(ul),
.message-text :deep(ol) {
  @apply ml-4 mb-2;
}

.message-text :deep(a) {
  @apply text-primary-600 hover:text-primary-700 underline;
}
</style>
```

## Exemplo 4: Tabela de Produtos com Formatação

```vue
<!-- resources/js/views/ProductsView.vue -->
<script setup lang="ts">
import { computed } from 'vue';
import { useProductsStore } from '@/stores/productsStore';
import { useFormatters } from '@/composables';

const productsStore = useProductsStore();
const { formatCurrency, formatDate } = useFormatters();

const products = computed(() => productsStore.products);

// Helper para status de estoque
const getStockStatus = (quantity: number) => {
  if (quantity === 0) {
    return { label: 'Sem Estoque', color: 'text-red-600 bg-red-100' };
  }
  if (quantity < 10) {
    return { label: 'Estoque Baixo', color: 'text-yellow-600 bg-yellow-100' };
  }
  return { label: 'Em Estoque', color: 'text-green-600 bg-green-100' };
};

// Helper para desconto
const getDiscountPercentage = (price: number, compareAt: number | null) => {
  if (!compareAt || compareAt <= price) return null;
  const discount = ((compareAt - price) / compareAt) * 100;
  return formatPercentage(discount);
};
</script>

<template>
  <div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200">
      <thead class="bg-gray-50">
        <tr>
          <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
            Produto
          </th>
          <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
            SKU
          </th>
          <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
            Preço
          </th>
          <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
            Estoque
          </th>
          <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
            Atualizado
          </th>
        </tr>
      </thead>
      <tbody class="bg-white divide-y divide-gray-200">
        <tr
          v-for="product in products"
          :key="product.id"
          class="hover:bg-gray-50 cursor-pointer"
        >
          <!-- Nome do Produto -->
          <td class="px-6 py-4">
            <div class="flex items-center">
              <img
                v-if="product.images?.[0]"
                :src="product.images[0]"
                :alt="product.name"
                class="w-10 h-10 rounded object-cover mr-3"
              />
              <div>
                <div class="font-medium text-gray-900">{{ product.name }}</div>
                <!-- Mostrar desconto se houver -->
                <div v-if="getDiscountPercentage(product.price, product.compare_at_price)"
                     class="text-sm text-green-600">
                  {{ getDiscountPercentage(product.price, product.compare_at_price) }} OFF
                </div>
              </div>
            </div>
          </td>

          <!-- SKU -->
          <td class="px-6 py-4 text-sm text-gray-500">
            {{ product.sku || '-' }}
          </td>

          <!-- Preço -->
          <td class="px-6 py-4">
            <div class="text-sm">
              <div class="font-medium text-gray-900">
                {{ formatCurrency(product.price) }}
              </div>
              <div
                v-if="product.compare_at_price && product.compare_at_price > product.price"
                class="text-gray-500 line-through"
              >
                {{ formatCurrency(product.compare_at_price) }}
              </div>
            </div>
          </td>

          <!-- Estoque -->
          <td class="px-6 py-4">
            <span
              class="px-2 py-1 text-xs font-semibold rounded-full"
              :class="getStockStatus(product.stock_quantity).color"
            >
              {{ product.stock_quantity }} - {{ getStockStatus(product.stock_quantity).label }}
            </span>
          </td>

          <!-- Data de Atualização -->
          <td class="px-6 py-4 text-sm text-gray-500">
            {{ formatDate(product.updated_at) }}
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
```

## Exemplo 5: Análise com Sanitização de Sugestões

```vue
<!-- resources/js/components/analysis/SuggestionsList.vue -->
<script setup lang="ts">
import { computed } from 'vue';
import { useAnalysisStore } from '@/stores/analysisStore';
import { useSanitize } from '@/composables';

const analysisStore = useAnalysisStore();

const suggestions = computed(() =>
  analysisStore.currentAnalysis?.suggestions || []
);

const handleMarkAsDone = async (suggestionId: number) => {
  const analysisId = analysisStore.currentAnalysis?.id;
  if (!analysisId) return;

  await analysisStore.markSuggestionAsDone(analysisId, suggestionId);
};
</script>

<template>
  <div class="space-y-4">
    <div
      v-for="suggestion in suggestions"
      :key="suggestion.id"
      class="bg-white rounded-lg border p-4"
      :class="{
        'border-gray-200': !suggestion.is_done,
        'border-green-200 bg-green-50': suggestion.is_done
      }"
    >
      <!-- Título e Prioridade -->
      <div class="flex items-start justify-between mb-3">
        <div class="flex-1">
          <h4 class="font-semibold text-gray-900">
            {{ suggestion.title }}
          </h4>
          <span
            class="inline-block mt-1 px-2 py-1 text-xs font-medium rounded"
            :class="{
              'bg-red-100 text-red-800': suggestion.priority === 'high',
              'bg-yellow-100 text-yellow-800': suggestion.priority === 'medium',
              'bg-blue-100 text-blue-800': suggestion.priority === 'low'
            }"
          >
            {{
              suggestion.priority === 'high'
                ? 'Alta Prioridade'
                : suggestion.priority === 'medium'
                ? 'Média Prioridade'
                : 'Baixa Prioridade'
            }}
          </span>
        </div>

        <!-- Checkbox -->
        <input
          type="checkbox"
          :checked="suggestion.is_done"
          @change="handleMarkAsDone(suggestion.id)"
          class="w-5 h-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
        />
      </div>

      <!-- Descrição Sanitizada -->
      <SuggestionDescription :description="suggestion.description" />

      <!-- Impacto Esperado -->
      <div
        v-if="suggestion.expected_impact"
        class="mt-3 p-3 bg-blue-50 rounded-lg"
      >
        <p class="text-sm font-medium text-blue-900 mb-1">Impacto Esperado:</p>
        <p class="text-sm text-blue-700">{{ suggestion.expected_impact }}</p>
      </div>
    </div>
  </div>
</template>

<!-- Componente filho para descrição sanitizada -->
<script setup lang="ts">
// SuggestionDescription.vue
import { computed } from 'vue';
import { useSanitize } from '@/composables';

const props = defineProps<{
  description: string;
}>();

const descriptionRef = computed(() => props.description);
const { sanitized } = useSanitize(descriptionRef);
</script>

<template>
  <div
    v-html="sanitized"
    class="prose prose-sm max-w-none text-gray-600"
  ></div>
</template>
```

## Exemplo 6: Validação de CPF em Formulário de Cliente

```vue
<!-- resources/js/components/customers/CustomerForm.vue -->
<script setup lang="ts">
import { ref, computed } from 'vue';
import { useValidation } from '@/composables';

const { isValidEmail, isValidCPF } = useValidation();

const formData = ref({
  name: '',
  email: '',
  cpf: '',
  phone: '',
});

// Validações reativas
const emailError = computed(() => {
  if (!formData.value.email) return null;
  return isValidEmail(formData.value.email) ? null : 'Email inválido';
});

const cpfError = computed(() => {
  if (!formData.value.cpf) return null;
  return isValidCPF(formData.value.cpf) ? null : 'CPF inválido';
});

const isFormValid = computed(() => {
  return (
    formData.value.name &&
    formData.value.email &&
    formData.value.cpf &&
    !emailError.value &&
    !cpfError.value
  );
});

// Formatar CPF enquanto digita
const formatCPF = (value: string) => {
  const numbers = value.replace(/\D/g, '');
  if (numbers.length <= 11) {
    return numbers
      .replace(/(\d{3})(\d)/, '$1.$2')
      .replace(/(\d{3})(\d)/, '$1.$2')
      .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
  }
  return value.slice(0, 14);
};

const handleCPFInput = (event: Event) => {
  const input = event.target as HTMLInputElement;
  formData.value.cpf = formatCPF(input.value);
};
</script>

<template>
  <form @submit.prevent="handleSubmit" class="space-y-4">
    <!-- Nome -->
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">
        Nome Completo
      </label>
      <input
        v-model="formData.name"
        type="text"
        required
        class="w-full px-4 py-2 border rounded-lg"
      />
    </div>

    <!-- Email -->
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">
        Email
      </label>
      <input
        v-model="formData.email"
        type="email"
        required
        class="w-full px-4 py-2 border rounded-lg"
        :class="{ 'border-red-500': emailError }"
      />
      <p v-if="emailError" class="mt-1 text-sm text-red-600">
        {{ emailError }}
      </p>
    </div>

    <!-- CPF -->
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">
        CPF
      </label>
      <input
        :value="formData.cpf"
        @input="handleCPFInput"
        type="text"
        maxlength="14"
        placeholder="000.000.000-00"
        required
        class="w-full px-4 py-2 border rounded-lg"
        :class="{ 'border-red-500': cpfError }"
      />
      <p v-if="cpfError" class="mt-1 text-sm text-red-600">
        {{ cpfError }}
      </p>
      <p v-else-if="formData.cpf && !cpfError" class="mt-1 text-sm text-green-600">
        ✓ CPF válido
      </p>
    </div>

    <!-- Submit Button -->
    <button
      type="submit"
      :disabled="!isFormValid"
      class="w-full px-4 py-2 bg-primary-600 text-white rounded-lg disabled:opacity-50"
    >
      Salvar Cliente
    </button>
  </form>
</template>
```

## Boas Práticas

1. **Sempre sanitize HTML de fontes externas** antes de usar `v-html`
2. **Valide no client e no server** - validação client-side é UX, server-side é segurança
3. **Use computed properties** para validações reativas
4. **Formate valores para exibição**, mas mantenha valores originais para cálculos
5. **Scroll automático** deve usar `nextTick` para garantir que DOM foi atualizado
6. **Teste edge cases** - valores nulos, strings vazias, números negativos
