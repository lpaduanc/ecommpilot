# Testes Frontend - EcommPilot

Documentação dos testes do frontend do EcommPilot.

## Configuração

Os testes estão configurados usando:

- **Vitest** - Framework de testes rápido e moderno para Vite
- **Vue Test Utils** - Utilitários oficiais para testes de componentes Vue
- **Happy DOM** - Ambiente DOM leve para testes

## Executar Testes

```bash
# Executar todos os testes
npm test

# Executar testes em modo watch
npm test -- --watch

# Executar testes com UI interativa
npm run test:ui

# Executar testes com coverage
npm run test:coverage

# Executar apenas testes de um arquivo específico
npm test -- tests/unit/views/auth/LoginView.test.js
```

## Estrutura de Testes

```
tests/
├── setup.js                           # Configuração global dos testes
├── unit/
│   ├── views/
│   │   └── auth/
│   │       └── LoginView.test.js      # Testes da tela de login
│   └── __mocks__/
│       └── notificationStore.js        # Mock da store de notificações
└── README.md                          # Este arquivo
```

## Testes Implementados

### LoginView (30 testes)

Testes completos da tela de login cobrindo:

#### Renderização do Formulário (6 testes)
- ✅ Renderização correta do formulário
- ✅ Campo de e-mail com label e placeholder
- ✅ Campo de senha com label e placeholder
- ✅ Checkbox "Lembrar-me"
- ✅ Botão de submit
- ✅ Links para "Esqueceu sua senha" e "Criar conta"

#### Validação de Campos Obrigatórios (3 testes)
- ✅ Erro quando e-mail não é preenchido
- ✅ Erro quando senha não é preenchida
- ✅ Erros para ambos os campos vazios

#### Validação de Formato de E-mail (4 testes)
- ✅ Aceita e-mails válidos
- ✅ Rejeita e-mail sem @
- ✅ Rejeita e-mail sem domínio
- ✅ Rejeita e-mail sem nome de usuário

#### Comportamento do Botão de Submit (3 testes)
- ✅ Não chama login com formulário inválido
- ✅ Chama login com formulário válido
- ✅ Envia remember=true quando checkbox marcado

#### Estados de Loading (2 testes)
- ✅ Mostra loading durante autenticação
- ✅ Remove loading após autenticação

#### Exibição de Mensagens de Erro (4 testes)
- ✅ Exibe erro do servidor para campo e-mail
- ✅ Exibe erro do servidor para campo senha
- ✅ Chama notificationStore.error
- ✅ Limpa erros ao submeter novamente

#### Redirecionamento Após Login (3 testes)
- ✅ Redireciona para "/" após login bem-sucedido
- ✅ Redireciona para URL do query param redirect
- ✅ Chama notificationStore.success

#### Interação com AuthStore (3 testes)
- ✅ Passa credenciais corretas para authStore.login
- ✅ Lida com resposta de sucesso
- ✅ Lida com resposta de erro

#### Integração Completa (2 testes)
- ✅ Fluxo de login com sucesso completo
- ✅ Fluxo de login com erro de validação do servidor

## Cobertura de Testes

Os testes do LoginView cobrem:

- **Renderização**: Todos os elementos do formulário
- **Validação**: Campos obrigatórios e formato de e-mail
- **Interações**: Submit, preenchimento de campos, checkbox
- **Estados**: Loading, erros, sucesso
- **Integração**: AuthStore e NotificationStore
- **Navegação**: Redirecionamento após login

## Boas Práticas Utilizadas

1. **Mocks Apropriados**: Components base, stores e router mockados
2. **Isolamento**: Cada teste é independente
3. **Async/Await**: Testes assíncronos bem estruturados
4. **Descrição Clara**: Testes descritivos e organizados
5. **Setup/Teardown**: beforeEach para configuração limpa
6. **Cobertura Completa**: Casos de sucesso e erro

## Próximos Passos

Testes a serem implementados:

- [ ] RegisterView
- [ ] ForgotPasswordView
- [ ] ResetPasswordView
- [ ] DashboardView
- [ ] AnalysisView
- [ ] ChatView
- [ ] Componentes base (BaseButton, BaseInput, BaseCard, etc.)
- [ ] Stores (authStore, dashboardStore, etc.)
- [ ] Composables (useFormatters, useValidation, etc.)

## Troubleshooting

### Testes Falhando

Se os testes estiverem falhando, verifique:

1. Dependências instaladas: `npm install`
2. Cache limpo: `npm test -- --clearCache`
3. Configuração do Vitest está correta
4. Mocks estão atualizados

### Performance Lenta

Se os testes estiverem lentos:

1. Use `npm test -- --run` para executar sem watch
2. Execute apenas os testes necessários
3. Verifique se não há vazamentos de memória

## Referências

- [Vitest Documentation](https://vitest.dev/)
- [Vue Test Utils](https://test-utils.vuejs.org/)
- [Testing Library Best Practices](https://testing-library.com/docs/queries/about)
