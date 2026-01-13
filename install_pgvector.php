<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    \Illuminate\Support\Facades\DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
    echo "✓ pgvector extension created successfully!\n";
} catch (\Throwable $e) {
    echo '✗ Error: '.$e->getMessage()."\n";
    echo "\n";
    echo "The pgvector extension is not installed on your PostgreSQL.\n";
    echo "\n";
    echo "Troubleshooting steps:\n";
    echo "1. Verify installation:\n";
    echo "   .\\diagnose-pgvector.ps1\n";
    echo "\n";
    echo "2. If not installed, run the installation script:\n";
    echo "   .\\install-pgvector-windows.ps1\n";
    echo "   (Run as Administrator in Developer PowerShell for VS 2022)\n";
    echo "\n";
    echo "3. After installation, RESTART PostgreSQL service:\n";
    echo "   Restart-Service postgresql-x64-18\n";
    echo "   OR\n";
    echo "   net stop postgresql-x64-18 && net start postgresql-x64-18\n";
    echo "\n";
    echo "4. Then run this script again:\n";
    echo "   php install_pgvector.php\n";
    echo "\n";
    echo "For detailed instructions, see: docs/INSTALL_PGVECTOR_WINDOWS.md\n";
    exit(1);
}
