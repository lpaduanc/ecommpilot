# Gerenciamento de Configurações de E-mail

Backend para gerenciar múltiplas configurações de e-mail no sistema Ecommpilot.

## Recursos

- **Múltiplas configurações**: Configure diferentes provedores/configurações para diferentes propósitos
- **Provedores suportados**: SMTP, Mailgun, Amazon SES, Postmark, Resend
- **Segurança**: Credenciais criptografadas no banco de dados
- **Mascaramento**: Valores sensíveis são mascarados nas respostas da API
- **Testes integrados**: Endpoint para testar envio de e-mail com cada configuração

## Estrutura

### Tabela: `email_configurations`

```
- id
- name (string) - Nome/propósito da configuração
- identifier (string, unique) - Slug único
- provider (string) - smtp|mailgun|ses|postmark|resend
- is_active (boolean)
- settings (json, encrypted) - Configurações do provedor
- timestamps
```

### Campos do settings (por provedor)

#### Comuns (todos os provedores)
```json
{
  "from_address": "noreply@example.com",
  "from_name": "Nome Remetente"
}
```

#### SMTP
```json
{
  "host": "smtp.example.com",
  "port": 587,
  "username": "user@example.com",
  "password": "secret",
  "encryption": "tls"
}
```

#### Mailgun
```json
{
  "domain": "example.com",
  "api_key": "key-xxxxx",
  "api_url": "api.mailgun.net"
}
```

#### Amazon SES
```json
{
  "key": "AWS_ACCESS_KEY",
  "secret": "AWS_SECRET_KEY",
  "region": "us-east-1"
}
```

#### Postmark
```json
{
  "token": "xxxxx-xxxxx-xxxxx"
}
```

#### Resend
```json
{
  "api_key": "re_xxxxx"
}
```

## API Endpoints

Todas as rotas requerem autenticação e permissão `admin.access`.

### Listar Configurações
```http
GET /api/admin/settings/email
```

**Resposta:**
```json
{
  "data": [
    {
      "id": 1,
      "name": "Sincronização",
      "identifier": "sync",
      "provider": "smtp",
      "is_active": true,
      "settings": {
        "from_address": "sync@ecommpilot.com.br",
        "from_name": "Ecommpilot Sync",
        "host": "smtp.example.com",
        "port": 587,
        "username": "sync@example.com",
        "password": "sec****ret",
        "encryption": "tls"
      },
      "created_at": "2026-01-17T10:00:00.000000Z",
      "updated_at": "2026-01-17T10:00:00.000000Z"
    }
  ]
}
```

### Criar Configuração
```http
POST /api/admin/settings/email
Content-Type: application/json

{
  "name": "Sincronização",
  "identifier": "sync",
  "provider": "smtp",
  "is_active": true,
  "settings": {
    "from_address": "sync@ecommpilot.com.br",
    "from_name": "Ecommpilot Sync",
    "host": "smtp.example.com",
    "port": 587,
    "username": "sync@example.com",
    "password": "secret123",
    "encryption": "tls"
  }
}
```

**Resposta:** Status 201
```json
{
  "message": "Configuração de e-mail criada com sucesso.",
  "data": { ... }
}
```

### Visualizar Configuração
```http
GET /api/admin/settings/email/{id}
```

### Atualizar Configuração
```http
PUT /api/admin/settings/email/{id}
Content-Type: application/json

{
  "name": "Novo Nome",
  "is_active": false
}
```

**Nota:** Valores mascarados (com `****`) não serão atualizados. Para atualizar credenciais, envie o valor completo.

### Excluir Configuração
```http
DELETE /api/admin/settings/email/{id}
```

**Resposta:**
```json
{
  "message": "Configuração de e-mail excluída com sucesso."
}
```

### Testar Configuração
```http
POST /api/admin/settings/email/{id}/test
Content-Type: application/json

{
  "test_email": "test@example.com"
}
```

**Resposta (sucesso):**
```json
{
  "success": true,
  "message": "E-mail de teste enviado com sucesso!"
}
```

**Resposta (erro):**
```json
{
  "success": false,
  "message": "Erro ao enviar e-mail: Connection refused"
}
```

## Uso no Código

### Obter Configuração por Identifier

```php
use App\Services\EmailConfigurationService;

$emailService = app(EmailConfigurationService::class);

// Obter e configurar mailer
$config = $emailService->useConfiguration('sync');

if ($config) {
    // O mailer já está configurado, pode enviar e-mail
    Mail::to('user@example.com')
        ->send(new YourMailable());
}
```

### Enviar E-mail com Configuração Específica

```php
use App\Services\EmailConfigurationService;
use Illuminate\Support\Facades\Mail;

class SyncNotificationService
{
    public function sendNotification(string $to, array $data): void
    {
        $emailService = app(EmailConfigurationService::class);

        // Configura o mailer com a configuração "sync"
        $config = $emailService->useConfiguration('sync');

        if (!$config || !$config->is_active) {
            throw new \Exception('Configuração de e-mail "sync" não encontrada ou inativa');
        }

        Mail::raw('Notificação de sincronização', function ($message) use ($to) {
            $message->to($to)
                ->subject('Sincronização Completa');
        });
    }
}
```

### Criar Mailable Personalizado

```php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SyncCompletedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public array $syncData
    ) {}

    public function build()
    {
        // from() será automaticamente definido pela configuração
        return $this->view('emails.sync-completed')
            ->subject('Sincronização Completa - Ecommpilot');
    }
}
```

```php
// Uso
use App\Services\EmailConfigurationService;
use App\Mail\SyncCompletedMail;

$emailService = app(EmailConfigurationService::class);
$emailService->useConfiguration('sync');

Mail::to($user->email)->send(new SyncCompletedMail($syncData));
```

## Exemplos de Casos de Uso

### 1. Sincronização de Dados
```json
{
  "name": "Sincronização",
  "identifier": "sync",
  "provider": "smtp",
  "settings": {
    "from_address": "contato@ecommpilot.com.br",
    "from_name": "Ecommpilot - Sincronização"
  }
}
```

### 2. Notificações de IA
```json
{
  "name": "Análise IA",
  "identifier": "ai-analysis",
  "provider": "resend",
  "settings": {
    "from_address": "assistente_ia@ecommpilot.com.br",
    "from_name": "Assistente IA - Ecommpilot",
    "api_key": "re_xxxxx"
  }
}
```

### 3. Marketing
```json
{
  "name": "Marketing",
  "identifier": "marketing",
  "provider": "mailgun",
  "settings": {
    "from_address": "marketing@ecommpilot.com.br",
    "from_name": "Ecommpilot Marketing",
    "domain": "ecommpilot.com.br",
    "api_key": "key-xxxxx"
  }
}
```

## Segurança

### Criptografia
Todos os valores em `settings` são automaticamente criptografados no banco de dados usando Laravel's `encrypted:array` cast.

### Mascaramento
Campos sensíveis (password, api_key, secret, token, key) são mascarados nas respostas da API:
- `"verylongpassword123"` → `"very****123"`
- `"short"` → `"********"`

### Validação
- Apenas usuários com permissão `admin.access` podem gerenciar configurações
- Validação de provedores suportados
- Validação de campos obrigatórios por provedor
- Identifier não pode ser alterado após criação

## Testes

Execute os testes:
```bash
php artisan test --filter=EmailConfigurationTest
```

Testes incluídos:
- ✓ Listagem de configurações
- ✓ Criação de configuração SMTP
- ✓ Atualização de configuração
- ✓ Exclusão de configuração
- ✓ Controle de acesso (apenas admin)
- ✓ Criptografia de settings
- ✓ Mascaramento de valores sensíveis

## Arquivos Criados

```
app/
├── Http/
│   ├── Controllers/Api/
│   │   └── AdminEmailConfigurationController.php
│   └── Requests/
│       ├── StoreEmailConfigurationRequest.php
│       └── UpdateEmailConfigurationRequest.php
├── Models/
│   └── EmailConfiguration.php
└── Services/
    └── EmailConfigurationService.php

database/
├── factories/
│   └── EmailConfigurationFactory.php
└── migrations/
    └── 2026_01_17_010937_create_email_configurations_table.php

tests/Feature/
└── EmailConfigurationTest.php

routes/
└── api.php (updated)
```
