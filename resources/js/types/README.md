# TypeScript Types Documentation

Este diretório contém todas as definições de tipos TypeScript usadas no projeto ecommpilot.

## Estrutura de Tipos

```
types/
├── index.ts           # Barrel export - ponto central de importação
├── api.ts            # Tipos relacionados a API e requisições HTTP
├── user.ts           # Tipos de usuário e autenticação
├── store.ts          # Tipos de lojas integradas (Nuvemshop, etc)
├── product.ts        # Tipos de produtos e estoque
├── order.ts          # Tipos de pedidos e pagamentos
├── customer.ts       # Tipos de clientes
├── dashboard.ts      # Tipos de dashboard e estatísticas
├── analysis.ts       # Tipos de análise com IA
├── chat.ts           # Tipos de chat com IA
└── notification.ts   # Tipos de notificações/toasts
```

## Como Usar

### Importação Recomendada (usando barrel export)

```typescript
import type { User, LoginCredentials, ApiResponse, Result } from '@/types';
```

### Importação Específica

```typescript
import type { User } from '@/types/user';
import type { ApiResponse } from '@/types/api';
```

## Exemplos de Uso

### 1. Tipagem de Props em Componentes Vue

```vue
<script setup lang="ts">
import type { User, StatCard } from '@/types';

interface Props {
  user: User;
  stats: StatCard[];
}

const props = defineProps<Props>();
</script>
```

### 2. Tipagem em Stores Pinia

```typescript
// authStore.ts
import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import type { User, LoginCredentials, Result } from '@/types';

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null);

  async function login(credentials: LoginCredentials): Promise<Result<User>> {
    // ... implementação
  }

  return { user, login };
});
```

### 3. Tipagem de Requisições API

```typescript
import type { ApiResponse, SyncedProduct } from '@/types';
import api from '@/services/api';

async function fetchProducts(): Promise<ApiResponse<SyncedProduct[]>> {
  const response = await api.get<ApiResponse<SyncedProduct[]>>('/products');
  return response.data;
}
```

### 4. Pattern Result<T> para Error Handling

```typescript
import type { Result, User } from '@/types';

async function updateProfile(data: UserProfileUpdate): Promise<Result<User>> {
  try {
    const response = await api.put('/profile', data);
    return {
      success: true,
      data: response.data
    };
  } catch (error: any) {
    return {
      success: false,
      error: {
        message: error.response?.data?.message || 'Erro ao atualizar perfil',
        errors: error.response?.data?.errors,
        status: error.response?.status,
      }
    };
  }
}

// Uso:
const result = await updateProfile({ name: 'João' });
if (result.success) {
  console.log('Sucesso:', result.data);
} else {
  console.error('Erro:', result.error.message);
}
```

## Tipos Principais

### ApiResponse<T>

Wrapper genérico para respostas de API:

```typescript
interface ApiResponse<T = any> {
  data: T;
  message?: string;
  meta?: {
    current_page?: number;
    total?: number;
    // ...
  };
}
```

### Result<T>

Pattern para operações que podem falhar:

```typescript
type Result<T> =
  | { success: true; data: T; message?: string }
  | { success: false; error: ApiError };
```

### User

Representa um usuário autenticado:

```typescript
interface User {
  id: number;
  name: string;
  email: string;
  role: 'admin' | 'client';
  permissions: string[];
  active_store_id: number | null;
  ai_credits: number;
}
```

## Convenções de Nomenclatura

- **Interfaces**: PascalCase (ex: `User`, `LoginCredentials`)
- **Types**: PascalCase (ex: `UserRole`, `OrderStatus`)
- **Enums**: PascalCase para tipo, snake_case ou kebab-case para valores
- **Constantes**: UPPER_SNAKE_CASE (ex: `NOTIFICATION_DURATION`)

## Status Enums

O projeto usa literal types ao invés de enums nativos para melhor compatibilidade:

```typescript
// ✅ Recomendado
export type OrderStatus = 'Pending' | 'Processing' | 'Shipped';

// ❌ Evitar
export enum OrderStatus {
  Pending = 'Pending',
  Processing = 'Processing',
}
```

## Boas Práticas

1. **Sempre use `type` para importações de tipos**:
   ```typescript
   import type { User } from '@/types'; // ✅
   import { User } from '@/types';      // ❌ (importa em runtime)
   ```

2. **Prefira interfaces para objetos, types para unions**:
   ```typescript
   interface User { ... }              // ✅ Para objetos
   type UserRole = 'admin' | 'client'; // ✅ Para unions
   ```

3. **Use tipos genéricos quando apropriado**:
   ```typescript
   ApiResponse<User>
   Result<SyncedProduct[]>
   PaginatedResponse<Order>
   ```

4. **Documente tipos complexos**:
   ```typescript
   /**
    * Representa um pedido sincronizado do e-commerce
    * @property {OrderStatus} status - Status atual do pedido
    * @property {OrderItem[]} items - Itens do pedido
    */
   export interface SyncedOrder { ... }
   ```

## Migração Gradual

Para migrar código JavaScript existente:

1. Renomeie `.js` para `.ts`
2. Adicione tipos explícitos onde necessário
3. Execute `npm run type-check` (quando configurado)
4. Corrija erros de tipo progressivamente

## Troubleshooting

### Erro: Cannot find module '@/types'

Certifique-se que `tsconfig.json` tem o path correto:

```json
{
  "compilerOptions": {
    "paths": {
      "@/*": ["./resources/js/*"]
    }
  }
}
```

### Erro: Type 'X' is not assignable to type 'Y'

Verifique se os tipos estão alinhados com o backend. Sempre valide responses da API.

## Contribuindo

Ao adicionar novos tipos:

1. Crie um arquivo específico para o domínio (ex: `invoice.ts`)
2. Exporte os tipos no `index.ts`
3. Documente o uso no README
4. Mantenha consistência com tipos existentes
