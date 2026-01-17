<?php

namespace App\Services;

use App\Models\EmailConfiguration;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class EmailConfigurationService
{
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

            // Configure mailer based on provider
            $this->configureMailer($configuration);

            // Send test email
            Mail::raw('Este é um e-mail de teste da configuração: '.$configuration->name, function ($message) use ($toEmail, $configuration) {
                $message->to($toEmail)
                    ->subject('Teste de Configuração de E-mail - '.$configuration->name)
                    ->from(
                        $configuration->settings['from_address'] ?? config('mail.from.address'),
                        $configuration->settings['from_name'] ?? config('mail.from.name')
                    );
            });

            return [
                'success' => true,
                'message' => 'E-mail de teste enviado com sucesso!',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao enviar e-mail: '.$e->getMessage(),
            ];
        }
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
     * Get settings for display (mask sensitive data).
     */
    public function getForDisplay(int $id): array
    {
        $configuration = EmailConfiguration::findOrFail($id);
        $settings = $configuration->settings;

        // Mask sensitive fields
        $sensitiveFields = ['password', 'api_key', 'secret', 'token', 'key'];
        foreach ($sensitiveFields as $field) {
            if (isset($settings[$field]) && ! empty($settings[$field])) {
                $settings[$field] = $this->maskValue($settings[$field]);
            }
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
     * Mask sensitive values for display.
     */
    private function maskValue(?string $value): string
    {
        if (empty($value)) {
            return '';
        }

        if (strlen($value) <= 8) {
            return '********';
        }

        return substr($value, 0, 4).str_repeat('*', strlen($value) - 8).substr($value, -4);
    }
}
