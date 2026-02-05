<?php

use App\Models\SystemSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $settings = [
            // Decodo timeout (increased from 30 to 60 seconds)
            [
                'key' => 'external_data.decodo.timeout',
                'value' => '60',
                'type' => 'integer',
                'description' => 'Timeout em segundos para requests ao Decodo API',
            ],
            // Max retry attempts
            [
                'key' => 'external_data.decodo.max_retries',
                'value' => '3',
                'type' => 'integer',
                'description' => 'Número máximo de tentativas em caso de falha',
            ],
            // Minimum content length to consider valid
            [
                'key' => 'external_data.decodo.min_content_length',
                'value' => '100',
                'type' => 'integer',
                'description' => 'Tamanho mínimo do conteúdo retornado para considerar válido (em bytes)',
            ],
            // Delay between competitor requests
            [
                'key' => 'external_data.competitors.request_delay',
                'value' => '3',
                'type' => 'integer',
                'description' => 'Delay em segundos entre requests de concorrentes para evitar rate limiting',
            ],
        ];

        foreach ($settings as $setting) {
            SystemSetting::firstOrCreate(
                ['key' => $setting['key']],
                [
                    'value' => $setting['value'],
                    'type' => $setting['type'],
                    'description' => $setting['description'],
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $keys = [
            'external_data.decodo.timeout',
            'external_data.decodo.max_retries',
            'external_data.decodo.min_content_length',
            'external_data.competitors.request_delay',
        ];

        SystemSetting::whereIn('key', $keys)->delete();
    }
};
