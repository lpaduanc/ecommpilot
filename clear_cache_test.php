<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Clear all settings cache
App\Models\SystemSetting::clearCache();
echo "Cache cleared!\n\n";

// Now re-test
$service = new App\Services\SettingsService();

echo "=== After cache clear - getAISettings() ===\n";
$aiSettings = $service->getAISettings();
echo "provider: " . $aiSettings['provider'] . "\n";
foreach (['openai', 'gemini', 'anthropic'] as $p) {
    $key = $aiSettings[$p]['api_key'] ?? '';
    echo "{$p}.api_key: " . (empty($key) ? '(EMPTY)' : 'len=' . strlen($key) . ', prefix=' . substr($key, 0, 8)) . "\n";
}

echo "\n=== After cache clear - getAISettingsForDisplay() ===\n";
$displaySettings = $service->getAISettingsForDisplay();
foreach (['openai', 'gemini', 'anthropic'] as $p) {
    $key = $displaySettings[$p]['api_key'] ?? '';
    $configured = $displaySettings[$p]['is_configured'] ?? false;
    echo "{$p}.api_key (display): " . (empty($key) ? '(EMPTY)' : $key) . "\n";
    echo "{$p}.is_configured: " . ($configured ? 'true' : 'false') . "\n";
}
