<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\EmailConfiguration;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Mailjet\Client;
use Mailjet\Resources;

class EmailConfigurationService
{
    /**
     * Safe log to mail channel - silently ignores permission errors.
     */
    private function logMail(string $level, string $message, array $context = []): void
    {
        try {
            Log::channel('mail')->{$level}($message, $context);
        } catch (\Throwable $e) {
            // Fallback to default log channel if mail channel fails (e.g., permission issues)
            try {
                Log::{$level}('[mail] '.$message, $context);
            } catch (\Throwable) {
                // Silently ignore if even default logging fails
            }
        }
    }

    /**
     * Get all email configurations.
     */
    public function getAll(): \Illuminate\Database\Eloquent\Collection
    {
        return EmailConfiguration::orderBy('name')->get();
    }

    /**
     * Get active email configurations.
     */
    public function getActive(): \Illuminate\Database\Eloquent\Collection
    {
        return EmailConfiguration::active()->orderBy('name')->get();
    }

    /**
     * Get configuration by ID.
     */
    public function getById(int $id): ?EmailConfiguration
    {
        return EmailConfiguration::find($id);
    }

    /**
     * Get configuration by identifier.
     */
    public function getByIdentifier(string $identifier): ?EmailConfiguration
    {
        return EmailConfiguration::byIdentifier($identifier)->active()->first();
    }

    /**
     * Create a new email configuration.
     */
    public function create(array $data): EmailConfiguration
    {
        // Generate identifier from name if not provided
        if (empty($data['identifier'])) {
            $data['identifier'] = Str::slug($data['name']);
        }

        return EmailConfiguration::create($data);
    }

    /**
     * Update an email configuration.
     */
    public function update(int $id, array $data): EmailConfiguration
    {
        $configuration = EmailConfiguration::findOrFail($id);

        // Don't allow changing identifier after creation
        unset($data['identifier']);

        $configuration->update($data);

        return $configuration->fresh();
    }

    /**
     * Delete an email configuration.
     */
    public function delete(int $id): bool
    {
        $configuration = EmailConfiguration::findOrFail($id);

        return $configuration->delete();
    }

    /**
     * Test email sending with a configuration.
     */
    public function test(int $id, string $toEmail): array
    {
        try {
            $configuration = EmailConfiguration::findOrFail($id);

            if (! $configuration->is_active) {
                return [
                    'success' => false,
                    'message' => 'Configuração está inativa.',
                ];
            }

            $settings = $configuration->settings;
            $fromAddress = $settings['from_address'] ?? config('mail.from.address');
            $fromName = $settings['from_name'] ?? config('mail.from.name');
            $subject = 'Teste de Configuração de E-mail - '.$configuration->name;
            $body = 'Este é um e-mail de teste da configuração: '.$configuration->name;

            // Send based on provider
            switch ($configuration->provider) {
                case 'mailjet':
                    return $this->sendViaMailjet($settings, $toEmail, $fromAddress, $fromName, $subject, $body);

                case 'smtp':
                    return $this->sendViaSmtp($settings, $toEmail, $fromAddress, $fromName, $subject, $body);

                default:
                    // For other providers, use Laravel's Mail facade
                    $this->configureMailer($configuration);
                    Mail::raw($body, function ($message) use ($toEmail, $fromAddress, $fromName, $subject) {
                        $message->to($toEmail)
                            ->subject($subject)
                            ->from($fromAddress, $fromName);
                    });

                    return [
                        'success' => true,
                        'message' => 'E-mail de teste enviado com sucesso!',
                    ];
            }
        } catch (\Exception $e) {
            $errorId = 'err_'.uniqid();
            Log::error('Erro ao enviar e-mail de teste', [
                'error_id' => $errorId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return [
                'success' => false,
                'message' => 'Erro ao enviar e-mail.',
                'error_id' => $errorId,
            ];
        }
    }

    /**
     * Send email directly via Mailjet API.
     */
    private function sendViaMailjet(array $settings, string $to, string $fromAddress, string $fromName, string $subject, string $body): array
    {
        $apiKey = $settings['api_key'] ?? '';
        $secretKey = $settings['secret_key'] ?? '';

        if (empty($apiKey) || empty($secretKey)) {
            return [
                'success' => false,
                'message' => 'API Key e Secret Key do Mailjet são obrigatórios.',
            ];
        }

        // Log tentativa de envio
        $this->logMail('info', 'Tentando enviar email via Mailjet', [
            'to' => $to,
            'from' => $fromAddress,
            'subject' => $subject,
            'provider' => 'mailjet',
        ]);

        $client = new Client($apiKey, $secretKey, true, ['version' => 'v3.1']);

        $response = $client->post(Resources::$Email, [
            'body' => [
                'Messages' => [
                    [
                        'From' => [
                            'Email' => $fromAddress,
                            'Name' => $fromName,
                        ],
                        'To' => [
                            [
                                'Email' => $to,
                                'Name' => '',
                            ],
                        ],
                        'Subject' => $subject,
                        'TextPart' => $body,
                        'HTMLPart' => '<p>'.$body.'</p>',
                    ],
                ],
            ],
        ]);

        if ($response->success()) {
            $this->logMail('info', 'Email enviado com sucesso via Mailjet', [
                'to' => $to,
                'from' => $fromAddress,
                'provider' => 'mailjet',
            ]);

            ActivityLog::log('email.test_sent', null, [
                'provider' => 'mailjet',
                'to' => $to,
                'from' => $fromAddress,
            ]);

            return [
                'success' => true,
                'message' => 'E-mail de teste enviado com sucesso via Mailjet!',
            ];
        }

        $errorData = $response->getData();

        // Extract error message from Mailjet response
        $errorMessage = 'Erro ao enviar via Mailjet.';
        if (isset($errorData['Messages'][0]['Errors'][0]['ErrorMessage'])) {
            $errorMessage = $errorData['Messages'][0]['Errors'][0]['ErrorMessage'];
        } elseif (isset($errorData['ErrorMessage'])) {
            $errorMessage = $errorData['ErrorMessage'];
        }

        $this->logMail('error', 'Falha ao enviar email via Mailjet', [
            'to' => $to,
            'from' => $fromAddress,
            'status' => $response->getStatus(),
            'error' => $errorMessage,
            'provider' => 'mailjet',
        ]);

        ActivityLog::log('email.test_failed', null, [
            'provider' => 'mailjet',
            'to' => $to,
            'error' => $errorMessage,
        ]);

        return [
            'success' => false,
            'message' => $errorMessage,
        ];
    }

    /**
     * Send email directly via SMTP.
     */
    private function sendViaSmtp(array $settings, string $to, string $fromAddress, string $fromName, string $subject, string $body): array
    {
        // Log tentativa de envio
        $this->logMail('info', 'Tentando enviar email via SMTP', [
            'to' => $to,
            'from' => $fromAddress,
            'subject' => $subject,
            'host' => $settings['host'] ?? 'localhost',
            'provider' => 'smtp',
        ]);

        // Create a fresh SMTP transport with the provided settings
        $transport = new \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport(
            $settings['host'] ?? 'localhost',
            (int) ($settings['port'] ?? 587),
            ($settings['encryption'] ?? 'tls') === 'ssl'
        );

        if (! empty($settings['username'])) {
            $transport->setUsername($settings['username']);
        }
        if (! empty($settings['password'])) {
            $transport->setPassword($settings['password']);
        }

        $mailer = new \Symfony\Component\Mailer\Mailer($transport);

        $email = (new \Symfony\Component\Mime\Email)
            ->from(new \Symfony\Component\Mime\Address($fromAddress, $fromName))
            ->to($to)
            ->subject($subject)
            ->text($body)
            ->html('<p>'.$body.'</p>');

        $mailer->send($email);

        $this->logMail('info', 'Email enviado com sucesso via SMTP', [
            'to' => $to,
            'from' => $fromAddress,
            'host' => $settings['host'] ?? 'localhost',
            'provider' => 'smtp',
        ]);

        ActivityLog::log('email.test_sent', null, [
            'provider' => 'smtp',
            'to' => $to,
            'from' => $fromAddress,
            'host' => $settings['host'] ?? 'localhost',
        ]);

        return [
            'success' => true,
            'message' => 'E-mail de teste enviado com sucesso via SMTP!',
        ];
    }

    /**
     * Configure mailer based on configuration.
     */
    private function configureMailer(EmailConfiguration $configuration): void
    {
        $settings = $configuration->settings;

        switch ($configuration->provider) {
            case 'smtp':
                config([
                    'mail.default' => 'smtp',
                    'mail.mailers.smtp' => [
                        'transport' => 'smtp',
                        'host' => $settings['host'] ?? '',
                        'port' => $settings['port'] ?? 587,
                        'encryption' => $settings['encryption'] ?? 'tls',
                        'username' => $settings['username'] ?? '',
                        'password' => $settings['password'] ?? '',
                        'timeout' => null,
                    ],
                ]);
                break;

            case 'mailgun':
                config([
                    'mail.default' => 'mailgun',
                    'mail.mailers.mailgun' => [
                        'transport' => 'mailgun',
                    ],
                    'services.mailgun' => [
                        'domain' => $settings['domain'] ?? '',
                        'secret' => $settings['api_key'] ?? '',
                        'endpoint' => $settings['api_url'] ?? 'api.mailgun.net',
                    ],
                ]);
                break;

            case 'ses':
                config([
                    'mail.default' => 'ses',
                    'mail.mailers.ses' => [
                        'transport' => 'ses',
                    ],
                    'services.ses' => [
                        'key' => $settings['key'] ?? '',
                        'secret' => $settings['secret'] ?? '',
                        'region' => $settings['region'] ?? 'us-east-1',
                    ],
                ]);
                break;

            case 'postmark':
                config([
                    'mail.default' => 'postmark',
                    'mail.mailers.postmark' => [
                        'transport' => 'postmark',
                    ],
                    'services.postmark' => [
                        'token' => $settings['token'] ?? '',
                    ],
                ]);
                break;

            case 'resend':
                config([
                    'mail.default' => 'resend',
                    'mail.mailers.resend' => [
                        'transport' => 'resend',
                    ],
                    'services.resend' => [
                        'key' => $settings['api_key'] ?? '',
                    ],
                ]);
                break;

            case 'mailjet':
                config([
                    'mail.default' => 'mailjet',
                    'mail.mailers.mailjet' => [
                        'transport' => 'mailjet',
                    ],
                    'services.mailjet' => [
                        'key' => $settings['api_key'] ?? '',
                        'secret' => $settings['secret_key'] ?? '',
                    ],
                ]);
                break;
        }

        // Set from address and name
        if (isset($settings['from_address'])) {
            config(['mail.from.address' => $settings['from_address']]);
        }
        if (isset($settings['from_name'])) {
            config(['mail.from.name' => $settings['from_name']]);
        }
    }

    /**
     * Get email configuration for use in mailable.
     * Returns the configuration and sets up the mailer.
     */
    public function useConfiguration(string $identifier): ?EmailConfiguration
    {
        $configuration = $this->getByIdentifier($identifier);

        if ($configuration) {
            $this->configureMailer($configuration);
        }

        return $configuration;
    }

    /**
     * Get settings for display (with real values).
     */
    public function getForDisplay(int $id): array
    {
        $configuration = EmailConfiguration::findOrFail($id);

        try {
            $settings = $configuration->settings;
        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            Log::warning('Email config settings could not be decrypted (APP_KEY may have changed)', [
                'config_id' => $id,
                'error' => $e->getMessage(),
            ]);
            $settings = [];
        }

        return [
            'id' => $configuration->id,
            'name' => $configuration->name,
            'identifier' => $configuration->identifier,
            'provider' => $configuration->provider,
            'is_active' => $configuration->is_active,
            'settings' => $settings,
            'created_at' => $configuration->created_at,
            'updated_at' => $configuration->updated_at,
        ];
    }

    /**
     * Send HTML email using a specific configuration identifier.
     * Uses direct API calls for providers like Mailjet to avoid Laravel Mail caching issues.
     */
    public function sendHtmlEmail(string $identifier, string $toEmail, string $toName, string $subject, string $htmlContent, ?string $textContent = null): array
    {
        try {
            $configuration = $this->getByIdentifier($identifier);

            if (! $configuration) {
                return [
                    'success' => false,
                    'message' => "Configuração de e-mail '{$identifier}' não encontrada.",
                ];
            }

            if (! $configuration->is_active) {
                return [
                    'success' => false,
                    'message' => 'Configuração de e-mail está inativa.',
                ];
            }

            $settings = $configuration->settings;
            $fromAddress = $settings['from_address'] ?? config('mail.from.address');
            $fromName = $settings['from_name'] ?? config('mail.from.name');

            // Send based on provider
            switch ($configuration->provider) {
                case 'mailjet':
                    return $this->sendHtmlViaMailjet($settings, $toEmail, $toName, $fromAddress, $fromName, $subject, $htmlContent, $textContent);

                case 'smtp':
                    return $this->sendHtmlViaSmtp($settings, $toEmail, $toName, $fromAddress, $fromName, $subject, $htmlContent, $textContent);

                default:
                    // For other providers, use Laravel's Mail facade
                    $this->configureMailer($configuration);
                    Mail::html($htmlContent, function ($message) use ($toEmail, $toName, $fromAddress, $fromName, $subject) {
                        $message->to($toEmail, $toName)
                            ->subject($subject)
                            ->from($fromAddress, $fromName);
                    });

                    return [
                        'success' => true,
                        'message' => 'E-mail enviado com sucesso!',
                    ];
            }
        } catch (\Exception $e) {
            $errorId = 'err_'.uniqid();
            $this->logMail('error', 'Erro ao enviar e-mail HTML', [
                'error_id' => $errorId,
                'identifier' => $identifier,
                'to' => $toEmail,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return [
                'success' => false,
                'message' => 'Erro ao enviar e-mail.',
                'error_id' => $errorId,
            ];
        }
    }

    /**
     * Send HTML email directly via Mailjet API.
     */
    private function sendHtmlViaMailjet(array $settings, string $to, string $toName, string $fromAddress, string $fromName, string $subject, string $htmlContent, ?string $textContent = null): array
    {
        $apiKey = $settings['api_key'] ?? '';
        $secretKey = $settings['secret_key'] ?? '';

        if (empty($apiKey) || empty($secretKey)) {
            return [
                'success' => false,
                'message' => 'API Key e Secret Key do Mailjet são obrigatórios.',
            ];
        }

        $this->logMail('info', 'Enviando email HTML via Mailjet', [
            'to' => $to,
            'from' => $fromAddress,
            'subject' => $subject,
            'provider' => 'mailjet',
        ]);

        $client = new Client($apiKey, $secretKey, true, ['version' => 'v3.1']);

        $messageBody = [
            'From' => [
                'Email' => $fromAddress,
                'Name' => $fromName,
            ],
            'To' => [
                [
                    'Email' => $to,
                    'Name' => $toName,
                ],
            ],
            'Subject' => $subject,
            'HTMLPart' => $htmlContent,
        ];

        // Add text part if provided
        if ($textContent) {
            $messageBody['TextPart'] = $textContent;
        }

        $response = $client->post(Resources::$Email, [
            'body' => [
                'Messages' => [$messageBody],
            ],
        ]);

        if ($response->success()) {
            $this->logMail('info', 'Email HTML enviado com sucesso via Mailjet', [
                'to' => $to,
                'from' => $fromAddress,
                'subject' => $subject,
                'provider' => 'mailjet',
            ]);

            return [
                'success' => true,
                'message' => 'E-mail enviado com sucesso via Mailjet!',
            ];
        }

        $errorData = $response->getData();
        $errorMessage = 'Erro ao enviar via Mailjet.';

        if (isset($errorData['Messages'][0]['Errors'][0]['ErrorMessage'])) {
            $errorMessage = $errorData['Messages'][0]['Errors'][0]['ErrorMessage'];
        } elseif (isset($errorData['ErrorMessage'])) {
            $errorMessage = $errorData['ErrorMessage'];
        }

        $this->logMail('error', 'Falha ao enviar email HTML via Mailjet', [
            'to' => $to,
            'from' => $fromAddress,
            'status' => $response->getStatus(),
            'error' => $errorMessage,
            'error_data' => $errorData,
            'provider' => 'mailjet',
        ]);

        return [
            'success' => false,
            'message' => $errorMessage,
        ];
    }

    /**
     * Send HTML email directly via SMTP.
     */
    private function sendHtmlViaSmtp(array $settings, string $to, string $toName, string $fromAddress, string $fromName, string $subject, string $htmlContent, ?string $textContent = null): array
    {
        $this->logMail('info', 'Enviando email HTML via SMTP', [
            'to' => $to,
            'from' => $fromAddress,
            'subject' => $subject,
            'host' => $settings['host'] ?? 'localhost',
            'provider' => 'smtp',
        ]);

        $transport = new \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport(
            $settings['host'] ?? 'localhost',
            (int) ($settings['port'] ?? 587),
            ($settings['encryption'] ?? 'tls') === 'ssl'
        );

        if (! empty($settings['username'])) {
            $transport->setUsername($settings['username']);
        }
        if (! empty($settings['password'])) {
            $transport->setPassword($settings['password']);
        }

        $mailer = new \Symfony\Component\Mailer\Mailer($transport);

        $email = (new \Symfony\Component\Mime\Email)
            ->from(new \Symfony\Component\Mime\Address($fromAddress, $fromName))
            ->to(new \Symfony\Component\Mime\Address($to, $toName))
            ->subject($subject)
            ->html($htmlContent);

        if ($textContent) {
            $email->text($textContent);
        }

        $mailer->send($email);

        $this->logMail('info', 'Email HTML enviado com sucesso via SMTP', [
            'to' => $to,
            'from' => $fromAddress,
            'subject' => $subject,
            'provider' => 'smtp',
        ]);

        return [
            'success' => true,
            'message' => 'E-mail enviado com sucesso via SMTP!',
        ];
    }
}
