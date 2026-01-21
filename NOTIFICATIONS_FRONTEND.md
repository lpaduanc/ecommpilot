# Interface de Notificações - Frontend

## Implementação Completa

Esta documentação descreve a implementação completa da interface de notificações no frontend Vue 3.

## Arquivos Criados

### 1. Store Pinia
**Arquivo:** `resources/js/stores/systemNotificationStore.js`

Store Pinia para gerenciar o estado das notificações do sistema:
- Busca de notificações paginadas
- Busca de notificações não lidas
- Marcar como lida (individual e todas)
- Deletar notificações
- Filtros (tipo, status, período)
- Polling automático para novas notificações

### 2. Componentes

#### NotificationItem.vue
**Arquivo:** `resources/js/components/notifications/NotificationItem.vue`

Componente para renderizar cada item de notificação:
- Ícone baseado no tipo (sync, analysis, email, error, success)
- Cores baseadas no tipo
- Indicador visual de lida/não lida
- Data relativa (há X minutos, há X horas, etc)
- Ações: marcar como lida, deletar
- Suporte para modo compacto (usado no dropdown)

#### NotificationDropdown.vue
**Arquivo:** `resources/js/components/notifications/NotificationDropdown.vue`

Dropdown de notificações no header:
- Mostra até 5 notificações não lidas
- Loading state
- Empty state quando não há notificações
- Botão "Marcar todas como lidas"
- Link "Ver todas as notificações"

### 3. View (Página)

#### NotificationsView.vue
**Arquivo:** `resources/js/views/NotificationsView.vue`

Página completa de notificações:
- Lista paginada de todas as notificações
- Filtros:
  - **Tipo:** Todos, Sincronização, Análise IA, E-mail
  - **Status:** Todas, Não lidas, Lidas
  - **Período:** Todos, Hoje, Última semana, Último mês
- Ações:
  - Marcar individual como lida
  - Marcar todas como lidas
  - Deletar notificação
- Estados:
  - Loading
  - Empty state
  - Lista com paginação
- Design responsivo com dark mode

### 4. Composable

#### useRelativeTime.js
**Arquivo:** `resources/js/composables/useRelativeTime.js`

Helper para formatar datas relativas em português:
- "Agora mesmo"
- "Há X minutos"
- "Há X horas"
- "Há X dias"
- Data formatada (para > 7 dias)

## Arquivos Modificados

### 1. TheHeader.vue
**Arquivo:** `resources/js/components/layout/TheHeader.vue`

Modificações:
- Importa `useSystemNotificationStore` e `NotificationDropdown`
- Badge de contagem de não lidas no ícone de sino
- Dropdown substituído pelo componente `NotificationDropdown`
- Polling automático:
  - Busca notificações não lidas ao montar
  - Inicia polling a cada 60 segundos
  - Para polling ao desmontar

### 2. TheSidebar.vue
**Arquivo:** `resources/js/components/layout/TheSidebar.vue`

Modificações:
- Importa `useSystemNotificationStore` e `BellIcon`
- Adiciona item de menu "Notificações" com:
  - Ícone de sino
  - Badge de contagem de não lidas
  - Link para `/notifications`
- Badge aparece no modo expandido e colapsado

### 3. Router
**Arquivo:** `resources/js/router/index.js`

Adicionada rota:
```javascript
{
  path: '/notifications',
  name: 'notifications',
  component: () => import('../views/NotificationsView.vue'),
  meta: { requiresAuth: true },
}
```

## API Esperada (Backend)

A implementação espera os seguintes endpoints:

### GET /notifications
Buscar notificações paginadas
```javascript
// Query params
{
  page: 1,
  per_page: 20,
  type: 'sync|analysis|email', // opcional
  status: 'read|unread', // opcional
  period: 'today|week|month' // opcional
}

// Response
{
  data: [
    {
      id: 1,
      type: 'sync', // sync|analysis|email|error|success
      title: 'Sincronização concluída',
      message: 'Seus produtos foram atualizados com sucesso.',
      read_at: null, // ou timestamp ISO
      created_at: '2026-01-18T10:30:00Z'
    }
  ],
  current_page: 1,
  last_page: 5,
  per_page: 20,
  total: 100
}
```

### GET /notifications/unread
Buscar apenas notificações não lidas
```javascript
// Response
{
  data: [
    { id: 1, type: 'sync', title: '...', message: '...', ... }
  ]
}
```

### POST /notifications/:id/read
Marcar notificação como lida
```javascript
// Response
{ success: true }
```

### POST /notifications/read-all
Marcar todas como lidas
```javascript
// Response
{ success: true }
```

### DELETE /notifications/:id
Deletar notificação
```javascript
// Response
{ success: true }
```

## Tipos de Notificação

Os tipos suportados e suas cores são:

| Tipo | Cor | Ícone | Uso |
|------|-----|-------|-----|
| `sync` | Azul (primary) | ArrowPathIcon | Sincronizações |
| `analysis` | Roxo | SparklesIcon | Análises IA |
| `email` | Verde (success) | EnvelopeIcon | E-mails |
| `error` | Vermelho (danger) | ExclamationCircleIcon | Erros |
| `success` | Verde (success) | CheckCircleIcon | Sucessos |

## Funcionalidades

### Polling Automático
- Inicia automaticamente ao montar o TheHeader
- Busca novas notificações a cada 60 segundos
- Para automaticamente ao desmontar

### Filtros na Página
- **Tipo:** Filtra por tipo de notificação
- **Status:** Mostra lidas, não lidas ou todas
- **Período:** Filtra por data de criação

### Estados Visuais
- **Loading:** Spinner durante carregamento
- **Empty:** Mensagem quando não há notificações
- **Badge:** Contagem de não lidas no header e sidebar
- **Indicador:** Ponto vermelho para notificações não lidas

### Responsividade
- Design mobile-first
- Dropdown adaptado para telas pequenas
- Filtros em grid responsivo na página

### Dark Mode
- Todos os componentes suportam dark mode
- Cores adaptadas automaticamente
- Transições suaves

## Uso

### Buscar notificações programaticamente
```javascript
import { useSystemNotificationStore } from '@/stores/systemNotificationStore';

const notificationStore = useSystemNotificationStore();

// Buscar notificações
await notificationStore.fetchNotifications(page, perPage);

// Buscar não lidas
await notificationStore.fetchUnread();

// Marcar como lida
await notificationStore.markAsRead(notificationId);

// Marcar todas como lidas
await notificationStore.markAllAsRead();

// Deletar
await notificationStore.deleteNotification(notificationId);

// Aplicar filtros
notificationStore.setFilters({
  type: 'sync',
  status: 'unread',
  period: 'week'
});

// Resetar filtros
notificationStore.resetFilters();
```

### Acessar estado
```javascript
const notificationStore = useSystemNotificationStore();

// Contagem de não lidas
notificationStore.unreadCount

// Tem não lidas?
notificationStore.hasUnread

// Lista de notificações
notificationStore.notifications

// Lista de não lidas
notificationStore.unreadNotifications

// Lista filtrada
notificationStore.filteredNotifications
```

## Próximos Passos

Para completar a implementação, o backend precisa:

1. Criar migration para tabela `notifications`
2. Criar model `Notification`
3. Implementar controller `NotificationController`
4. Registrar rotas de API
5. Criar notificações ao:
   - Completar sincronizações
   - Gerar análises IA
   - Enviar e-mails
   - Ocorrer erros importantes
