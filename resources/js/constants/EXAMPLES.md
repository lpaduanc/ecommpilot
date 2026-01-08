# Exemplos Práticos de Uso das Constantes

Este arquivo contém exemplos reais de como usar as constantes no dia a dia do desenvolvimento.

## Exemplo 1: Lista de Produtos com Status de Estoque

```vue
<template>
  <div class="products-list">
    <div
      v-for="product in products"
      :key="product.id"
      class="product-card"
    >
      <h3>{{ product.name }}</h3>
      <p>Preço: R$ {{ product.price }}</p>

      <!-- Badge de estoque usando constantes -->
      <Badge
        :label="getStockConfig(product.stock).label"
        :color="getStockConfig(product.stock).color"
        :variant="getStockConfig(product.stock).variant"
      />

      <button @click="viewProduct(product.id)">
        Ver Detalhes
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import {
  getStockStatus,
  STOCK_STATUS_CONFIG,
  ROUTE_NAMES,
  API_ENDPOINTS
} from '@/constants';
import api from '@/services/api';

interface Product {
  id: number;
  name: string;
  price: number;
  stock: number;
}

const router = useRouter();
const products = ref<Product[]>([]);

// Helper para obter configuração de estoque
function getStockConfig(stock: number) {
  const status = getStockStatus(stock);
  return STOCK_STATUS_CONFIG[status];
}

// Navegar para detalhes do produto
function viewProduct(id: number) {
  router.push({
    name: ROUTE_NAMES.PRODUCTS,
    params: { id }
  });
}

// Buscar produtos da API
async function fetchProducts() {
  try {
    const response = await api.get(API_ENDPOINTS.PRODUCTS.LIST);
    products.value = response.data.data;
  } catch (error) {
    console.error('Erro ao buscar produtos:', error);
  }
}

fetchProducts();
</script>
```

## Exemplo 2: Formulário de Login com Tratamento de Erros

```vue
<template>
  <form @submit.prevent="handleLogin" class="login-form">
    <input
      v-model="email"
      type="email"
      placeholder="Email"
      required
    />
    <input
      v-model="password"
      type="password"
      placeholder="Senha"
      required
    />

    <button type="submit" :disabled="loading">
      {{ loading ? 'Entrando...' : 'Entrar' }}
    </button>

    <router-link :to="{ name: forgotPasswordRoute }">
      Esqueceu a senha?
    </router-link>
  </form>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/authStore';
import { useNotificationStore } from '@/stores/notificationStore';
import {
  ROUTE_NAMES,
  API_ENDPOINTS,
  HTTP_STATUS,
  NotificationType,
  NOTIFICATION_DURATION
} from '@/constants';
import api from '@/services/api';

const router = useRouter();
const authStore = useAuthStore();
const notificationStore = useNotificationStore();

const email = ref('');
const password = ref('');
const loading = ref(false);

// Usando constante de rota
const forgotPasswordRoute = ROUTE_NAMES.FORGOT_PASSWORD;

async function handleLogin() {
  loading.value = true;

  try {
    const response = await api.post(API_ENDPOINTS.AUTH.LOGIN, {
      email: email.value,
      password: password.value
    });

    // Armazenar token e usuário
    authStore.setUser(response.data.user);

    // Notificação de sucesso
    notificationStore.show({
      type: NotificationType.Success,
      message: 'Login realizado com sucesso!',
      duration: NOTIFICATION_DURATION.SHORT
    });

    // Redirecionar para dashboard
    router.push({ name: ROUTE_NAMES.DASHBOARD });
  } catch (error: any) {
    // Tratamento específico por status HTTP
    if (error.response) {
      const status = error.response.status;

      switch (status) {
        case HTTP_STATUS.UNAUTHORIZED:
          notificationStore.show({
            type: NotificationType.Error,
            message: 'Email ou senha inválidos',
            duration: NOTIFICATION_DURATION.MEDIUM
          });
          break;

        case HTTP_STATUS.UNPROCESSABLE_ENTITY:
          notificationStore.show({
            type: NotificationType.Warning,
            message: 'Verifique os dados informados',
            duration: NOTIFICATION_DURATION.MEDIUM
          });
          break;

        case HTTP_STATUS.TOO_MANY_REQUESTS:
          notificationStore.show({
            type: NotificationType.Warning,
            message: 'Muitas tentativas. Aguarde alguns minutos.',
            duration: NOTIFICATION_DURATION.LONG
          });
          break;

        default:
          notificationStore.show({
            type: NotificationType.Error,
            message: 'Erro ao fazer login. Tente novamente.',
            duration: NOTIFICATION_DURATION.MEDIUM
          });
      }
    } else {
      notificationStore.show({
        type: NotificationType.Error,
        message: 'Erro de conexão. Verifique sua internet.',
        duration: NOTIFICATION_DURATION.MEDIUM
      });
    }
  } finally {
    loading.value = false;
  }
}
</script>
```

## Exemplo 3: Dashboard com Múltiplas Chamadas de API

```vue
<template>
  <div class="dashboard">
    <div class="stats-grid">
      <StatCard
        title="Receita Total"
        :value="stats.totalRevenue"
        icon="currency-dollar"
      />
      <StatCard
        title="Pedidos"
        :value="stats.totalOrders"
        icon="shopping-cart"
      />
      <StatCard
        title="Produtos"
        :value="stats.totalProducts"
        icon="cube"
      />
    </div>

    <div class="charts-grid">
      <RevenueChart :data="revenueData" />
      <OrdersStatusChart :data="ordersStatusData" />
      <TopProductsChart :data="topProducts" />
    </div>

    <div class="alerts">
      <h3>Alertas de Estoque</h3>
      <div v-for="item in lowStockItems" :key="item.id" class="alert-item">
        <span>{{ item.name }}</span>
        <Badge
          :label="getStockConfig(item.stock).label"
          :color="getStockConfig(item.stock).color"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import {
  API_ENDPOINTS,
  getStockStatus,
  STOCK_STATUS_CONFIG,
  HTTP_STATUS,
  HTTP_STATUS_CATEGORY,
  NotificationType,
  NOTIFICATION_DURATION
} from '@/constants';
import api from '@/services/api';
import { useNotificationStore } from '@/stores/notificationStore';

interface Stats {
  totalRevenue: number;
  totalOrders: number;
  totalProducts: number;
}

interface LowStockItem {
  id: number;
  name: string;
  stock: number;
}

const notificationStore = useNotificationStore();

const stats = ref<Stats>({
  totalRevenue: 0,
  totalOrders: 0,
  totalProducts: 0
});

const revenueData = ref([]);
const ordersStatusData = ref([]);
const topProducts = ref([]);
const lowStockItems = ref<LowStockItem[]>([]);

function getStockConfig(stock: number) {
  const status = getStockStatus(stock);
  return STOCK_STATUS_CONFIG[status];
}

async function fetchDashboardData() {
  try {
    // Buscar múltiplos endpoints em paralelo
    const [
      statsResponse,
      revenueResponse,
      ordersStatusResponse,
      topProductsResponse,
      lowStockResponse
    ] = await Promise.all([
      api.get(API_ENDPOINTS.DASHBOARD.STATS),
      api.get(API_ENDPOINTS.DASHBOARD.REVENUE_CHART),
      api.get(API_ENDPOINTS.DASHBOARD.ORDERS_STATUS_CHART),
      api.get(API_ENDPOINTS.DASHBOARD.TOP_PRODUCTS),
      api.get(API_ENDPOINTS.DASHBOARD.LOW_STOCK)
    ]);

    // Verificar se todas as respostas foram bem-sucedidas
    const responses = [
      statsResponse,
      revenueResponse,
      ordersStatusResponse,
      topProductsResponse,
      lowStockResponse
    ];

    const allSuccess = responses.every(
      response => HTTP_STATUS_CATEGORY.isSuccess(response.status)
    );

    if (allSuccess) {
      stats.value = statsResponse.data;
      revenueData.value = revenueResponse.data;
      ordersStatusData.value = ordersStatusResponse.data;
      topProducts.value = topProductsResponse.data;
      lowStockItems.value = lowStockResponse.data;

      // Mostrar alerta se houver produtos com estoque baixo
      if (lowStockItems.value.length > 0) {
        notificationStore.show({
          type: NotificationType.Warning,
          message: `${lowStockItems.value.length} produto(s) com estoque baixo`,
          duration: NOTIFICATION_DURATION.LONG
        });
      }
    }
  } catch (error: any) {
    if (error.response) {
      const status = error.response.status;

      if (HTTP_STATUS_CATEGORY.isServerError(status)) {
        notificationStore.show({
          type: NotificationType.Error,
          message: 'Erro no servidor. Tente novamente mais tarde.',
          duration: NOTIFICATION_DURATION.PERMANENT
        });
      } else if (status === HTTP_STATUS.FORBIDDEN) {
        notificationStore.show({
          type: NotificationType.Error,
          message: 'Você não tem permissão para visualizar o dashboard.',
          duration: NOTIFICATION_DURATION.LONG
        });
      }
    }
  }
}

onMounted(() => {
  fetchDashboardData();
});
</script>
```

## Exemplo 4: Guard de Rota com Permissões

```typescript
// router/guards.ts
import { NavigationGuardNext, RouteLocationNormalized } from 'vue-router';
import { useAuthStore } from '@/stores/authStore';
import { useNotificationStore } from '@/stores/notificationStore';
import {
  ROUTE_NAMES,
  ROUTE_PERMISSIONS,
  NotificationType,
  NOTIFICATION_DURATION
} from '@/constants';

export async function authGuard(
  to: RouteLocationNormalized,
  from: RouteLocationNormalized,
  next: NavigationGuardNext
) {
  const authStore = useAuthStore();
  const notificationStore = useNotificationStore();

  // Inicializar auth store se necessário
  if (!authStore.isInitialized) {
    await authStore.initialize();
  }

  const isAuthenticated = authStore.isAuthenticated;
  const requiresAuth = to.meta.requiresAuth;
  const isGuestOnly = to.meta.guest;
  const requiredPermission = to.meta.permission as string | undefined;

  // Rota requer autenticação mas usuário não está autenticado
  if (requiresAuth && !isAuthenticated) {
    notificationStore.show({
      type: NotificationType.Warning,
      message: 'Você precisa estar logado para acessar esta página',
      duration: NOTIFICATION_DURATION.MEDIUM
    });

    return next({
      name: ROUTE_NAMES.LOGIN,
      query: { redirect: to.fullPath }
    });
  }

  // Rota apenas para visitantes mas usuário está autenticado
  if (isGuestOnly && isAuthenticated) {
    return next({ name: ROUTE_NAMES.DASHBOARD });
  }

  // Verificar permissão específica
  if (requiredPermission) {
    const hasPermission = authStore.hasPermission(requiredPermission);

    if (!hasPermission) {
      notificationStore.show({
        type: NotificationType.Error,
        message: 'Você não tem permissão para acessar esta página',
        duration: NOTIFICATION_DURATION.LONG
      });

      return next({ name: ROUTE_NAMES.DASHBOARD });
    }
  }

  // Verificação especial para rotas admin
  if (to.path.startsWith('/admin')) {
    const hasAdminAccess = authStore.hasPermission(
      ROUTE_PERMISSIONS.ADMIN_DASHBOARD
    );

    if (!hasAdminAccess) {
      notificationStore.show({
        type: NotificationType.Error,
        message: 'Acesso negado: área administrativa',
        duration: NOTIFICATION_DURATION.LONG
      });

      return next({ name: ROUTE_NAMES.DASHBOARD });
    }
  }

  next();
}
```

## Exemplo 5: Composable para Gerenciamento de Produtos

```typescript
// composables/useProducts.ts
import { ref, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useNotificationStore } from '@/stores/notificationStore';
import {
  API_ENDPOINTS,
  buildEndpoint,
  HTTP_STATUS,
  HTTP_STATUS_CATEGORY,
  NotificationType,
  NOTIFICATION_DURATION,
  ROUTE_NAMES,
  getStockStatus,
  StockStatus,
  STOCK_THRESHOLDS
} from '@/constants';
import api from '@/services/api';

interface Product {
  id: number;
  name: string;
  price: number;
  stock: number;
  category: string;
}

export function useProducts() {
  const router = useRouter();
  const notificationStore = useNotificationStore();

  const products = ref<Product[]>([]);
  const loading = ref(false);
  const currentPage = ref(1);
  const totalPages = ref(1);

  // Produtos com estoque baixo
  const lowStockProducts = computed(() =>
    products.value.filter(p => getStockStatus(p.stock) === StockStatus.LowStock)
  );

  // Produtos fora de estoque
  const outOfStockProducts = computed(() =>
    products.value.filter(p => getStockStatus(p.stock) === StockStatus.OutOfStock)
  );

  // Buscar lista de produtos
  async function fetchProducts(page = 1) {
    loading.value = true;

    try {
      const response = await api.get(API_ENDPOINTS.PRODUCTS.LIST, {
        params: { page }
      });

      if (HTTP_STATUS_CATEGORY.isSuccess(response.status)) {
        products.value = response.data.data;
        currentPage.value = response.data.current_page;
        totalPages.value = response.data.last_page;
      }
    } catch (error: any) {
      handleError(error, 'buscar produtos');
    } finally {
      loading.value = false;
    }
  }

  // Buscar detalhes de um produto
  async function fetchProductDetail(id: number) {
    loading.value = true;

    try {
      const endpoint = buildEndpoint(API_ENDPOINTS.PRODUCTS.DETAIL, { id });
      const response = await api.get(endpoint);

      if (response.status === HTTP_STATUS.OK) {
        return response.data;
      }
    } catch (error: any) {
      handleError(error, 'buscar detalhes do produto');
      return null;
    } finally {
      loading.value = false;
    }
  }

  // Deletar produto
  async function deleteProduct(id: number) {
    try {
      const endpoint = buildEndpoint(API_ENDPOINTS.PRODUCTS.DETAIL, { id });
      const response = await api.delete(endpoint);

      if (response.status === HTTP_STATUS.NO_CONTENT) {
        notificationStore.show({
          type: NotificationType.Success,
          message: 'Produto excluído com sucesso',
          duration: NOTIFICATION_DURATION.SHORT
        });

        // Remover da lista local
        products.value = products.value.filter(p => p.id !== id);

        return true;
      }
    } catch (error: any) {
      handleError(error, 'excluir produto');
      return false;
    }
  }

  // Navegar para detalhes do produto
  function viewProduct(id: number) {
    router.push({
      name: ROUTE_NAMES.PRODUCTS,
      params: { id }
    });
  }

  // Tratamento centralizado de erros
  function handleError(error: any, action: string) {
    if (error.response) {
      const status = error.response.status;

      switch (status) {
        case HTTP_STATUS.FORBIDDEN:
          notificationStore.show({
            type: NotificationType.Error,
            message: `Você não tem permissão para ${action}`,
            duration: NOTIFICATION_DURATION.MEDIUM
          });
          break;

        case HTTP_STATUS.NOT_FOUND:
          notificationStore.show({
            type: NotificationType.Error,
            message: 'Produto não encontrado',
            duration: NOTIFICATION_DURATION.MEDIUM
          });
          break;

        case HTTP_STATUS.UNPROCESSABLE_ENTITY:
          const errors = error.response.data.errors;
          const message = Object.values(errors).flat().join(', ');

          notificationStore.show({
            type: NotificationType.Warning,
            message,
            duration: NOTIFICATION_DURATION.LONG
          });
          break;

        default:
          if (HTTP_STATUS_CATEGORY.isServerError(status)) {
            notificationStore.show({
              type: NotificationType.Error,
              message: 'Erro no servidor. Tente novamente mais tarde.',
              duration: NOTIFICATION_DURATION.LONG
            });
          } else {
            notificationStore.show({
              type: NotificationType.Error,
              message: `Erro ao ${action}`,
              duration: NOTIFICATION_DURATION.MEDIUM
            });
          }
      }
    } else {
      notificationStore.show({
        type: NotificationType.Error,
        message: 'Erro de conexão. Verifique sua internet.',
        duration: NOTIFICATION_DURATION.MEDIUM
      });
    }
  }

  // Verificar se produto precisa de alerta de estoque
  function needsStockAlert(stock: number): boolean {
    return stock <= STOCK_THRESHOLDS.LOW_STOCK;
  }

  return {
    products,
    loading,
    currentPage,
    totalPages,
    lowStockProducts,
    outOfStockProducts,
    fetchProducts,
    fetchProductDetail,
    deleteProduct,
    viewProduct,
    needsStockAlert
  };
}
```

## Resumo dos Benefícios

Estes exemplos demonstram:

1. **Type Safety**: Autocomplete e detecção de erros em tempo de desenvolvimento
2. **Consistência**: Mesmas constantes usadas em toda a aplicação
3. **Manutenibilidade**: Mudanças centralizadas propagam automaticamente
4. **Documentação**: Código auto-documentado e fácil de entender
5. **Redução de Bugs**: Impossível ter typos em strings hardcoded
6. **DX (Developer Experience)**: Desenvolvimento mais rápido e confiável
