<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'description' => 'Focado em quem está começando a usar dados para tomar decisões.',
                'price' => 149.00,
                'is_active' => true,
                'sort_order' => 1,
                'orders_limit' => 750,
                'stores_limit' => 1,
                'analysis_per_day' => 2, // 1 automática + 1 extra
                'analysis_history_limit' => 4,
                'data_retention_months' => 12,
                'has_ai_analysis' => true,
                'has_ai_chat' => false,
                'has_suggestion_discussion' => false,
                'has_suggestion_history' => false,
                'has_custom_dashboards' => false,
                'has_external_integrations' => false,
                'has_impact_dashboard' => false,
                'external_integrations_limit' => 0,
                'features' => [
                    'daily_email_report' => true,
                    'sync_interval_hours' => 24,
                ],
            ],
            [
                'name' => 'Business',
                'slug' => 'business',
                'description' => 'Focado em gestores que precisam de insights profundos várias vezes ao dia.',
                'price' => 359.00,
                'is_active' => true,
                'sort_order' => 2,
                'orders_limit' => 5000,
                'stores_limit' => 5,
                'analysis_per_day' => 3, // 1 automática + 2 extras
                'analysis_history_limit' => -1, // Ilimitado
                'data_retention_months' => 48,
                'has_ai_analysis' => true,
                'has_ai_chat' => true,
                'has_suggestion_discussion' => true,
                'has_suggestion_history' => false,
                'has_custom_dashboards' => false,
                'has_external_integrations' => true,
                'has_impact_dashboard' => false,
                'external_integrations_limit' => 3,
                'features' => [
                    'daily_email_report' => true,
                    'sync_interval_hours' => 24,
                ],
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'description' => 'Para quem exige máxima performance e personalização.',
                'price' => 599.00,
                'is_active' => true,
                'sort_order' => 3,
                'orders_limit' => -1, // Ilimitado
                'stores_limit' => -1, // Ilimitado
                'analysis_per_day' => 4, // 1 automática + 3 extras
                'analysis_history_limit' => -1, // Ilimitado
                'data_retention_months' => -1, // Ilimitado
                'has_ai_analysis' => true,
                'has_ai_chat' => true,
                'has_suggestion_discussion' => true,
                'has_suggestion_history' => true,
                'has_custom_dashboards' => true,
                'has_external_integrations' => true,
                'has_impact_dashboard' => true,
                'external_integrations_limit' => -1, // Ilimitado
                'features' => [
                    'daily_email_report' => true,
                    'sync_interval_hours' => 24,
                    'priority_support' => true,
                    'whatsapp_support' => true,
                ],
            ],
        ];

        foreach ($plans as $plan) {
            Plan::firstOrCreate(
                ['slug' => $plan['slug']],
                $plan
            );
        }

        $this->command->info('Planos criados com sucesso!');
    }
}
