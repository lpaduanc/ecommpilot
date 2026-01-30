# Guia de Testes - Sistema de Workflow para Sugestões

## Pré-requisitos

1. Executar migrations:
```bash
php artisan migrate
```

2. Ter uma sugestão aceita (status: 'accepted') no sistema

## Cenários de Teste

### 1. Sincronização Automática de Passos

**Objetivo:** Verificar se passos são criados automaticamente ao aceitar uma sugestão.

**Steps:**
1. Criar uma análise com sugestões
2. Aceitar uma sugestão via endpoint:
   ```
   POST /api/suggestions/{uuid}/accept
   ```
3. Verificar se passos foram criados:
   ```
   GET /api/suggestions/{uuid}/steps
   ```

**Resultado Esperado:**
- Array de steps criados a partir do campo `recommended_action`
- Todos com `is_custom: false`
- Todos com `status: 'pending'`
- `progress: 0`

### 2. Listar Passos

**Endpoint:** `GET /api/suggestions/{uuid}/steps`

**Resultado Esperado:**
```json
{
  "success": true,
  "data": {
    "steps": [
      {
        "id": 1,
        "title": "Passo 1",
        "description": null,
        "position": 1,
        "is_custom": false,
        "status": "pending",
        "completed_at": null,
        "completed_by": null
      }
    ],
    "progress": 0
  }
}
```

### 3. Completar Passo

**Endpoint:** `PATCH /api/suggestions/{uuid}/steps/{id}`

**Body:**
```json
{
  "status": "completed"
}
```

**Resultado Esperado:**
- `status: 'completed'`
- `completed_at` preenchido
- `completed_by` com dados do usuário logado
- `progress` atualizado (ex: 33 se 1 de 3 passos completo)

### 4. Descompletar Passo

**Endpoint:** `PATCH /api/suggestions/{uuid}/steps/{id}`

**Body:**
```json
{
  "status": "pending"
}
```

**Resultado Esperado:**
- `status: 'pending'`
- `completed_at: null`
- `completed_by: null`
- `progress` decrementado

### 5. Criar Passo Customizado

**Endpoint:** `POST /api/suggestions/{uuid}/steps`

**Body:**
```json
{
  "title": "Passo customizado criado manualmente",
  "description": "Descrição opcional do passo"
}
```

**Resultado Esperado:**
- Passo criado com `is_custom: true`
- `position` adicionado ao final (max + 1)
- `status: 'pending'`
- `progress` recalculado

### 6. Editar Passo

**Endpoint:** `PATCH /api/suggestions/{uuid}/steps/{id}`

**Body:**
```json
{
  "title": "Título editado",
  "description": "Descrição editada",
  "position": 2
}
```

**Resultado Esperado:**
- Campos atualizados
- Funciona para passos originais e customizados

### 7. Deletar Passo Customizado

**Endpoint:** `DELETE /api/suggestions/{uuid}/steps/{id}`

**Pré-condição:** Passo com `is_custom: true`

**Resultado Esperado:**
- Passo removido com sucesso
- `progress` recalculado

### 8. Tentar Deletar Passo Original (Erro)

**Endpoint:** `DELETE /api/suggestions/{uuid}/steps/{id}`

**Pré-condição:** Passo com `is_custom: false`

**Resultado Esperado:**
```json
{
  "success": false,
  "message": "Apenas passos customizados podem ser removidos.",
  "status": 422
}
```

### 9. Criar Comentário Geral

**Endpoint:** `POST /api/suggestions/{uuid}/comments`

**Body:**
```json
{
  "content": "Comentário geral sobre a sugestão"
}
```

**Resultado Esperado:**
- Comentário criado com `step_id: null`
- `is_general: true`
- `user` preenchido

### 10. Criar Comentário em Passo

**Endpoint:** `POST /api/suggestions/{uuid}/comments`

**Body:**
```json
{
  "content": "Comentário sobre o passo específico",
  "step_id": 1
}
```

**Resultado Esperado:**
- Comentário criado com `step_id: 1`
- `is_general: false`
- `step_title` preenchido

### 11. Listar Comentários

**Endpoint:** `GET /api/suggestions/{uuid}/comments`

**Resultado Esperado:**
```json
{
  "success": true,
  "data": {
    "comments": [
      {
        "id": 1,
        "content": "Comentário...",
        "step_id": 1,
        "step_title": "Título do passo",
        "user": {
          "id": 1,
          "name": "João Silva"
        },
        "created_at": "2026-01-27T10:30:00Z",
        "is_general": false
      }
    ]
  }
}
```

### 12. Deletar Próprio Comentário

**Endpoint:** `DELETE /api/suggestions/{uuid}/comments/{id}`

**Pré-condição:** Comentário criado pelo usuário logado

**Resultado Esperado:**
- Comentário removido com sucesso

### 13. Tentar Deletar Comentário de Outro Usuário (Erro)

**Endpoint:** `DELETE /api/suggestions/{uuid}/comments/{id}`

**Pré-condição:** Comentário criado por outro usuário + usuário não é super_admin

**Resultado Esperado:**
```json
{
  "success": false,
  "message": "Você não pode remover este comentário.",
  "status": 403
}
```

### 14. Controle de Acesso - Loja de Outro Usuário (Erro)

**Endpoint:** Qualquer endpoint de steps/comments

**Pré-condição:** Sugestão pertence a loja de outro usuário

**Resultado Esperado:**
```json
{
  "success": false,
  "message": "Você não tem acesso a esta sugestão.",
  "status": 403
}
```

### 15. Super Admin - Acesso a Todas as Lojas

**Endpoint:** Qualquer endpoint de steps/comments

**Pré-condição:** Usuário com role super_admin

**Resultado Esperado:**
- Acesso permitido a sugestões de qualquer loja

## Testes de Progresso

### Cenário: 3 passos, 1 completo
- Total: 3
- Completos: 1
- Progress: 33%

### Cenário: 5 passos, 3 completos
- Total: 5
- Completos: 3
- Progress: 60%

### Cenário: Nenhum passo
- Total: 0
- Progress: 0%

### Cenário: Todos completos
- Total: 4
- Completos: 4
- Progress: 100%

## Validações a Testar

### Criar Passo
- ✅ `title` é obrigatório (max 500 chars)
- ✅ `description` é opcional
- ✅ `position` é opcional (calcula automaticamente)
- ✅ Deve estar autenticado
- ✅ Deve ter acesso à loja

### Atualizar Passo
- ✅ `title` é opcional (max 500 chars)
- ✅ `description` é opcional
- ✅ `position` é opcional
- ✅ `status` deve ser 'pending' ou 'completed'
- ✅ Passo deve pertencer à sugestão

### Criar Comentário
- ✅ `content` é obrigatório (max 2000 chars)
- ✅ `step_id` é opcional
- ✅ Se `step_id` fornecido, deve pertencer à sugestão
- ✅ Deve estar autenticado

### Deletar Comentário
- ✅ Apenas autor ou super_admin
- ✅ Comentário deve pertencer à sugestão

## Testes de Edge Cases

1. **Sugestão sem recommended_action**
   - Aceitar sugestão
   - Verificar que nenhum step é criado
   - Progress deve ser 0

2. **Sugestão com recommended_action vazio**
   - Aceitar sugestão
   - Verificar que nenhum step é criado

3. **Sugestão já com steps**
   - Aceitar sugestão novamente
   - Verificar que não duplica steps

4. **Comentário em step deletado**
   - Criar comentário em step customizado
   - Deletar step
   - Verificar que comentário também é deletado (CASCADE)

5. **Reordenar positions**
   - Criar 3 passos
   - Atualizar position do passo 3 para 1
   - Verificar ordenação

## Collection Postman/Insomnia

Variáveis necessárias:
- `base_url`: http://localhost:8000/api
- `token`: Bearer token de autenticação
- `suggestion_uuid`: UUID de uma sugestão aceita
- `step_id`: ID de um passo
- `comment_id`: ID de um comentário

## Logs a Verificar

Verificar `storage/logs/laravel.log` para:
- "Custom step created"
- "Step updated"
- "Custom step deleted"
- "Comment created"
- "Comment deleted"

## Performance

Verificar queries com Laravel Debugbar ou Telescope:
- Listar steps: deve fazer eager loading de `completedBy`
- Listar comments: deve fazer eager loading de `user` e `step`
- Não deve ter N+1 queries

## Próximos Passos após Testes

1. Implementar frontend Vue 3
2. Adicionar testes automatizados (PHPUnit)
3. Adicionar notificações quando passo é completado
4. Adicionar activity log para mudanças nos passos
5. Implementar drag-and-drop para reordenar passos
