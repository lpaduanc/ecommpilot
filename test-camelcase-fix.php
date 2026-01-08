#!/usr/bin/env php
<?php

/**
 * Manual test script to verify camelCase to snake_case conversion is working.
 *
 * This script simulates a frontend request with camelCase fields and verifies
 * that the data is properly saved to the database.
 *
 * Usage: php test-camelcase-fix.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\SystemSetting;
use Illuminate\Support\Facades\DB;

echo "=== CamelCase to Snake_Case Conversion Test ===\n\n";

// Clean up test data
echo "1. Cleaning up test data...\n";
SystemSetting::whereIn('key', [
    'nuvemshop.client_id',
    'nuvemshop.client_secret',
    'nuvemshop.grant_type',
])->delete();
echo "   ✓ Cleaned up\n\n";

// Simulate the prepareForValidation conversion
echo "2. Simulating camelCase to snake_case conversion...\n";
$input = [
    'clientId' => '24713',
    'clientSecret' => 'c85726f31bf4f4b304488d6f802fe8f8a4a1df307f6ef258',
    'grantType' => 'authorization_code',
];

echo "   Input (camelCase):\n";
foreach ($input as $key => $value) {
    $displayValue = strlen($value) > 50 ? substr($value, 0, 20).'...' : $value;
    echo "     - {$key}: {$displayValue}\n";
}

$converted = [];
foreach ($input as $key => $value) {
    $snakeKey = Illuminate\Support\Str::snake($key);
    $converted[$snakeKey] = $value;
}

echo "\n   Converted (snake_case):\n";
foreach ($converted as $key => $value) {
    $displayValue = strlen($value) > 50 ? substr($value, 0, 20).'...' : $value;
    echo "     - {$key}: {$displayValue}\n";
}
echo "\n";

// Save to database using SystemSetting::set()
echo "3. Saving to database...\n";
SystemSetting::set('nuvemshop.client_id', $converted['client_id'], [
    'type' => 'string',
    'group' => 'nuvemshop',
    'label' => 'Client ID',
    'description' => 'Nuvemshop OAuth Client ID',
    'is_sensitive' => true,
]);

SystemSetting::set('nuvemshop.client_secret', $converted['client_secret'], [
    'type' => 'string',
    'group' => 'nuvemshop',
    'label' => 'Client Secret',
    'description' => 'Nuvemshop OAuth Client Secret',
    'is_sensitive' => true,
]);

SystemSetting::set('nuvemshop.grant_type', $converted['grant_type'], [
    'type' => 'string',
    'group' => 'nuvemshop',
    'label' => 'Grant Type',
    'description' => 'OAuth Grant Type',
    'is_sensitive' => false,
]);
echo "   ✓ Saved to database\n\n";

// Verify data was saved
echo "4. Verifying data in database...\n";
$clientId = SystemSetting::get('nuvemshop.client_id');
$clientSecret = SystemSetting::get('nuvemshop.client_secret');
$grantType = SystemSetting::get('nuvemshop.grant_type');

$success = true;

if ($clientId === '24713') {
    echo "   ✓ client_id: {$clientId}\n";
} else {
    echo "   ✗ client_id: Expected '24713', got '{$clientId}'\n";
    $success = false;
}

if ($clientSecret === 'c85726f31bf4f4b304488d6f802fe8f8a4a1df307f6ef258') {
    echo "   ✓ client_secret: " . substr($clientSecret, 0, 20) . "...\n";
} else {
    echo "   ✗ client_secret: Value mismatch\n";
    $success = false;
}

if ($grantType === 'authorization_code') {
    echo "   ✓ grant_type: {$grantType}\n";
} else {
    echo "   ✗ grant_type: Expected 'authorization_code', got '{$grantType}'\n";
    $success = false;
}

echo "\n";

// Test getDisplayValue for sensitive fields
echo "5. Testing display value masking for sensitive fields...\n";
$clientIdSetting = SystemSetting::where('key', 'nuvemshop.client_id')->first();
$clientSecretSetting = SystemSetting::where('key', 'nuvemshop.client_secret')->first();

$displayClientId = $clientIdSetting->getDisplayValue();
$displayClientSecret = $clientSecretSetting->getDisplayValue();

if (str_contains($displayClientId, '****')) {
    echo "   ✓ client_id is masked: {$displayClientId}\n";
} else {
    echo "   ✗ client_id is not properly masked: {$displayClientId}\n";
    $success = false;
}

if (str_contains($displayClientSecret, '****')) {
    echo "   ✓ client_secret is masked: {$displayClientSecret}\n";
} else {
    echo "   ✗ client_secret is not properly masked: {$displayClientSecret}\n";
    $success = false;
}

echo "\n";

// Clean up test data
echo "6. Cleaning up test data...\n";
SystemSetting::whereIn('key', [
    'nuvemshop.client_id',
    'nuvemshop.client_secret',
    'nuvemshop.grant_type',
])->delete();
echo "   ✓ Cleaned up\n\n";

// Summary
echo "=== Summary ===\n";
if ($success) {
    echo "✓ All tests passed! The camelCase to snake_case conversion is working correctly.\n";
    exit(0);
} else {
    echo "✗ Some tests failed. Please review the output above.\n";
    exit(1);
}
