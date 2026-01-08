<!--
  EXEMPLO: Uso dos novos padrões em componentes Vue

  Este arquivo demonstra como usar as stores migradas em componentes Vue 3
  com Composition API e TypeScript.

  IMPORTANTE: Este é apenas um exemplo de referência.
  Não importe este arquivo em código de produção.
-->

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useAuthStore } from '@/stores/authStore';
import { useDashboardStore } from '@/stores/dashboardStore';
import { useNotificationStore } from '@/stores/notificationStore';
import { cancelAllRequests } from '@/services/api';
import type { Result } from '@/utils/apiHelpers';

/**
 * EXEMPLO 1: Uso básico de store com error handling
 */
const authStore = useAuthStore();
const notificationStore = useNotificationStore();

const email = ref('');
const password = ref('');
const isLoggingIn = ref(false);

async function handleLogin() {
  isLoggingIn.value = true;

  const result = await authStore.login({
    email: email.value,
    password: password.value,
  });

  isLoggingIn.value = false;

  if (result.success) {
    // Sucesso - TypeScript sabe que result.data existe
    notificationStore.success(`Bem-vindo, ${result.data.name}!`);
    // Redirecionar para dashboard
  } else {
    // Erro - TypeScript sabe que result.error existe
    notificationStore.error(result.error.message);

    // Mostrar erros de validação se existirem
    if (result.error.errors) {
      // result.error.errors é Record<string, string[]>
      Object.entries(result.error.errors).forEach(([field, messages]) => {
        messages.forEach(message => {
          notificationStore.error(`${field}: ${message}`);
        });
      });
    }
  }
}

/**
 * EXEMPLO 2: Fetch de dados ao montar componente
 */
const dashboardStore = useDashboardStore();

onMounted(async () => {
  // Buscar todos os dados do dashboard
  await dashboardStore.fetchAllData();

  // Ou buscar apenas estatísticas
  const result = await dashboardStore.fetchStats();

  if (result.success) {
    console.log('Stats loaded:', result.data);
  } else {
    console.error('Failed to load stats:', result.error.message);
  }
});

/**
 * EXEMPLO 3: Cancelar requests ao desmontar componente
 */
onUnmounted(() => {
  // Cancelar todos os requests pendentes para evitar memory leaks
  cancelAllRequests('Component unmounted');
});

/**
 * EXEMPLO 4: Search com debounce automático via request cancellation
 */
const searchQuery = ref('');
const searchResults = ref<any[]>([]);
const isSearching = ref(false);

// Watch searchQuery e fazer request (requests antigos são cancelados automaticamente)
watch(searchQuery, async (newQuery) => {
  if (!newQuery || newQuery.length < 2) {
    searchResults.value = [];
    return;
  }

  isSearching.value = true;

  // createCancelableRequest cancela automaticamente requests anteriores com a mesma key
  await dashboardStore.searchProducts?.(newQuery);

  isSearching.value = false;
});

/**
 * EXEMPLO 5: Update com validação de erros
 */
const productName = ref('');
const productPrice = ref(0);
const validationErrors = ref<Record<string, string[]>>({});

async function handleUpdateProduct(productId: number) {
  // Limpar erros anteriores
  validationErrors.value = {};

  const result = await dashboardStore.updateProduct?.(productId, {
    name: productName.value,
    price: productPrice.value,
  });

  if (result.success) {
    notificationStore.success('Produto atualizado com sucesso!');
    // Fechar modal ou voltar para lista
  } else {
    // Erro geral
    notificationStore.error(result.error.message);

    // Erros de validação (422)
    if (result.error.errors) {
      validationErrors.value = result.error.errors;
    }
  }
}

/**
 * EXEMPLO 6: Operação pesada com feedback de progresso
 */
const isSyncing = ref(false);
const syncProgress = ref(0);

async function handleSync() {
  isSyncing.value = true;
  syncProgress.value = 0;

  const result = await dashboardStore.syncData?.();

  if (result.success) {
    notificationStore.success('Sincronização concluída!');
  } else {
    notificationStore.error(`Erro na sincronização: ${result.error.message}`);
  }

  isSyncing.value = false;
  syncProgress.value = 100;
}

/**
 * EXEMPLO 7: Atualização de filtros com invalidação de cache
 */
function handleFilterChange(newFilters: any) {
  // setFilters limpa o cache automaticamente
  dashboardStore.setFilters(newFilters);

  // Recarregar dados com novos filtros
  dashboardStore.fetchAllData();
}

/**
 * EXEMPLO 8: Computed que reage a mudanças na store
 */
const hasError = computed(() => !!dashboardStore.error);
const isLoading = computed(() => dashboardStore.isLoading);
const totalRevenue = computed(() => dashboardStore.stats?.total_revenue || 0);

/**
 * EXEMPLO 9: Formulário com submit e tratamento de erro
 */
const formData = ref({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
});
const formErrors = ref<Record<string, string[]>>({});
const isSubmitting = ref(false);

async function handleSubmit() {
  // Limpar erros
  formErrors.value = {};
  isSubmitting.value = true;

  const result = await authStore.register(formData.value);

  isSubmitting.value = false;

  if (result.success) {
    notificationStore.success('Conta criada com sucesso!');
    // Redirecionar
  } else {
    // Erro geral
    notificationStore.error(result.error.message);

    // Erros de validação
    if (result.error.errors) {
      formErrors.value = result.error.errors;
    }
  }
}

/**
 * EXEMPLO 10: Polling com cleanup
 */
const pollingInterval = ref<number | null>(null);

onMounted(() => {
  // Iniciar polling a cada 30 segundos
  pollingInterval.value = window.setInterval(() => {
    dashboardStore.fetchStats();
  }, 30000);
});

onUnmounted(() => {
  // Limpar polling ao desmontar
  if (pollingInterval.value) {
    clearInterval(pollingInterval.value);
  }
});

/**
 * EXEMPLO 11: Delete com confirmação
 */
async function handleDelete(productId: number) {
  const confirmed = confirm('Tem certeza que deseja deletar este produto?');

  if (!confirmed) return;

  const result = await dashboardStore.deleteProduct?.(productId);

  if (result.success) {
    notificationStore.success('Produto deletado com sucesso!');
  } else {
    notificationStore.error(`Erro ao deletar: ${result.error.message}`);
  }
}

/**
 * EXEMPLO 12: Retry manual de operação falhada
 */
const lastFailedOperation = ref<(() => Promise<any>) | null>(null);

async function retryLastOperation() {
  if (!lastFailedOperation.value) return;

  const result = await lastFailedOperation.value();

  if (result.success) {
    notificationStore.success('Operação concluída com sucesso!');
    lastFailedOperation.value = null;
  } else {
    notificationStore.error('Operação falhou novamente.');
  }
}
</script>

<template>
  <div class="example-component">
    <!-- EXEMPLO 1: Login form -->
    <section v-if="!authStore.isAuthenticated" class="login-section">
      <h2>Login</h2>
      <form @submit.prevent="handleLogin">
        <input
          v-model="email"
          type="email"
          placeholder="Email"
          :disabled="isLoggingIn"
        />
        <input
          v-model="password"
          type="password"
          placeholder="Senha"
          :disabled="isLoggingIn"
        />
        <button type="submit" :disabled="isLoggingIn">
          {{ isLoggingIn ? 'Entrando...' : 'Entrar' }}
        </button>
      </form>
    </section>

    <!-- EXEMPLO 2: Dashboard com loading state -->
    <section v-else class="dashboard-section">
      <h2>Dashboard</h2>

      <!-- Loading state -->
      <div v-if="isLoading" class="loading">
        Carregando...
      </div>

      <!-- Error state -->
      <div v-else-if="hasError" class="error">
        {{ dashboardStore.error }}
        <button @click="dashboardStore.fetchAllData()">
          Tentar Novamente
        </button>
      </div>

      <!-- Success state -->
      <div v-else class="stats">
        <div class="stat-card">
          <h3>Receita Total</h3>
          <p>{{ totalRevenue }}</p>
        </div>
        <!-- Mais cards... -->
      </div>
    </section>

    <!-- EXEMPLO 3: Search com debounce automático -->
    <section class="search-section">
      <h2>Buscar Produtos</h2>
      <input
        v-model="searchQuery"
        type="search"
        placeholder="Digite para buscar..."
      />
      <div v-if="isSearching" class="loading">Buscando...</div>
      <ul v-else-if="searchResults.length > 0">
        <li v-for="result in searchResults" :key="result.id">
          {{ result.name }}
        </li>
      </ul>
    </section>

    <!-- EXEMPLO 4: Formulário com erros de validação -->
    <section class="form-section">
      <h2>Cadastro</h2>
      <form @submit.prevent="handleSubmit">
        <div class="form-group">
          <input
            v-model="formData.name"
            type="text"
            placeholder="Nome"
            :class="{ error: formErrors.name }"
          />
          <span v-if="formErrors.name" class="error-message">
            {{ formErrors.name[0] }}
          </span>
        </div>

        <div class="form-group">
          <input
            v-model="formData.email"
            type="email"
            placeholder="Email"
            :class="{ error: formErrors.email }"
          />
          <span v-if="formErrors.email" class="error-message">
            {{ formErrors.email[0] }}
          </span>
        </div>

        <button type="submit" :disabled="isSubmitting">
          {{ isSubmitting ? 'Criando conta...' : 'Criar Conta' }}
        </button>
      </form>
    </section>

    <!-- EXEMPLO 5: Operação pesada com progresso -->
    <section class="sync-section">
      <h2>Sincronização</h2>
      <button @click="handleSync" :disabled="isSyncing">
        {{ isSyncing ? 'Sincronizando...' : 'Sincronizar Dados' }}
      </button>
      <div v-if="isSyncing" class="progress-bar">
        <div class="progress" :style="{ width: `${syncProgress}%` }"></div>
      </div>
    </section>

    <!-- EXEMPLO 6: Filtros com cache invalidation -->
    <section class="filters-section">
      <h2>Filtros</h2>
      <select @change="handleFilterChange({ period: $event.target.value })">
        <option value="last_7_days">Últimos 7 dias</option>
        <option value="last_30_days">Últimos 30 dias</option>
        <option value="last_90_days">Últimos 90 dias</option>
      </select>
    </section>

    <!-- EXEMPLO 7: Retry de operação falhada -->
    <section v-if="lastFailedOperation" class="retry-section">
      <div class="error-banner">
        Operação falhou.
        <button @click="retryLastOperation">Tentar Novamente</button>
      </div>
    </section>
  </div>
</template>

<style scoped>
/* Estilos de exemplo - adapte conforme seu design system */
.example-component {
  max-width: 800px;
  margin: 0 auto;
  padding: 2rem;
}

section {
  margin-bottom: 2rem;
  padding: 1.5rem;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
}

.loading {
  text-align: center;
  padding: 2rem;
  color: #6b7280;
}

.error {
  padding: 1rem;
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 4px;
  color: #dc2626;
}

.form-group {
  margin-bottom: 1rem;
}

.form-group input {
  width: 100%;
  padding: 0.5rem;
  border: 1px solid #d1d5db;
  border-radius: 4px;
}

.form-group input.error {
  border-color: #dc2626;
}

.error-message {
  display: block;
  margin-top: 0.25rem;
  font-size: 0.875rem;
  color: #dc2626;
}

button {
  padding: 0.5rem 1rem;
  background: #3b82f6;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
}

button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.progress-bar {
  width: 100%;
  height: 8px;
  background: #e5e7eb;
  border-radius: 4px;
  overflow: hidden;
  margin-top: 1rem;
}

.progress {
  height: 100%;
  background: #3b82f6;
  transition: width 0.3s ease;
}

.error-banner {
  padding: 1rem;
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 4px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
</style>
