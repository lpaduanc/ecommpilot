<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Alters embedding columns to support Gemini's 768 dimensions.
     * pgvector can store vectors of different sizes, but we need to update
     * existing columns if they were created with a fixed size.
     */
    public function up(): void
    {
        if (config('database.default') !== 'pgsql') {
            return;
        }

        $this->alterEmbeddingColumn('knowledge_embeddings');
        $this->alterEmbeddingColumn('suggestions');
    }

    /**
     * Alter embedding column to support Gemini dimensions (768).
     */
    private function alterEmbeddingColumn(string $table): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        try {
            // Check if pgvector extension exists
            $hasVector = DB::select("SELECT 1 FROM pg_extension WHERE extname = 'vector'");

            if (empty($hasVector)) {
                DB::unprepared('CREATE EXTENSION IF NOT EXISTS vector');
            }

            // Check if column exists
            $hasColumn = DB::select("
                SELECT 1 FROM information_schema.columns
                WHERE table_name = ? AND column_name = 'embedding'
            ", [$table]);

            if (empty($hasColumn)) {
                // Column doesn't exist, create it with Gemini dimensions (768)
                DB::unprepared("ALTER TABLE {$table} ADD COLUMN embedding vector(768)");
                Log::info("Created embedding column on {$table} with 768 dimensions (Gemini)");
            } else {
                // Column exists, we need to alter it
                // First, clear existing embeddings (they were generated with different dimensions)
                DB::table($table)->update(['embedding' => null]);

                // Drop the column and recreate with new dimensions
                DB::unprepared("ALTER TABLE {$table} DROP COLUMN embedding");
                DB::unprepared("ALTER TABLE {$table} ADD COLUMN embedding vector(768)");
                Log::info("Recreated embedding column on {$table} with 768 dimensions (Gemini)");
            }

            // Recreate index
            $indexName = "{$table}_embedding_idx";
            DB::unprepared("DROP INDEX IF EXISTS {$indexName}");
            DB::unprepared("CREATE INDEX IF NOT EXISTS {$indexName} ON {$table} USING hnsw (embedding vector_cosine_ops)");

        } catch (\Throwable $e) {
            Log::warning("Could not alter embedding column on {$table}: ".$e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (config('database.default') !== 'pgsql') {
            return;
        }

        // Revert to OpenAI dimensions (1536) if needed
        $this->revertEmbeddingColumn('knowledge_embeddings', 1536);
        $this->revertEmbeddingColumn('suggestions', 1536);
    }

    /**
     * Revert embedding column to original dimensions.
     */
    private function revertEmbeddingColumn(string $table, int $dimensions): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        try {
            $hasColumn = DB::select("
                SELECT 1 FROM information_schema.columns
                WHERE table_name = ? AND column_name = 'embedding'
            ", [$table]);

            if (! empty($hasColumn)) {
                DB::table($table)->update(['embedding' => null]);
                DB::unprepared("ALTER TABLE {$table} DROP COLUMN embedding");
                DB::unprepared("ALTER TABLE {$table} ADD COLUMN embedding vector({$dimensions})");

                $indexName = "{$table}_embedding_idx";
                DB::unprepared("DROP INDEX IF EXISTS {$indexName}");
                DB::unprepared("CREATE INDEX IF NOT EXISTS {$indexName} ON {$table} USING hnsw (embedding vector_cosine_ops)");
            }
        } catch (\Throwable $e) {
            Log::warning("Could not revert embedding column on {$table}: ".$e->getMessage());
        }
    }
};
