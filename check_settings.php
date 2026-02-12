<?php

require '/var/www/html/vendor/autoload.php';
$app = require_once '/var/www/html/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Crypt;

echo "=== AI System Settings ===\n\n";

$settings = SystemSetting::where('key', 'like', 'ai.%')->get();

if ($settings->isEmpty()) {
    echo "NO AI SETTINGS FOUND IN DATABASE!\n";
} else {
    foreach ($settings as $s) {
        echo "KEY: {$s->key}\n";
        echo '  is_sensitive: '.($s->is_sensitive ? 'true' : 'false')."\n";
        echo "  type: {$s->type}\n";
        echo '  raw_value_length: '.strlen($s->value ?? '')."\n";
        echo '  raw_value_prefix: '.substr($s->value ?? '(null)', 0, 40)."\n";

        if ($s->is_sensitive && $s->value) {
            try {
                $decrypted = Crypt::decryptString($s->value);
                echo '  DECRYPT: OK (length='.strlen($decrypted).', prefix='.substr($decrypted, 0, 8)."...)\n";
            } catch (Exception $e) {
                echo '  DECRYPT: FAILED - '.$e->getMessage()."\n";
            }
        }

        try {
            $casted = $s->getCastedValue();
            echo '  getCastedValue: '.substr((string) $casted, 0, 20)."\n";
        } catch (Exception $e) {
            echo '  getCastedValue: ERROR - '.$e->getMessage()."\n";
        }

        echo "\n";
    }
}

echo "\n=== Testing SettingsService::getAISettings() ===\n\n";
$service = new App\Services\SettingsService;
$aiSettings = $service->getAISettings();
echo 'provider: '.$aiSettings['provider']."\n";
foreach (['openai', 'gemini', 'anthropic'] as $p) {
    $key = $aiSettings[$p]['api_key'] ?? '';
    echo "{$p}.api_key: ".(empty($key) ? '(EMPTY)' : 'len='.strlen($key).', prefix='.substr($key, 0, 8))."\n";
    echo "{$p}.model: ".($aiSettings[$p]['model'] ?? '(not set)')."\n";
}

echo "\n=== Testing SettingsService::getAISettingsForDisplay() ===\n\n";
$displaySettings = $service->getAISettingsForDisplay();
foreach (['openai', 'gemini', 'anthropic'] as $p) {
    $key = $displaySettings[$p]['api_key'] ?? '';
    $configured = $displaySettings[$p]['is_configured'] ?? false;
    echo "{$p}.api_key (display): ".(empty($key) ? '(EMPTY)' : $key)."\n";
    echo "{$p}.is_configured: ".($configured ? 'true' : 'false')."\n";
}
