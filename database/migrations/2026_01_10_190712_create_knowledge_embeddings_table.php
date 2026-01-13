<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('knowledge_embeddings', function (Blueprint $table) {
            $table->id();
            $table->string('category', 100); // 'benchmark', 'strategy', 'case', 'seasonality'
            $table->string('niche', 100)->nullable(); // 'fashion', 'electronics', 'general'
            $table->string('title', 255);
            $table->text('content');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('category');
            $table->index('niche');
        });

        // Add vector column for pgvector (embedding) - only if extension is available
        if (config('database.default') === 'pgsql') {
            $this->addVectorColumn();
        }
    }

    /**
     * Add vector column in a separate operation to avoid transaction issues.
     */
    private function addVectorColumn(): void
    {
        try {
            // Check if pgvector extension exists
            $hasVector = DB::select("SELECT 1 FROM pg_extension WHERE extname = 'vector'");

            if (empty($hasVector)) {
                // Try to create extension
                DB::unprepared('CREATE EXTENSION IF NOT EXISTS vector');
            }

            // Add vector column
            DB::unprepared('ALTER TABLE knowledge_embeddings ADD COLUMN IF NOT EXISTS embedding vector(1536)');

            // Create index (using HNSW which doesn't require training like IVFFlat)
            DB::unprepared('CREATE INDEX IF NOT EXISTS knowledge_embedding_idx ON knowledge_embeddings USING hnsw (embedding vector_cosine_ops)');
        } catch (\Throwable $e) {
            // pgvector not installed - continue without embedding column
            // The system will work with text-based search instead
            \Illuminate\Support\Facades\Log::warning('pgvector extension not available: '.$e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('knowledge_embeddings');
    }
};
