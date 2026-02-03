# Testando a Implementação do Mailjet

## Comandos para Testar

### 1. Limpar Cache de Configuração

Sempre que alterar o `.env`, limpe o cache:

```bash
php artisan config:clear
```

### 2. Verificar Configuração

```bash
php artisan tinker
```

No tinker, execute:

```php
// Verificar mailer configurado
config('mail.default');
// Deve retornar: "mailjet" (em produção) ou "log" (desenvolvimento)

// Verificar credenciais
config('services.mailjet.key');
config('services.mailjet.secret');

// Verificar remetente
config('mail.from.address');
// Deve retornar: "no-reply-reset-password@ecommpilot.com.br"

// Verificar frontend URL
config('app.frontend_url');
```

### 3. Testar Envio de Reset de Senha

#### Via Tinker (Recomendado para testes)

```bash
php artisan tinker
```

```php
use App\Models\User;
use Illuminate\Support\Facades\Password;

// Pegar um usuário de teste
$user = User::first();

// Ou criar um usuário de teste
$user = User::create([
    'name' => 'Teste Mailjet',
    'email' => 'seu-email@exemplo.com', // Use seu e-mail real
    'password' => 'senha123',
]);

// Enviar e-mail de reset
Password::sendResetLink(['email' => $user->email]);

// Se retornar "passwords.sent", o e-mail foi enviado com sucesso
```

#### Via API (Teste Real)

```bash
# Com curl
curl -X POST http://localhost:8000/api/auth/forgot-password \
  -H "Content-Type: application/json" \
  -d '{"email": "seu-email@exemplo.com"}'

# Resposta esperada:
# {
#   "message": "Se o e-mail estiver cadastrado, você receberá um link de redefinição."
# }
```

### 4. Verificar Queue (se usando filas)

Se `ResetPasswordNotification` está na fila:

```bash
# Iniciar worker
php artisan queue:work --queue=default --tries=3 --timeout=60

# Em outra janela, monitore os logs
tail -f storage/logs/laravel.log
```

### 5. Verificar Logs

#### Logs do Laravel

```bash
# Logs gerais
tail -f storage/logs/laravel.log

# Verificar se o e-mail foi processado
grep -i "mailjet" storage/logs/laravel.log

# Verificar erros
grep -i "error" storage/logs/laravel.log
```

#### Logs do Mailjet (se configurado)

```bash
tail -f storage/logs/mail.log
```

## Cenários de Teste

### ✅ Teste 1: E-mail Válido

**Input:**
```json
{
  "email": "usuario.existente@exemplo.com"
}
```

**Esperado:**
- Status 200
- Mensagem genérica (não revela se e-mail existe)
- E-mail enviado para a caixa de entrada

**Verificar:**
1. Abrir caixa de e-mail
2. Verificar se recebeu o e-mail
3. Clicar no botão "Redefinir Senha"
4. Verificar se redireciona para o frontend

### ✅ Teste 2: E-mail Inexistente

**Input:**
```json
{
  "email": "nao.existe@exemplo.com"
}
```

**Esperado:**
- Status 200 (mesma resposta do teste 1)
- Mensagem genérica (SEGURANÇA: não revela que e-mail não existe)
- Nenhum e-mail enviado

### ✅ Teste 3: Link Expirado

**Passos:**
1. Solicite reset de senha
2. Aguarde 61 minutos (ou altere `config/auth.php` para expiração menor)
3. Tente usar o link

**Esperado:**
- Mensagem de token expirado

### ✅ Teste 4: Reset Bem-Sucedido

**Passos:**
1. Solicite reset de senha
2. Receba o e-mail
3. Clique no link
4. Digite nova senha
5. Confirme nova senha

**Input API:**
```json
{
  "token": "token_do_email",
  "email": "usuario@exemplo.com",
  "password": "nova_senha_123",
  "password_confirmation": "nova_senha_123"
}
```

**Esperado:**
- Status 200
- Mensagem de sucesso
- Login com nova senha funcionando

### ✅ Teste 5: Retry em Caso de Falha

**Simular falha temporária:**

1. Configure credenciais inválidas temporariamente:
   ```bash
   MAILJET_APIKEY=invalid
   MAILJET_APISECRET=invalid
   ```

2. Tente enviar e-mail

3. Verifique logs:
   ```bash
   grep "Mailjet send attempt failed" storage/logs/laravel.log
   ```

**Esperado:**
- 3 tentativas de envio
- Delays entre tentativas: 5s, 15s, 30s
- Erro final após 3 tentativas

## Checklist de Produção

Antes de colocar em produção, verifique:

- [ ] Credenciais do Mailjet configuradas no `.env`
- [ ] E-mail remetente verificado no Mailjet
- [ ] Domínio configurado com SPF/DKIM
- [ ] `APP_FRONTEND_URL` apontando para URL do frontend em produção
- [ ] Queue worker rodando (`php artisan queue:work`)
- [ ] Logs sendo monitorados
- [ ] Teste de envio real bem-sucedido
- [ ] E-mail não caindo em spam
- [ ] Template renderizando corretamente em:
  - [ ] Gmail
  - [ ] Outlook
  - [ ] Apple Mail
  - [ ] Mobile (iOS/Android)

## Troubleshooting

### Problema: "Connection refused"

**Causa:** Credenciais inválidas ou API Key bloqueada

**Solução:**
1. Verifique credenciais no `.env`
2. Confirme na dashboard do Mailjet
3. Verifique se a API Key está ativa

### Problema: "Sender not verified"

**Causa:** E-mail remetente não verificado no Mailjet

**Solução:**
1. Acesse https://app.mailjet.com/account/sender
2. Adicione e verifique o e-mail ou domínio
3. Aguarde aprovação (pode levar algumas horas)

### Problema: E-mail caindo em spam

**Causa:** Falta de autenticação ou reputação baixa

**Solução:**
1. Configure SPF, DKIM e DMARC no DNS
2. Use domínio verificado
3. Evite palavras spam no assunto
4. Comece com poucos e-mails e aumente gradualmente

### Problema: "Rate limit exceeded"

**Causa:** Limite de envios do plano excedido

**Solução:**
1. Verifique seu plano no Mailjet
2. Considere upgrade
3. Implemente throttling na aplicação

### Problema: E-mail não aparece na caixa de entrada

**Passos de diagnóstico:**

1. **Verifique logs:**
   ```bash
   grep "Mailjet email sent successfully" storage/logs/laravel.log
   ```

2. **Verifique dashboard do Mailjet:**
   - Acesse https://app.mailjet.com/stats
   - Veja se o e-mail foi enviado
   - Verifique status de entrega

3. **Verifique spam:**
   - Olhe na pasta de spam/lixo eletrônico

4. **Verifique queue:**
   ```bash
   php artisan queue:failed
   ```

## Exemplos de Código

### Enviar Reset Programaticamente

```php
use App\Models\User;
use Illuminate\Support\Facades\Password;

// Método 1: Via Password Facade (Recomendado)
$status = Password::sendResetLink(['email' => 'usuario@exemplo.com']);

if ($status === Password::RESET_LINK_SENT) {
    // E-mail enviado com sucesso
}

// Método 2: Via Notification Direta
$user = User::where('email', 'usuario@exemplo.com')->first();
$token = app('auth.password.broker')->createToken($user);
$user->sendPasswordResetNotification($token);
```

### Customizar Template

Para customizar o template do e-mail, edite:

```
resources/views/emails/reset-password.blade.php
```

Variáveis disponíveis:
- `$userName`: Nome do usuário
- `$resetUrl`: URL completa para reset

### Alterar Tempo de Expiração

Edite `config/auth.php`:

```php
'passwords' => [
    'users' => [
        'provider' => 'users',
        'table' => 'password_reset_tokens',
        'expire' => 60, // minutos
        'throttle' => 60, // segundos entre tentativas
    ],
],
```

## Monitoramento

### Métricas Importantes

1. **Taxa de entrega:** Quantos e-mails chegaram
2. **Taxa de abertura:** Quantos usuários abriram
3. **Taxa de clique:** Quantos clicaram no botão
4. **Taxa de conversão:** Quantos resetaram a senha

### Dashboard do Mailjet

Acesse https://app.mailjet.com/stats para ver:
- E-mails enviados
- Taxa de entrega
- Bounces (rejeições)
- Spam reports
- Métricas de engajamento

### Logs do Laravel

Configure log channel específico para e-mails em `config/logging.php`:

```php
'mail' => [
    'driver' => 'daily',
    'path' => storage_path('logs/mail.log'),
    'level' => 'info',
    'days' => 14,
],
```

## Boas Práticas

1. ✅ **Use queue** para envios assíncronos
2. ✅ **Monitore logs** regularmente
3. ✅ **Configure retry** com backoff exponencial
4. ✅ **Teste em staging** antes de produção
5. ✅ **Use domínio verificado** para melhor deliverability
6. ✅ **Implemente rate limiting** para prevenir abuso
7. ✅ **Mantenha templates** simples e responsivos
8. ✅ **Evite palavras spam** no assunto e corpo
9. ✅ **Configure SPF/DKIM/DMARC** no DNS
10. ✅ **Monitore reputação** do domínio

## Recursos Adicionais

- [Documentação Mailjet](https://dev.mailjet.com/)
- [Laravel Mail Docs](https://laravel.com/docs/mail)
- [Laravel Notifications](https://laravel.com/docs/notifications)
- [Test Email HTML](https://www.litmus.com/)
- [Check Email Spam Score](https://www.mail-tester.com/)
