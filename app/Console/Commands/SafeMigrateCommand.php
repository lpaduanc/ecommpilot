<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SafeMigrateCommand extends Command
{
    protected $signature = 'migrate:safe {--fresh : Run fresh migration (DANGEROUS)} {--seed : Run seeders after migration} {--force : Force in production}';

    protected $description = 'Safe migration command that creates backup before destructive operations';

    public function handle(): int
    {
        $isFresh = $this->option('fresh');

        // BLOCK migrate:fresh in production without explicit confirmation
        if ($isFresh && app()->environment('production')) {
            $this->error('❌ migrate:fresh is BLOCKED in production!');
            $this->error('This command would DELETE ALL DATA.');
            Log::critical('BLOCKED: Attempted migrate:fresh in production', [
                'user' => get_current_user(),
                'ip' => request()?->ip() ?? 'CLI',
            ]);

            return Command::FAILURE;
        }

        if ($isFresh) {
            // Log the attempt
            Log::warning('migrate:fresh requested', [
                'environment' => app()->environment(),
                'user' => get_current_user(),
                'timestamp' => now()->toIso8601String(),
            ]);

            // Show warning
            $this->warn('⚠️  WARNING: migrate:fresh will DELETE ALL DATA!');
            $this->newLine();

            // Show current data counts
            $this->showDataCounts();

            // Require explicit confirmation
            if (! $this->confirm('Are you ABSOLUTELY SURE you want to delete all data?', false)) {
                $this->info('Operation cancelled.');

                return Command::SUCCESS;
            }

            // Second confirmation
            $confirmation = $this->ask('Type "DELETE ALL DATA" to confirm');
            if ($confirmation !== 'DELETE ALL DATA') {
                $this->info('Operation cancelled - confirmation text did not match.');

                return Command::SUCCESS;
            }

            // Create backup before destructive operation
            $this->info('Creating database backup before migration...');
            $backupFile = $this->createBackup();

            if ($backupFile) {
                $this->info("✓ Backup created: {$backupFile}");
            } else {
                $this->warn('Could not create backup, but proceeding...');
            }

            Log::warning('migrate:fresh CONFIRMED and executing', [
                'environment' => app()->environment(),
                'backup_file' => $backupFile,
            ]);
        }

        // Run the actual migration
        $command = $isFresh ? 'migrate:fresh' : 'migrate';
        $options = ['--force' => $this->option('force')];

        if ($this->option('seed')) {
            $options['--seed'] = true;
        }

        // Set bypass flag to allow internal migrate:fresh call
        app()->instance('migrate_safe_bypass', true);

        try {
            $this->call($command, $options);
        } finally {
            // Remove bypass flag
            app()->instance('migrate_safe_bypass', false);
        }

        return Command::SUCCESS;
    }

    private function showDataCounts(): void
    {
        $tables = [
            'users' => 'Users',
            'stores' => 'Stores',
            'synced_products' => 'Products',
            'synced_orders' => 'Orders',
            'analyses' => 'Analyses',
            'suggestions' => 'Suggestions',
        ];

        $this->info('Current data in database:');
        foreach ($tables as $table => $label) {
            try {
                $count = DB::table($table)->count();
                $this->line("  - {$label}: {$count} records");
            } catch (\Exception $e) {
                $this->line("  - {$label}: (table not found)");
            }
        }
        $this->newLine();
    }

    private function createBackup(): ?string
    {
        try {
            $filename = 'backup_'.date('Y-m-d_H-i-s').'.sql';
            $path = storage_path('backups/'.$filename);

            // Create backups directory if not exists
            if (! is_dir(storage_path('backups'))) {
                mkdir(storage_path('backups'), 0755, true);
            }

            // Get database config
            $host = config('database.connections.pgsql.host');
            $port = config('database.connections.pgsql.port');
            $database = config('database.connections.pgsql.database');
            $username = config('database.connections.pgsql.username');
            $password = config('database.connections.pgsql.password');

            // Create backup using pg_dump
            $command = sprintf(
                'PGPASSWORD=%s pg_dump -h %s -p %s -U %s -d %s -F c -f %s 2>&1',
                escapeshellarg($password),
                escapeshellarg($host),
                escapeshellarg($port),
                escapeshellarg($username),
                escapeshellarg($database),
                escapeshellarg($path)
            );

            exec($command, $output, $returnCode);

            if ($returnCode === 0 && file_exists($path)) {
                return $path;
            }
        } catch (\Exception $e) {
            Log::error('Backup failed', ['error' => $e->getMessage()]);
        }

        return null;
    }
}
