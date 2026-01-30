# Implementação do Sistema de Workflow de Sugestões

## Resumo das Alterações

Sistema de workflow de sugestões implementado separando PASSOS (referência da IA) de TAREFAS (criadas pelo cliente), com registro de impacto nas vendas.

## Arquivos Criados

### Migrations

1. **database/migrations/2026_01_27_000005_create_suggestion_tasks_table.php**
   - Tabela `suggestion_tasks` para tarefas do cliente
   - Campos: step_index (nullable), title, description, status, due_date, completed_at, completed_by, created_by
   - Status: pending, in_progress, completed

2. **database/migrations/2026_01_27_000006_create_suggestion_impacts_table.php**
   - Tabela `suggestion_impacts` para registro de impacto nas vendas
   - Campos: type, label, value, numeric_value, metadata
   - Tipos: coupon, campaign, method, metric, other

### Models

3. **app/Models/SuggestionTask.php**
   - Model para tarefas
   - Relacionamentos: suggestion, completedBy, createdBy
   - Métodos: start(), complete(), uncomplete(), isCompleted(), isPending(), isInProgress(), isLinkedToStep(), isGeneral()
   - Scopes: pending(), inProgress(), completed(), forStep(), general(), withStep()

4. **app/Models/SuggestionImpact.php**
   - Model para impactos
   - Relacionamento: suggestion
   - Métodos: isMetric(), getMetadata(), setMetadata(), getTypes()
   - Scopes: coupons(), campaigns(), methods(), metrics(), other()

### Controllers

5. **app/Http/Controllers/Api/SuggestionTaskController.php**
   - CRUD completo de tarefas
   - Endpoints:
     - GET /suggestions/{uuid}/tasks - Listar tarefas
     - POST /suggestions/{uuid}/tasks - Criar tarefa
     - PATCH /suggestions/{uuid}/tasks/{id} - Atualizar tarefa
     - DELETE /suggestions/{uuid}/tasks/{id} - Deletar tarefa

6. **app/Http/Controllers/Api/SuggestionImpactController.php**
   - CRUD completo de impactos
   - Endpoints:
     - GET /suggestions/{uuid}/impacts - Listar impactos
     - POST /suggestions/{uuid}/impacts - Criar impacto
     - PATCH /suggestions/{uuid}/impacts/{id} - Atualizar impacto
     - DELETE /suggestions/{uuid}/impacts/{id} - Deletar impacto

## Arquivos Modificados

### Models

7. **app/Models/Suggestion.php**
   - Adicionado relacionamento `tasks()` - hasMany(SuggestionTask)
   - Adicionado relacionamento `impacts()` - hasMany(SuggestionImpact)
   - Adicionado método `getTasksProgressAttribute()` - Calcula % de tarefas completas

### Controllers

8. **app/Http/Controllers/Api/SuggestionCommentController.php**
   - CORRIGIDO: Bug "Cannot read properties of undefined (reading 'id')"
   - Linha 38: Adicionada verificação `$comment->user ? [...] : null`

### Routes

9. **routes/api.php**
   - Adicionados imports: SuggestionTaskController, SuggestionImpactController
   - Adicionadas rotas para tasks (4 rotas)
   - Adicionadas rotas para impacts (4 rotas)
   - Marcado steps como "Legacy" para manter compatibilidade

## Estrutura de Dados

### Tabela suggestion_tasks

```sql
CREATE TABLE suggestion_tasks (
    id BIGSERIAL PRIMARY KEY,
    suggestion_id BIGINT REFERENCES suggestions(id) ON DELETE CASCADE,
    step_index SMALLINT,  -- índice do passo da IA (0, 1, 2...) ou NULL se tarefa geral
    title VARCHAR(500) NOT NULL,
    description TEXT,
    status VARCHAR(20) DEFAULT 'pending',  -- pending, in_progress, completed
    due_date DATE,
    completed_at TIMESTAMP,
    completed_by BIGINT REFERENCES users(id),
    created_by BIGINT REFERENCES users(id),
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    INDEX (suggestion_id, status),
    INDEX (step_index)
);
```

### Tabela suggestion_impacts

```sql
CREATE TABLE suggestion_impacts (
    id BIGSERIAL PRIMARY KEY,
    suggestion_id BIGINT REFERENCES suggestions(id) ON DELETE CASCADE,
    type VARCHAR(50) NOT NULL,  -- coupon, campaign, method, metric, other
    label VARCHAR(255) NOT NULL,  -- nome do campo (ex: "Cupom utilizado", "Campanha")
    value TEXT,  -- valor do campo
    numeric_value DECIMAL(15,2),  -- valor numérico (para métricas)
    metadata JSON,  -- dados extras
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    INDEX (suggestion_id, type)
);
```

## Conceitos Principais

### 1. Separação de Passos e Tarefas

- **PASSOS (Steps)**: Vêm do campo `recommended_action` da sugestão (array JSON)
  - São FIXOS e servem como REFERÊNCIA
  - Gerados pela IA na análise
  - Não devem ser editados pelo cliente

- **TAREFAS (Tasks)**: Criadas pelo cliente para implementar a sugestão
  - Podem estar vinculadas a um passo específico (via `step_index`)
  - Podem ser tarefas gerais (step_index = null)
  - Podem ser marcadas como concluídas
  - Têm data de vencimento, responsáveis, etc.

### 2. Sistema de Impacto nas Vendas

- **Dinâmico**: Campos flexíveis para registrar qualquer tipo de impacto
- **Tipos**:
  - `coupon`: Cupons utilizados na implementação
  - `campaign`: Nome de campanhas criadas
  - `method`: Métodos/ferramentas utilizados
  - `metric`: Métricas mensuráveis (antes/depois)
  - `other`: Outros tipos de impacto

- **Estrutura**:
  - `label`: Nome do campo (ex: "Cupom utilizado", "Taxa de conversão")
  - `value`: Valor textual
  - `numeric_value`: Valor numérico (para métricas)
  - `metadata`: JSON com dados extras (ex: {before: 2.5, after: 3.2, variation: 28})

## Exemplo de Uso

### Criar uma Tarefa Vinculada a um Passo

```json
POST /api/suggestions/{uuid}/tasks
{
  "step_index": 0,  // Primeiro passo do recommended_action
  "title": "Configurar cupom PRIMEIRACOMPRA",
  "description": "Criar cupom de 10% conforme sugerido",
  "status": "pending",
  "due_date": "2026-02-15"
}
```

### Criar uma Tarefa Geral

```json
POST /api/suggestions/{uuid}/tasks
{
  "step_index": null,  // Não vinculada a passo específico
  "title": "Reunião com equipe de marketing",
  "description": "Alinhar estratégia de lançamento",
  "status": "pending",
  "due_date": "2026-02-01"
}
```

### Registrar Impacto - Cupom Utilizado

```json
POST /api/suggestions/{uuid}/impacts
{
  "type": "coupon",
  "label": "Cupom utilizado",
  "value": "PRIMEIRACOMPRA",
  "metadata": {
    "discount": 10,
    "discount_type": "percentage",
    "max_uses": 100
  }
}
```

### Registrar Impacto - Métrica (Antes/Depois)

```json
POST /api/suggestions/{uuid}/impacts
{
  "type": "metric",
  "label": "Taxa de conversão",
  "numeric_value": 3.2,  // Valor atual
  "metadata": {
    "before": 2.5,
    "after": 3.2,
    "variation": 28,
    "unit": "%",
    "period": "últimos 30 dias"
  }
}
```

### Registrar Impacto - Campanha

```json
POST /api/suggestions/{uuid}/impacts
{
  "type": "campaign",
  "label": "Campanha criada",
  "value": "Black Friday 2026 - Primeira Compra",
  "metadata": {
    "platform": "Email + Instagram Ads",
    "budget": 5000,
    "start_date": "2026-02-01",
    "end_date": "2026-02-28"
  }
}
```

## Passos para Executar

1. **Rodar as migrations**:
   ```bash
   php artisan migrate --path=database/migrations/2026_01_27_000005_create_suggestion_tasks_table.php
   php artisan migrate --path=database/migrations/2026_01_27_000006_create_suggestion_impacts_table.php
   ```

2. **Verificar as rotas**:
   ```bash
   php artisan route:list --name=suggestions
   ```

3. **Testar os endpoints**:
   - Criar tarefa: POST /api/suggestions/{uuid}/tasks
   - Listar tarefas: GET /api/suggestions/{uuid}/tasks
   - Atualizar tarefa: PATCH /api/suggestions/{uuid}/tasks/{id}
   - Deletar tarefa: DELETE /api/suggestions/{uuid}/tasks/{id}
   - Criar impacto: POST /api/suggestions/{uuid}/impacts
   - Listar impactos: GET /api/suggestions/{uuid}/impacts
   - Atualizar impacto: PATCH /api/suggestions/{uuid}/impacts/{id}
   - Deletar impacto: DELETE /api/suggestions/{uuid}/impacts/{id}

## Compatibilidade

- **SuggestionStep** (steps): Mantido para compatibilidade com código existente
- Rotas de steps continuam funcionando normalmente
- Novo sistema (tasks + impacts) pode coexistir com steps antigos
- Recomenda-se migrar gradualmente para o novo sistema

## Benefícios

1. **Separação de Responsabilidades**: Passos da IA vs. Tarefas do Cliente
2. **Flexibilidade**: Cliente pode criar tarefas customizadas
3. **Rastreabilidade**: Vinculação opcional com passos da IA
4. **Impacto Mensurável**: Sistema dinâmico para registrar resultados
5. **Histórico Completo**: Métricas antes/depois, campanhas utilizadas, etc.

## Próximos Passos (Frontend)

1. **Visualizar Passos da IA**: Mostrar `recommended_action` como referência (read-only)
2. **CRUD de Tarefas**: Interface para criar/editar/deletar tarefas
3. **Vinculação Visual**: Mostrar quais tarefas estão vinculadas a quais passos
4. **Registro de Impacto**: Formulário dinâmico para registrar impactos
5. **Dashboard de Resultados**: Visualizar métricas antes/depois, variação %
6. **Timeline**: Linha do tempo mostrando evolução da implementação
