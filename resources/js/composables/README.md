# Composables Reutilizáveis - Ecommpilot

Esta pasta contém composables Vue 3 reutilizáveis que fornecem funcionalidades comuns em toda a aplicação.

## 📚 Composables Disponíveis

### 1. useFormatters

Formatação de valores (moeda, data, porcentagem) no padrão brasileiro.

```typescript
import { useFormatters } from '@/composables/useFormatters';

const { formatCurrency, formatDate, formatPercentage } = useFormatters();

formatCurrency(1234.56);      // "R$ 1.234,56"
formatDate('2024-01-15');     // "15/01/2024"
formatPercentage(25.5);       // "25.50%"
```

**Casos de Uso:**
- Exibição de preços de produtos
- Formatação de receita e valores no dashboard
- Formatação de datas de pedidos
- Exibição de taxas de conversão e crescimento

### 2. useValidation

Validação de dados comuns (email, CPF).

```typescript
import { useValidation } from '@/composables/useValidation';

const { isValidEmail, isValidCPF } = useValidation();

isValidEmail('teste@exemplo.com');  // true
isValidEmail('teste@exemplo');       // false

isValidCPF('123.456.789-10');        // Valida formato + dígitos
isValidCPF('12345678910');           // Aceita sem formatação
isValidCPF('111.111.111-11');        // false (CPF inválido)
```

**Casos de Uso:**
- Validação de formulários de login/registro
- Validação de dados de clientes
- Validação de campos antes de enviar ao backend

### 3. useScroll

Controle de scroll de elementos e da página.

```typescript
import { ref } from 'vue';
import { useScroll } from '@/composables/useScroll';

const chatContainer = ref<HTMLElement | null>(null);
const { scrollToBottom, scrollToTop, scrollToElement } = useScroll();

// Rolar chat para baixo quando nova mensagem chegar
watch(messages, () => {
  scrollToBottom(chatContainer);
});

// Botão "voltar ao topo"
scrollToTop();

// Rolar até elemento específico (ex: skip links)
scrollToElement('main-content', -80);
```

**Casos de Uso:**
- Chat: scroll automático para última mensagem
- Botão "voltar ao topo"
- Navegação por âncoras (skip links de acessibilidade)
- Scroll suave ao mudar de seção

### 4. useSanitize

Sanitização de HTML para proteção contra XSS.

```typescript
import { computed } from 'vue';
import { useSanitize } from '@/composables/useSanitize';

const props = defineProps<{ message: { content: string } }>();

const messageContent = computed(() => props.message.content);
const { sanitized, stripped, basic } = useSanitize(messageContent);

// Em template
<div v-html="sanitized"></div>      // HTML sanitizado (tags permitidas)
<p>{{ stripped }}</p>                // Texto puro (sem HTML)
<div v-html="basic"></div>           // Apenas formatação básica
```

**Casos de Uso:**
- Exibir mensagens de chat com formatação
- Renderizar respostas da IA com markdown
- Exibir descrições de produtos vindas de integrações
- Prevenir ataques XSS em conteúdo gerado por usuários

### 5. useSanitizeArray

Variante do useSanitize para arrays de strings.

```typescript
import { useSanitizeArray } from '@/composables/useSanitize';

const messages = ref([
  '<p>Mensagem 1</p>',
  '<p>Mensagem 2 <script>alert(1)</script></p>'
]);

const { sanitized, stripped } = useSanitizeArray(messages);

// sanitized.value = ['<p>Mensagem 1</p>', '<p>Mensagem 2 </p>']
// stripped.value = ['Mensagem 1', 'Mensagem 2 ']
```

## 🛡️ Utilitários de Sanitização (utils/sanitize.ts)

Funções puras de sanitização que podem ser usadas fora de componentes Vue.

```typescript
import { sanitizeHtml, stripHtml, sanitizeBasicFormatting, sanitizeUrl } from '@/utils/sanitize';

// Sanitizar HTML mantendo formatação permitida
sanitizeHtml('<p>Texto <b>negrito</b></p><script>alert("XSS")</script>');
// Retorna: '<p>Texto <b>negrito</b></p>'

// Remover todo HTML
stripHtml('<p>Texto <b>negrito</b></p>');
// Retorna: 'Texto negrito'

// Apenas formatação básica
sanitizeBasicFormatting('<p>Texto <b>negrito</b> <a href="#">link</a></p>');
// Retorna: '<p>Texto <b>negrito</b> <a href="#">link</a></p>'

// Sanitizar URLs
sanitizeUrl('https://exemplo.com');        // 'https://exemplo.com'
sanitizeUrl('javascript:alert(1)');        // ''
```

## 📦 Importação

### Importação Individual

```typescript
import { useFormatters } from '@/composables/useFormatters';
import { useValidation } from '@/composables/useValidation';
```

### Importação via Barrel Export

```typescript
import { useFormatters, useValidation, useScroll } from '@/composables';
```

## 🎯 Exemplos Práticos

### Dashboard - Formatação de Estatísticas

```vue
<script setup lang="ts">
import { useFormatters } from '@/composables';
import { useDashboardStore } from '@/stores/dashboardStore';

const dashboardStore = useDashboardStore();
const { formatCurrency, formatPercentage } = useFormatters();

const stats = computed(() => dashboardStore.stats);
</script>

<template>
  <div class="stats-card">
    <h3>Receita Total</h3>
    <p class="value">{{ formatCurrency(stats.total_revenue) }}</p>
    <p class="change">{{ formatPercentage(stats.revenue_change) }}</p>
  </div>
</template>
```

### Formulário - Validação de Email

```vue
<script setup lang="ts">
import { ref, computed } from 'vue';
import { useValidation } from '@/composables';

const email = ref('');
const { isValidEmail } = useValidation();

const emailError = computed(() => {
  if (!email.value) return null;
  return isValidEmail(email.value) ? null : 'Email inválido';
});
</script>

<template>
  <div>
    <input v-model="email" type="email" placeholder="seu@email.com" />
    <p v-if="emailError" class="error">{{ emailError }}</p>
  </div>
</template>
```

### Chat - Scroll e Sanitização

```vue
<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { useScroll, useSanitizeArray } from '@/composables';
import { useChatStore } from '@/stores/chatStore';

const chatStore = useChatStore();
const chatContainer = ref<HTMLElement | null>(null);

const messages = computed(() => chatStore.messages.map(m => m.content));
const { sanitized: sanitizedMessages } = useSanitizeArray(messages);

const { scrollToBottom } = useScroll();

// Scroll automático ao receber nova mensagem
watch(() => chatStore.messages.length, () => {
  scrollToBottom(chatContainer);
});
</script>

<template>
  <div ref="chatContainer" class="chat-messages">
    <div
      v-for="(message, index) in chatStore.messages"
      :key="message.id"
      class="message"
    >
      <div v-html="sanitizedMessages[index]"></div>
    </div>
  </div>
</template>
```

## 🔒 Segurança

### Proteção contra XSS

**SEMPRE** use `useSanitize` ou `sanitizeHtml` antes de renderizar HTML com `v-html`:

```vue
<!-- ❌ PERIGOSO - Vulnerável a XSS -->
<div v-html="message.content"></div>

<!-- ✅ SEGURO - HTML sanitizado -->
<script setup>
const messageContent = computed(() => message.content);
const { sanitized } = useSanitize(messageContent);
</script>
<div v-html="sanitized"></div>
```

### Validação Client-Side vs Server-Side

As validações do `useValidation` são apenas client-side. **SEMPRE** valide também no backend:

```typescript
// ✅ Client-side: UX imediato
const { isValidEmail } = useValidation();
if (!isValidEmail(email.value)) {
  showError('Email inválido');
  return;
}

// ✅ Server-side: Segurança
await api.post('/register', { email }); // Laravel valida novamente
```

## 🧪 Testes

Todos os composables possuem testes unitários em `__tests__/`:

```bash
# Executar testes
npm run test

# Executar testes em modo watch
npm run test:watch

# Gerar coverage
npm run test:coverage
```

## 📖 Referências

- [Vue 3 Composition API](https://vuejs.org/guide/extras/composition-api-faq.html)
- [VueUse - Collection of Composables](https://vueuse.org/)
- [DOMPurify Documentation](https://github.com/cure53/DOMPurify)
- [OWASP XSS Prevention](https://cheatsheetseries.owasp.org/cheatsheets/Cross_Site_Scripting_Prevention_Cheat_Sheet.html)

## 🤝 Contribuindo

Ao criar novos composables:

1. **Siga o padrão `use*` para naming**
2. **Adicione JSDoc comments** para todas as funções
3. **Inclua exemplos de uso** nos comentários
4. **Crie testes unitários** em `__tests__/`
5. **Documente no README.md** com exemplos práticos
6. **Exporte no index.ts** para barrel export
