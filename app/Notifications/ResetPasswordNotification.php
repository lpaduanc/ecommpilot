<?php

namespace App\Notifications;

use App\Services\EmailConfigurationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;

class ResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The password reset token.
     */
    public string $token;

    /**
     * Email configuration identifier to use from admin panel.
     */
    protected string $emailConfigIdentifier = 'password-reset';

    /**
     * Create a new notification instance.
     */
    public function __construct(string $token)
    {
        $this->token = $token;
    }

    /**
     * Get the notification's delivery channels.
     *
     * Uses custom channel if admin configuration exists and has valid credentials,
     * otherwise falls back to default Laravel mail.
     */
    public function via(object $notifiable): array
    {
        // Check if we have admin-configured email for password reset
        $emailService = app(EmailConfigurationService::class);
        $config = $emailService->getByIdentifier($this->emailConfigIdentifier);

        if ($config && $this->hasValidCredentials($config)) {
            // Use custom sending via EmailConfigurationService
            return ['database-email'];
        }

        // Fallback to default Laravel mail
        return ['mail'];
    }

    /**
     * Check if the email configuration has valid credentials.
     */
    protected function hasValidCredentials($config): bool
    {
        $settings = $config->settings ?? [];
        $provider = $config->provider;

        return match ($provider) {
            'mailjet' => ! empty($settings['api_key']) && ! empty($settings['secret_key']),
            'smtp' => ! empty($settings['host']),
            'mailgun' => ! empty($settings['api_key']) && ! empty($settings['domain']),
            'ses' => ! empty($settings['key']) && ! empty($settings['secret']),
            'postmark' => ! empty($settings['token']),
            'resend' => ! empty($settings['api_key']),
            default => false,
        };
    }

    /**
     * Send notification via admin-configured email service.
     *
     * This method is called when the 'database-email' channel is used.
     */
    public function toDatabaseEmail(object $notifiable): void
    {
        $emailService = app(EmailConfigurationService::class);
        $resetUrl = $this->resetUrl($notifiable);

        // Render the email template
        $htmlContent = View::make('emails.reset-password', [
            'userName' => $notifiable->name,
            'resetUrl' => $resetUrl,
        ])->render();

        // Generate plain text version
        $textContent = $this->getTextContent($notifiable->name, $resetUrl);

        $result = $emailService->sendHtmlEmail(
            $this->emailConfigIdentifier,
            $notifiable->email,
            $notifiable->name ?? '',
            'Redefinição de Senha - EcommPilot',
            $htmlContent,
            $textContent
        );

        if (! $result['success']) {
            Log::error('Failed to send password reset email via admin config', [
                'email' => $notifiable->email,
                'error' => $result['message'],
            ]);

            // Throw exception to trigger job retry
            throw new \RuntimeException('Failed to send password reset email: '.$result['message']);
        }

        Log::info('Password reset email sent via admin config', [
            'email' => $notifiable->email,
            'config' => $this->emailConfigIdentifier,
        ]);
    }

    /**
     * Get the mail representation of the notification (fallback).
     */
    public function toMail(object $notifiable): MailMessage
    {
        $resetUrl = $this->resetUrl($notifiable);

        return (new MailMessage)
            ->subject('Redefinição de Senha - EcommPilot')
            ->view('emails.reset-password', [
                'userName' => $notifiable->name,
                'resetUrl' => $resetUrl,
            ]);
    }

    /**
     * Get the reset password URL for the given notifiable.
     */
    protected function resetUrl(object $notifiable): string
    {
        // URL para o frontend Vue.js
        $frontendUrl = config('app.frontend_url', config('app.url'));

        return $frontendUrl.'/reset-password?token='.$this->token.'&email='.urlencode($notifiable->email);
    }

    /**
     * Get plain text version of the email.
     */
    protected function getTextContent(string $userName, string $resetUrl): string
    {
        return <<<TEXT
Olá, {$userName}!

Você está recebendo este e-mail porque recebemos uma solicitação de redefinição de senha para sua conta no EcommPilot.

Para redefinir sua senha, acesse o link abaixo:
{$resetUrl}

ATENÇÃO: Este link de redefinição expira em 60 minutos.

Se você não solicitou a redefinição de senha, ignore este e-mail. Nenhuma ação adicional é necessária e sua senha permanecerá a mesma.

Atenciosamente,
Equipe EcommPilot

---
Este é um e-mail automático. Por favor, não responda.
TEXT;
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
