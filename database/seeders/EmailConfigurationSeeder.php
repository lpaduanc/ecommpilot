<?php

namespace Database\Seeders;

use App\Models\EmailConfiguration;
use Illuminate\Database\Seeder;

class EmailConfigurationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Creates default email configurations that can be customized via admin panel.
     */
    public function run(): void
    {
        // Password Reset Email Configuration
        // This is used by ResetPasswordNotification
        EmailConfiguration::updateOrCreate(
            ['identifier' => 'password-reset'],
            [
                'name' => 'Reset de Senha',
                'provider' => 'mailjet',
                'is_active' => false, // Inactive until admin configures credentials
                'settings' => [
                    'api_key' => '',
                    'secret_key' => '',
                    'from_address' => 'no-reply-reset-password@ecommpilot.com.br',
                    'from_name' => 'EcommPilot',
                ],
            ]
        );

        // General Notifications Email Configuration (optional)
        EmailConfiguration::updateOrCreate(
            ['identifier' => 'notifications'],
            [
                'name' => 'Notificações Gerais',
                'provider' => 'mailjet',
                'is_active' => false,
                'settings' => [
                    'api_key' => '',
                    'secret_key' => '',
                    'from_address' => 'no-reply@ecommpilot.com.br',
                    'from_name' => 'EcommPilot',
                ],
            ]
        );

        // Analysis Completed Email Configuration (optional)
        EmailConfiguration::updateOrCreate(
            ['identifier' => 'analysis'],
            [
                'name' => 'Análises Concluídas',
                'provider' => 'mailjet',
                'is_active' => false,
                'settings' => [
                    'api_key' => '',
                    'secret_key' => '',
                    'from_address' => 'analises@ecommpilot.com.br',
                    'from_name' => 'EcommPilot - Análises',
                ],
            ]
        );
    }
}
