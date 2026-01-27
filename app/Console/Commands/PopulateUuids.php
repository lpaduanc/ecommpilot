<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PopulateUuids extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'uuid:populate
                          {--table= : Specific table to populate}
                          {--force : Force operation without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Populate UUID fields for existing records in all tables';

    /**
     * Tables that need UUID population.
     *
     * @var array
     */
    private array $tables = [
        'stores',
        'analyses',
        'suggestions',
        'users',
        'synced_products',
        'synced_orders',
        'synced_customers',
        'synced_coupons',
        'chat_conversations',
        'chat_messages',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $specificTable = $this->option('table');
        $tables = $specificTable ? [$specificTable] : $this->tables;

        // Validar se a tabela existe
        if ($specificTable && !in_array($specificTable, $this->tables)) {
            $this->error("Table '{$specificTable}' is not in the list of tables to populate.");
            return self::FAILURE;
        }

        // Confirmação
        if (!$this->option('force')) {
            if (!$this->confirm('This will populate UUIDs for all records without them. Continue?')) {
                $this->info('Operation cancelled.');
                return self::SUCCESS;
            }
        }

        $this->info('Starting UUID population...');
        $this->newLine();

        $totalRecords = 0;
        $totalUpdated = 0;

        foreach ($tables as $table) {
            if (!Schema::hasTable($table)) {
                $this->warn("Table '{$table}' does not exist. Skipping...");
                continue;
            }

            if (!Schema::hasColumn($table, 'uuid')) {
                $this->warn("Table '{$table}' does not have a 'uuid' column. Skipping...");
                continue;
            }

            $this->info("Processing table: {$table}");

            // Contar registros sem UUID
            $count = DB::table($table)
                ->whereNull('uuid')
                ->count();

            if ($count === 0) {
                $this->line("  No records to update.");
                continue;
            }

            $this->line("  Found {$count} records without UUID.");
            $bar = $this->output->createProgressBar($count);
            $bar->start();

            // Processar em chunks de 100
            $updated = 0;
            DB::table($table)
                ->whereNull('uuid')
                ->orderBy('id')
                ->chunk(100, function ($records) use ($table, &$updated, $bar) {
                    foreach ($records as $record) {
                        DB::table($table)
                            ->where('id', $record->id)
                            ->update(['uuid' => Str::uuid()->toString()]);

                        $updated++;
                        $bar->advance();
                    }
                });

            $bar->finish();
            $this->newLine();
            $this->line("  Updated {$updated} records.");
            $this->newLine();

            $totalRecords += $count;
            $totalUpdated += $updated;
        }

        $this->newLine();
        $this->info("UUID population completed!");
        $this->info("Total records processed: {$totalRecords}");
        $this->info("Total records updated: {$totalUpdated}");

        return self::SUCCESS;
    }
}
