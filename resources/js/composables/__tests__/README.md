# Testes para Composables

## Configuração Futura de Vitest

Para executar os testes neste diretório, será necessário instalar e configurar o Vitest:

```bash
npm install -D vitest @vue/test-utils happy-dom @vitest/ui
```

### vitest.config.ts

Criar o arquivo `vitest.config.ts` na raiz do projeto:

```typescript
import { defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';
import path from 'path';

export default defineConfig({
  plugins: [vue()],
  test: {
    globals: true,
    environment: 'happy-dom',
    coverage: {
      provider: 'v8',
      reporter: ['text', 'html', 'lcov'],
      exclude: ['node_modules/', 'resources/js/types/'],
    },
  },
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './resources/js'),
    },
  },
});
```

### Scripts no package.json

Adicionar os seguintes scripts:

```json
{
  "scripts": {
    "test": "vitest",
    "test:ui": "vitest --ui",
    "test:coverage": "vitest run --coverage",
    "test:watch": "vitest --watch"
  }
}
```

## Testes Disponíveis

### useLoadingState.test.ts

Testes completos para o composable `useLoadingState`:

- ✅ Inicialização com valores padrão
- ✅ Gerenciamento do estado `isLoading` durante execução
- ✅ Retorno de resultados de funções assíncronas
- ✅ Captura e tratamento de erros
- ✅ Extração de mensagens de erro de responses de API
- ✅ Limpeza de erros com `clearError()`
- ✅ Múltiplas execuções sequenciais

**Executar apenas este teste:**
```bash
npm run test -- useLoadingState.test.ts
```

### Estrutura de Teste Exemplo

```typescript
import { describe, it, expect, beforeEach } from 'vitest';
import { useLoadingState } from '../useLoadingState';

describe('useLoadingState', () => {
  beforeEach(() => {
    // Setup antes de cada teste
  });

  it('deve fazer algo específico', async () => {
    const { isLoading, error, execute } = useLoadingState();

    const result = await execute(async () => {
      return 'resultado';
    });

    expect(result).toBe('resultado');
    expect(error.value).toBeNull();
  });
});
```

## Cobertura de Código Esperada

- **useLoadingState**: 100% (todas as branches cobertas)
- **useFormatters**: 95%+
- **useValidation**: 95%+
- **useSanitize**: 100%

## Boas Práticas

1. **Cada composable deve ter seu arquivo de teste**
2. **Usar beforeEach para reset de estado**
3. **Testar casos de sucesso e erro**
4. **Testar edge cases (valores null, undefined, vazios)**
5. **Usar mocks para dependências externas**

## Executar Testes

```bash
# Executar todos os testes
npm run test

# Executar com UI interativa
npm run test:ui

# Executar com coverage
npm run test:coverage

# Executar em modo watch
npm run test:watch
```

## Estrutura de Arquivos

```
composables/
├── __tests__/
│   ├── README.md (este arquivo)
│   ├── useLoadingState.test.ts
│   ├── useFormatters.test.ts
│   ├── useValidation.test.ts
│   └── useSanitize.test.ts (futuro)
├── useLoadingState.ts
├── useFormatters.ts
├── useValidation.ts
├── useSanitize.ts
└── index.ts
```

## Próximos Passos

1. Instalar Vitest e dependências
2. Criar `vitest.config.ts`
3. Adicionar scripts ao `package.json`
4. Executar testes existentes
5. Criar testes para composables ainda não testados
