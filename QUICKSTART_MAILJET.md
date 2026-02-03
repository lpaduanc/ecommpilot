# Quick Start - Mailjet para Reset de Senha

## TL;DR - Configuração Rápida

### 1. Obter Credenciais do Mailjet

```
1. Crie conta em: https://www.mailjet.com/
2. Acesse: https://app.mailjet.com/account/api_keys
3. Copie API Key e Secret Key
```

### 2. Verificar E-mail Remetente

```
1. Acesse: https://app.mailjet.com/account/sender
2. Adicione: no-reply-reset-password@ecommpilot.com.br
3. Verifique o e-mail (clique no link recebido)
```

### 3. Configurar .env

```bash
# Abra .env e configure:
MAIL_MAILER=mailjet
MAIL_FROM_ADDRESS="no-reply-reset-password@ecommpilot.com.br"
MAIL_FROM_NAME="EcommPilot"

MAILJET_APIKEY=cole_sua_api_key_aqui
MAILJET_APISECRET=cole_sua_secret_key_aqui

APP_FRONTEND_URL="http://localhost:5173"  # ou URL de produção
```

### 4. Limpar Cache

```bash
php artisan config:clear
```

### 5. Testar

```bash
php artisan tinker
```

```php
Password::sendResetLink(['email' => 'seu-email@exemplo.com']);
```

**Pronto!** Verifique sua caixa de entrada.

---

## Desenvolvimento vs Produção

### Desenvolvimento (não envia e-mails reais)

```bash
MAIL_MAILER=log
```

E-mails aparecem em `storage/logs/laravel.log`

### Produção (envia e-mails via Mailjet)

```bash
MAIL_MAILER=mailjet
MAILJET_APIKEY=sua_chave_real
MAILJET_APISECRET=sua_secret_real
```

---

## Checklist Pré-Deploy

- [ ] Credenciais configuradas no `.env`
- [ ] E-mail remetente verificado no Mailjet
- [ ] `APP_FRONTEND_URL` apontando para URL correta
- [ ] Teste de envio bem-sucedido
- [ ] Queue worker rodando
- [ ] E-mail não caindo em spam

---

## Comandos Úteis

```bash
# Limpar cache de config
php artisan config:clear

# Ver configuração atual
php artisan tinker
>>> config('mail.default')

# Enviar teste
php artisan tinker
>>> Password::sendResetLink(['email' => 'teste@exemplo.com'])

# Ver logs
tail -f storage/logs/laravel.log

# Processar fila
php artisan queue:work
```

---

## Troubleshooting Rápido

### E-mail não chegou?

1. Verifique se queue worker está rodando
2. Verifique logs: `grep -i mailjet storage/logs/laravel.log`
3. Verifique pasta de spam
4. Acesse dashboard do Mailjet: https://app.mailjet.com/stats

### Erro "Sender not verified"?

- Verifique o e-mail em: https://app.mailjet.com/account/sender
- Aguarde aprovação (pode levar algumas horas)

### Erro "Invalid credentials"?

- Verifique `.env` tem as credenciais corretas
- Execute `php artisan config:clear`
- Confirme no dashboard do Mailjet

---

## Links Importantes

- **Dashboard:** https://app.mailjet.com/
- **API Keys:** https://app.mailjet.com/account/api_keys
- **Sender Verification:** https://app.mailjet.com/account/sender
- **Stats:** https://app.mailjet.com/stats
- **Docs:** https://dev.mailjet.com/

---

## Documentação Completa

Para documentação detalhada, consulte:

- **Setup Completo:** `docs/MAILJET_SETUP.md`
- **Guia de Testes:** `docs/MAILJET_TESTING.md`
- **Resumo de Implementação:** `MAILJET_IMPLEMENTATION_SUMMARY.md`

---

## Suporte

Em caso de problemas, consulte a documentação ou verifique:

1. Logs do Laravel: `storage/logs/laravel.log`
2. Dashboard do Mailjet: https://app.mailjet.com/stats
3. Status da API: https://status.mailjet.com/
