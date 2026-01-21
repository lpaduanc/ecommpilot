# ConfirmDialog - Implementação Moderna

Sistema de confirmação elegante que substitui os `confirm()` nativos do browser.

## Arquivos Criados

### 1. Composable: `resources/js/composables/useConfirmDialog.js`
Gerencia o estado global do modal de confirmação e retorna uma Promise para tratamento assíncrono.

**Uso:**
```javascript
import { useConfirmDialog } from '@/composables/useConfirmDialog';

const { confirm } = useConfirmDialog();

async function deleteItem() {
    const confirmed = await confirm({
        title: 'Excluir Item',
        message: 'Tem certeza que deseja excluir este item?',
        confirmText: 'Excluir',
        cancelText: 'Cancelar',
        variant: 'danger', // 'primary' | 'danger' | 'warning'
    });

    if (!confirmed) return;
    // procede com a ação
}
```

### 2. Componente: `resources/js/components/common/ConfirmDialog.vue`
Modal global que utiliza o `BaseModal` existente com design moderno:
- Ícone apropriado baseado na variante (ExclamationTriangleIcon para danger/warning, QuestionMarkCircleIcon para primary)
- Cores contextuais (vermelho para danger, amarelo para warning, azul para primary)
- Animações suaves
- Dark mode suportado

### 3. Registrado globalmente em: `resources/js/App.vue`
O componente é renderizado uma única vez e gerenciado via estado reativo global.

## Arquivos Atualizados

### Views que agora usam o ConfirmDialog:

1. **NotificationsView.vue** (2 confirmações)
   - Excluir notificação individual (variant: danger)
   - Marcar todas como lidas (variant: primary)

2. **ClientDetailView.vue** (1 confirmação)
   - Remover plano do cliente (variant: danger)

3. **PlansView.vue** (1 confirmação)
   - Excluir plano (variant: danger)

4. **admin/SettingsView.vue** (1 confirmação)
   - Excluir configuração de e-mail (variant: danger)

## Variantes Disponíveis

### `primary` (padrão - azul)
Para confirmações gerais sem risco crítico.

### `danger` (vermelho)
Para ações destrutivas como exclusões.

### `warning` (amarelo)
Para ações que requerem atenção especial.

## Benefícios

1. **Consistência**: Todos os diálogos de confirmação seguem o mesmo design
2. **Acessibilidade**: Usa BaseModal que já implementa focus trap e ARIA
3. **Dark Mode**: Suporte nativo para modo escuro
4. **Responsivo**: Funciona perfeitamente em mobile
5. **Modernidade**: Design elegante com ícones e animações
6. **Type-safe**: Retorna Promise<boolean> para fácil integração
7. **Customizável**: Títulos, mensagens e textos de botões configuráveis

## Exemplo de Uso por Variante

```javascript
// Exclusão (danger)
const confirmed = await confirm({
    title: 'Excluir Produto',
    message: 'Esta ação não pode ser desfeita.',
    confirmText: 'Excluir',
    variant: 'danger',
});

// Ação importante (warning)
const confirmed = await confirm({
    title: 'Alterar Status',
    message: 'Isso afetará todos os usuários conectados.',
    confirmText: 'Continuar',
    variant: 'warning',
});

// Confirmação padrão (primary)
const confirmed = await confirm({
    title: 'Salvar Alterações',
    message: 'Deseja salvar as alterações?',
    confirmText: 'Salvar',
    variant: 'primary',
});
```

## Build

O projeto compila corretamente com `npm run build`:
- ✓ 821 modules transformed
- ✓ built in 8.85s
- Todos os chunks gerados com sucesso
