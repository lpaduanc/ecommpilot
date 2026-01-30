<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables that need UUID.
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
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->tables as $table) {
            $this->processTable($table);
        }
    }

    /**
     * Process a single table: populate UUIDs and make NOT NULL.
     */
    private function processTable(string $table): void
    {
        // Skip if table doesn't exist
        if (! Schema::hasTable($table)) {
            return;
        }

        // Skip if uuid column doesn't exist
        if (! Schema::hasColumn($table, 'uuid')) {
            return;
        }

        // Step 1: Populate UUIDs using PostgreSQL's native gen_random_uuid()
        // This is much more reliable than PHP-generated UUIDs
        // Note: PostgreSQL UUID type cannot have empty string, only NULL
        $nullCount = DB::table($table)->whereNull('uuid')->count();

        if ($nullCount > 0) {
            // Use PostgreSQL native UUID generation
            DB::statement("UPDATE {$table} SET uuid = gen_random_uuid() WHERE uuid IS NULL");
        }

        // Step 2: Verify no NULL values remain
        $remainingNulls = DB::table($table)->whereNull('uuid')->count();
        if ($remainingNulls > 0) {
            throw new \RuntimeException(
                "Failed to populate UUIDs for table {$table}. {$remainingNulls} records still have NULL uuid."
            );
        }

        // Step 3: Add UNIQUE index if it doesn't exist
        $indexName = $table.'_uuid_unique';
        $indexExists = DB::select('
            SELECT 1 FROM pg_indexes
            WHERE tablename = ? AND indexname = ?
        ', [$table, $indexName]);

        if (empty($indexExists)) {
            Schema::table($table, function (Blueprint $blueprint) use ($indexName) {
                $blueprint->unique('uuid', $indexName);
            });
        }

        // Step 4: Make NOT NULL
        // First check if it's already NOT NULL
        $columnInfo = DB::select("
            SELECT is_nullable
            FROM information_schema.columns
            WHERE table_name = ? AND column_name = 'uuid'
        ", [$table]);

        if (! empty($columnInfo) && $columnInfo[0]->is_nullable === 'YES') {
            DB::statement("ALTER TABLE {$table} ALTER COLUMN uuid SET NOT NULL");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'uuid')) {
                continue;
            }

            // Drop unique index if exists
            $indexName = $table.'_uuid_unique';
            $indexExists = DB::select('
                SELECT 1 FROM pg_indexes
                WHERE tablename = ? AND indexname = ?
            ', [$table, $indexName]);

            if (! empty($indexExists)) {
                Schema::table($table, function (Blueprint $blueprint) use ($indexName) {
                    $blueprint->dropUnique($indexName);
                });
            }

            // Make nullable again
            DB::statement("ALTER TABLE {$table} ALTER COLUMN uuid DROP NOT NULL");
        }
    }
};
