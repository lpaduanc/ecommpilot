# Sistema de Workflow para Sugestões - Implementação

## Resumo
Implementação do sistema de workflow e gerenciamento de passos nas sugestões de análise AI (ECDEV-37).

## Arquivos Criados

### 1. Migrations

#### `database/migrations/2026_01_27_000001_create_suggestion_steps_table.php`
- Tabela `suggestion_steps` com os campos:
  - `id` (BIGSERIAL PRIMARY KEY)
  - `suggestion_id` (FK → suggestions)
  - `title` (VARCHAR 500)
  - `description` (TEXT nullable)
  - `position` (SMALLINT)
  - `is_custom` (BOOLEAN)
  - `status` (VARCHAR 20: 'pending', 'completed')
  - `completed_at` (TIMESTAMP nullable)
  - `completed_by` (FK → users nullable)
  - Indexes: (suggestion_id, position), (suggestion_id, status)

#### `database/migrations/2026_01_27_000002_create_suggestion_comments_table.php`
- Tabela `suggestion_comments` com os campos:
  - `id` (BIGSERIAL PRIMARY KEY)
  - `suggestion_id` (FK → suggestions)
  - `step_id` (FK → suggestion_steps nullable)
  - `user_id` (FK → users)
  - `content` (TEXT)
  - Indexes: (suggestion_id, created_at), (step_id, created_at)

### 2. Models

#### `app/Models/SuggestionStep.php`
**Relacionamentos:**
- `suggestion()` - BelongsTo Suggestion
- `completedBy()` - BelongsTo User
- `comments()` - HasMany SuggestionComment

**Métodos:**
- `complete(User $user)` - Marca passo como completo
- `uncomplete()` - Marca passo como pendente
- `isCompleted()` - Verifica se está completo
- `isPending()` - Verifica se está pendente

**Scopes:**
- `pending()` - Filtra passos pendentes
- `completed()` - Filtra passos completos
- `ordered()` - Ordena por posição
- `custom()` - Filtra apenas passos customizados
- `original()` - Filtra apenas passos originais

#### `app/Models/SuggestionComment.php`
**Relacionamentos:**
- `suggestion()` - BelongsTo Suggestion
- `step()` - BelongsTo SuggestionStep (nullable)
- `user()` - BelongsTo User

**Métodos:**
- `isGeneral()` - Verifica se é comentário geral
- `isStepComment()` - Verifica se é comentário de passo

**Scopes:**
- `general()` - Filtra comentários gerais
- `stepComments()` - Filtra comentários de passos

#### `app/Models/Suggestion.php` (Modificado)
**Novos Relacionamentos:**
- `steps()` - HasMany SuggestionStep (ordenado por position)
- `comments()` - HasMany SuggestionComment

**Novos Métodos:**
- `getProgressAttribute()` - Retorna % de progresso (passos completos / total)
- `syncStepsFromRecommendedAction()` - Cria steps a partir do array recommended_action

**Modificações:**
- Método `accept()` agora chama `syncStepsFromRecommendedAction()` automaticamente

### 3. Controllers

#### `app/Http/Controllers/Api/SuggestionStepController.php`

**Endpoints:**

1. `GET /suggestions/{suggestion}/steps`
   - Lista passos com status
   - Retorna: steps[], progress%
   - Permissão: analysis.view

2. `POST /suggestions/{suggestion}/steps`
   - Cria passo customizado
   - Body: title*, description?, position?
   - Retorna: step, progress%
   - Permissão: analysis.view

3. `PATCH /suggestions/{suggestion}/steps/{step}`
   - Atualiza passo (toggle status ou edita texto)
   - Body: title?, description?, position?, status?
   - Retorna: step, progress%
   - Permissão: analysis.view

4. `DELETE /suggestions/{suggestion}/steps/{step}`
   - Remove passo (apenas is_custom=true)
   - Retorna: progress%
   - Permissão: analysis.view

**Validações:**
- Apenas passos customizados podem ser deletados
- Passos originais podem ser editados mas não removidos
- Status toggle registra completed_by e completed_at

#### `app/Http/Controllers/Api/SuggestionCommentController.php`

**Endpoints:**

1. `GET /suggestions/{suggestion}/comments`
   - Lista comentários (gerais + de passos)
   - Retorna: comments[] com user, step_title
   - Permissão: analysis.view

2. `POST /suggestions/{suggestion}/comments`
   - Cria comentário
   - Body: content*, step_id?
   - Retorna: comment com user
   - Permissão: analysis.view

3. `DELETE /suggestions/{suggestion}/comments/{comment}`
   - Remove comentário (apenas autor ou super_admin)
   - Permissão: analysis.view + ownership

### 4. Rotas (routes/api.php)

Adicionadas dentro do grupo `suggestions` (middleware: auth:sanctum + can:analysis.view):

```php
// Steps (Workflow)
Route::get('/{suggestion}/steps', [SuggestionStepController::class, 'index']);
Route::post('/{suggestion}/steps', [SuggestionStepController::class, 'store']);
Route::patch('/{suggestion}/steps/{step}', [SuggestionStepController::class, 'update']);
Route::delete('/{suggestion}/steps/{step}', [SuggestionStepController::class, 'destroy']);

// Comments
Route::get('/{suggestion}/comments', [SuggestionCommentController::class, 'index']);
Route::post('/{suggestion}/comments', [SuggestionCommentController::class, 'store']);
Route::delete('/{suggestion}/comments/{comment}', [SuggestionCommentController::class, 'destroy']);
```

### 5. User Model (app/Models/User.php) - Modificado

**Novo Método:**
- `hasAccessToStore(?int $storeId)` - Verifica se usuário tem acesso à loja
  - Super admins: acesso a todas as lojas
  - Usuários regulares: apenas suas lojas

## Regras de Negócio Implementadas

1. **Passos Originais vs Customizados:**
   - Passos originais (is_custom=false) NÃO podem ser deletados
   - Passos originais PODEM ter texto editado
   - Passos customizados podem ser deletados

2. **Sincronização Automática:**
   - `syncStepsFromRecommendedAction()` é chamado automaticamente quando sugestão é aceita
   - Suporta formato string ou array no recommended_action
   - Apenas cria steps se ainda não existirem

3. **Progresso:**
   - Calculado dinamicamente: (completos / total) * 100
   - Atualizado em tempo real através do accessor `progress`

4. **Comentários:**
   - Podem ser gerais (step_id=null) ou associados a um passo
   - Apenas autor ou super_admin pode deletar

5. **Controle de Acesso:**
   - Verifica ownership da loja através de `hasAccessToStore()`
   - Super admins têm acesso a todas as lojas
   - Usuários regulares apenas às suas lojas

## Comandos para Executar

```bash
# Rodar migrations
php artisan migrate

# Executar testes (após implementar frontend)
composer test

# Lint PHP
./vendor/bin/pint
```

## Próximos Passos

1. Executar as migrations para criar as tabelas
2. Testar os endpoints via Postman/Insomnia:
   - Aceitar uma sugestão (deve criar steps automaticamente)
   - Listar steps
   - Toggle status de step
   - Criar step customizado
   - Adicionar comentários
3. Implementar frontend (Vue 3):
   - Componente StepsList
   - Componente StepItem (com toggle)
   - Componente AddStepModal
   - Componente CommentsList
   - Integração com analysisStore

## Estrutura do Response

### GET /suggestions/{uuid}/steps
```json
{
  "success": true,
  "data": {
    "steps": [
      {
        "id": 1,
        "title": "Criar cupom de desconto",
        "description": null,
        "position": 1,
        "is_custom": false,
        "status": "completed",
        "completed_at": "2026-01-27T10:30:00Z",
        "completed_by": {
          "id": 1,
          "name": "João Silva"
        }
      }
    ],
    "progress": 33
  }
}
```

### GET /suggestions/{uuid}/comments
```json
{
  "success": true,
  "data": {
    "comments": [
      {
        "id": 1,
        "content": "Implementado o cupom PRIMEIRACOMPRA",
        "step_id": 1,
        "step_title": "Criar cupom de desconto",
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

## Notas de Implementação

- Todos os controllers usam o trait `ApiResponse` para padronizar responses
- Logs são registrados em todas as operações importantes
- Validações são feitas via Request validation do Laravel
- Timestamps são retornados em formato ISO 8601
- Erros são tratados com try/catch e retornam mensagens em português
- Code follows Laravel best practices e Pint style guide
