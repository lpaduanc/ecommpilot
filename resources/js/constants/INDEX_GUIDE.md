# Índice de Documentação - Sistema de Constantes

Bem-vindo ao sistema de constantes do ecommpilot! Use este índice para navegar pela documentação.

## Arquivo Certo para Cada Situação

### Você quer...

#### ...começar a usar agora?
- **Leia**: QUICK_REFERENCE.md
- **Tempo**: 5 minutos
- **Você vai**: Ter referência rápida de todas as constantes disponíveis

#### ...entender tudo sobre o sistema?
- **Leia**: README.md
- **Tempo**: 15-20 minutos
- **Você vai**: Entender conceitos, benefícios e uso detalhado

#### ...migrar código existente?
- **Leia**: MIGRATION_GUIDE.md
- **Tempo**: 10-15 minutos
- **Você vai**: Aprender a converter magic strings para constantes

#### ...ver exemplos práticos?
- **Leia**: EXAMPLES.md
- **Tempo**: 20-30 minutos
- **Você vai**: Ver 5 exemplos completos de uso real

#### ...ter uma visão geral rápida?
- **Leia**: SUMMARY.md
- **Tempo**: 5-10 minutos
- **Você vai**: Entender o que foi criado e principais benefícios

## Guia de Leitura por Perfil

### Desenvolvedor Novo no Projeto
1. SUMMARY.md (visão geral)
2. QUICK_REFERENCE.md (referência rápida)
3. EXAMPLES.md (exemplos práticos)
4. README.md (documentação completa quando necessário)

### Desenvolvedor Experiente Migrando Código
1. MIGRATION_GUIDE.md (antes/depois)
2. QUICK_REFERENCE.md (consulta rápida)
3. EXAMPLES.md (casos complexos)

### Tech Lead / Arquiteto
1. SUMMARY.md (visão executiva)
2. README.md (arquitetura e convenções)
3. MIGRATION_GUIDE.md (estratégia de migração)

### Code Reviewer
1. QUICK_REFERENCE.md (referência durante review)
2. MIGRATION_GUIDE.md (padrões esperados)

## Estrutura de Arquivos

### Código TypeScript (4 arquivos)
- **index.ts** - Ponto de exportação central
  - Importar daqui: `import { ... } from '@/constants'`

- **notifications.ts** - Sistema de notificações
  - NotificationType enum
  - NOTIFICATION_DURATION

- **stock.ts** - Gerenciamento de estoque
  - StockStatus enum
  - STOCK_THRESHOLDS
  - STOCK_STATUS_CONFIG
  - getStockStatus() helper

- **routes.ts** - Sistema de rotas
  - ROUTE_NAMES (auth, app, admin)
  - ROUTE_PATHS
  - ROUTE_PERMISSIONS

- **api.ts** - API endpoints e HTTP
  - API_ENDPOINTS (auth, dashboard, products, orders, etc.)
  - HTTP_STATUS
  - HTTP_STATUS_CATEGORY
  - buildEndpoint() helper

### Documentação (5 arquivos + este)

- **README.md** - Documentação completa
  - Uso de cada constante
  - Benefícios
  - Convenções
  - Extensibilidade

- **QUICK_REFERENCE.md** - Cheat sheet
  - Todas as constantes em lista
  - Import statements
  - Padrões comuns
  - Tips

- **MIGRATION_GUIDE.md** - Guia de migração
  - Comparações antes/depois
  - Checklist
  - Prioridades
  - Benefícios observados

- **EXAMPLES.md** - Exemplos práticos
  - 5 exemplos completos
  - Casos de uso reais
  - Best practices

- **SUMMARY.md** - Sumário executivo
  - Visão geral
  - Principais benefícios
  - Como começar
  - Métricas

- **INDEX_GUIDE.md** - Este arquivo
  - Navegação pela documentação
  - Guias por perfil
  - FAQ

## Fluxo de Aprendizado Recomendado

### Rápido (15 minutos)
```
SUMMARY.md → QUICK_REFERENCE.md → Começar a usar
```

### Intermediário (45 minutos)
```
SUMMARY.md → README.md → QUICK_REFERENCE.md → EXAMPLES.md → Usar em produção
```

### Completo (90 minutos)
```
SUMMARY.md → README.md → MIGRATION_GUIDE.md → EXAMPLES.md → QUICK_REFERENCE.md → Migrar código existente
```

## FAQ

### Como importar as constantes?
```typescript
import { NotificationType, ROUTE_NAMES, API_ENDPOINTS } from '@/constants';
```

### Posso importar de arquivos individuais?
Sim, mas prefira importar do index para consistência:
```typescript
// ✅ Preferido
import { NotificationType } from '@/constants';

// ✓ Também funciona
import { NotificationType } from '@/constants/notifications';
```

### Como adicionar novas constantes?
1. Edite o arquivo TypeScript apropriado (ou crie um novo)
2. Use `as const` para type inference
3. Adicione export no index.ts
4. Documente no README.md

### Onde usar essas constantes?
Em todos os lugares! Rotas, API calls, notificações, tratamento de erros, etc.

### Preciso migrar todo o código de uma vez?
Não! Migre gradualmente, começando por novos componentes e código de alta prioridade.

### E se eu encontrar um bug ou quiser melhorar?
1. Documente o caso de uso
2. Proponha a mudança
3. Atualize os arquivos TypeScript
4. Atualize a documentação
5. Adicione exemplos se relevante

## Checklist de Onboarding

Para novos desenvolvedores:

- [ ] Ler SUMMARY.md (5 min)
- [ ] Bookmarkar QUICK_REFERENCE.md para consulta
- [ ] Ler pelo menos 2 exemplos do EXAMPLES.md
- [ ] Usar constantes em pelo menos 1 componente novo
- [ ] Migrar pelo menos 1 componente antigo (opcional)
- [ ] Compartilhar feedback com o time

## Recursos Adicionais

### No Projeto
- `tsconfig.json` - Configuração TypeScript
- `vite.config.js` - Alias `@/` configurado
- `resources/js/router/index.js` - Exemplos de rotas
- `resources/js/services/api.js` - Cliente API

### Documentação Externa
- TypeScript Handbook: https://www.typescriptlang.org/docs/
- Vue Router: https://router.vuejs.org/
- Axios: https://axios-http.com/

## Convenções do Projeto

1. **Naming**:
   - SCREAMING_SNAKE_CASE para constantes
   - PascalCase para enums e types
   - camelCase para funções helper

2. **Exports**:
   - Sempre use `as const` para constantes
   - Exporte types quando útil
   - Organize exports no index.ts

3. **Documentação**:
   - JSDoc comments para funções públicas
   - Comentários inline para lógica complexa
   - Exemplos no README.md

## Contato

Para dúvidas, sugestões ou problemas:
1. Consulte esta documentação primeiro
2. Pergunte no canal do time
3. Abra issue/ticket se necessário

---

**Versão**: 1.0.0
**Última atualização**: 2026-01-06
**Arquivos**: 10 (4 código + 6 documentação)
**Tamanho total**: ~88 KB

Bom desenvolvimento! 🚀
