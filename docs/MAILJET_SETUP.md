# Configuração do Mailjet para E-mails de Reset de Senha

Este documento descreve como configurar o Mailjet para envio de e-mails transacionais no EcommPilot.

## Pré-requisitos

1. Conta no Mailjet (https://www.mailjet.com/)
2. Credenciais API (API Key e Secret Key)

## Obter Credenciais do Mailjet

1. Acesse https://app.mailjet.com/account/api_keys
2. Copie as credenciais:
   - **API Key** (MAILJET_APIKEY)
   - **Secret Key** (MAILJET_APISECRET)

## Configurar Sender Address

No Mailjet, você precisa verificar o domínio ou e-mail remetente:

1. Acesse https://app.mailjet.com/account/sender
2. Adicione e verifique o e-mail: `no-reply-reset-password@ecommpilot.com.br`
3. Ou verifique o domínio completo: `ecommpilot.com.br`

### Verificação de Domínio (Recomendado)

Para produção, recomendamos verificar o domínio completo adicionando registros DNS:

```
Tipo: TXT
Nome: @
Valor: [fornecido pelo Mailjet]

Tipo: TXT
Nome: mailjet._domainkey
Valor: [fornecido pelo Mailjet]
```

## Configuração no Laravel

### 1. Variáveis de Ambiente

Adicione no arquivo `.env`:

```bash
# Mail Configuration
MAIL_MAILER=mailjet
MAIL_FROM_ADDRESS="no-reply-reset-password@ecommpilot.com.br"
MAIL_FROM_NAME="EcommPilot"

# Mailjet Configuration
MAILJET_APIKEY=sua_api_key_aqui
MAILJET_APISECRET=sua_secret_key_aqui

# Frontend URL (para links de reset de senha)
APP_FRONTEND_URL="https://app.ecommpilot.com.br"
```

### 2. Estrutura de Arquivos

A implementação do Mailjet está distribuída em:

```
app/
├── Mail/Transport/MailjetTransport.php      # Transport customizado
├── Notifications/ResetPasswordNotification.php  # Notification de reset
└── Models/User.php                          # Model com método sendPasswordResetNotification()

resources/views/emails/
└── reset-password.blade.php                 # Template HTML do e-mail

config/
├── mail.php                                 # Configuração do mailer
├── mailjet.php                              # Configuração específica do Mailjet
└── services.php                             # Credenciais da API
```

## Fluxo de Reset de Senha

### 1. Usuário Solicita Reset

```bash
POST /api/auth/forgot-password
Content-Type: application/json

{
  "email": "usuario@example.com"
}
```

### 2. Laravel Envia E-mail

O Laravel automaticamente:
1. Valida o e-mail
2. Gera um token de reset
3. Envia notificação via `ResetPasswordNotification`
4. A notificação usa o `MailjetTransport` configurado

### 3. E-mail Enviado

O usuário recebe um e-mail profissional com:
- Design responsivo
- Botão de ação destacado
- Link alternativo (caso o botão não funcione)
- Aviso de expiração (60 minutos)
- Dicas de segurança

### 4. Usuário Reseta Senha

```bash
POST /api/auth/reset-password
Content-Type: application/json

{
  "token": "token_recebido_por_email",
  "email": "usuario@example.com",
  "password": "nova_senha_segura",
  "password_confirmation": "nova_senha_segura"
}
```

## Template do E-mail

O template (`resources/views/emails/reset-password.blade.php`) inclui:

- ✅ Design moderno e profissional
- ✅ Responsivo (mobile-friendly)
- ✅ Branding da EcommPilot
- ✅ Botão de ação destacado
- ✅ Link alternativo para copiar/colar
- ✅ Avisos de segurança
- ✅ Texto em português brasileiro

## Recursos Avançados

### Retry Automático

O `MailjetTransport` implementa retry automático com backoff:

```php
private int $maxRetries = 3;
private array $retryDelays = [5, 15, 30]; // segundos
```

### Logging

Todos os envios são logados:
- Sucesso: `Log::channel('mail')->info()`
- Falha: `Log::channel('mail')->error()`
- Retry: `Log::channel('mail')->warning()`

### Queue (Assíncrono)

A `ResetPasswordNotification` implementa `ShouldQueue`, enviando e-mails de forma assíncrona:

```php
class ResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;
    // ...
}
```

## Testes

### Teste Manual

```bash
# 1. Certifique-se de que o queue worker está rodando
php artisan queue:work

# 2. Solicite reset via API ou Tinker
php artisan tinker
>>> $user = User::first();
>>> Password::sendResetLink(['email' => $user->email]);
```

### Verificar Logs

```bash
# Logs do Mailjet
tail -f storage/logs/mail.log

# Logs gerais
tail -f storage/logs/laravel.log
```

### Teste de Produção

Recomendado testar em ambiente staging antes de produção:

1. Configure MAIL_MAILER=log no desenvolvimento
2. Configure MAIL_MAILER=mailjet no staging
3. Teste todos os cenários:
   - E-mail válido
   - E-mail inexistente
   - Link expirado
   - Reset bem-sucedido

## Troubleshooting

### E-mail não está sendo enviado

1. **Verifique credenciais:**
   ```bash
   php artisan tinker
   >>> config('services.mailjet.key')
   >>> config('services.mailjet.secret')
   ```

2. **Verifique sender address:**
   - Acesse https://app.mailjet.com/account/sender
   - Confirme que o e-mail está verificado

3. **Verifique queue:**
   ```bash
   php artisan queue:work --queue=default --tries=3
   ```

### E-mail cai no spam

1. Configure SPF, DKIM e DMARC no DNS
2. Use domínio verificado no Mailjet
3. Evite palavras spam no assunto
4. Configure autenticação do domínio

### Rate Limit

O Mailjet tem limites por plano:
- Free: 200 e-mails/dia, 6.000 e-mails/mês
- Essential: ilimitado (com limites de taxa)

Se atingir o limite, considere:
- Upgrade do plano
- Implementar throttling customizado
- Usar queue com delay

## Links Úteis

- [Mailjet Dashboard](https://app.mailjet.com/)
- [Mailjet API Docs](https://dev.mailjet.com/)
- [Mailjet PHP SDK](https://github.com/mailjet/mailjet-apiv3-php)
- [Laravel Mail Docs](https://laravel.com/docs/mail)

## Ambiente de Desenvolvimento

Para desenvolvimento, recomendamos usar `MAIL_MAILER=log` para evitar envios reais:

```bash
# .env (desenvolvimento)
MAIL_MAILER=log
```

E-mails serão salvos em `storage/logs/laravel.log` ao invés de serem enviados.

Alternativamente, use [Mailtrap](https://mailtrap.io/) ou [MailHog](https://github.com/mailhog/MailHog) para testar e-mails localmente.
