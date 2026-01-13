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
        Schema::create('suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analysis_id')->constrained('analyses')->onDelete('cascade');
            $table->foreignId('store_id')->constrained('stores')->onDelete('cascade');
            $table->string('category', 50); // 'inventory', 'coupon', 'product', 'marketing', 'operational'
            $table->string('title', 255);
            $table->text('description');
            $table->text('recommended_action'); // step by step
            $table->string('expected_impact', 20); // 'high', 'medium', 'low'
            $table->integer('priority')->default(0); // 1-10
            $table->string('status', 20)->default('pending'); // 'pending', 'in_progress', 'completed', 'ignored'
            $table->timestamp('completed_at')->nullable();
            $table->json('target_metrics')->nullable(); // metrics that should improve
            $table->json('specific_data')->nullable(); // affected products, suggested values, examples
            $table->text('data_justification')->nullable(); // justification based on data
            $table->timestamps();

            $table->index(['store_id', 'status']);
            $table->index('category');
        });

        // Add vector column for pgvector (embedding) - only if extension is available
        // Uses separate connection to avoid transaction rollback
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
            DB::unprepared('ALTER TABLE suggestions ADD COLUMN IF NOT EXISTS embedding vector(1536)');

            // Create index (using HNSW which doesn't require training like IVFFlat)
            DB::unprepared('CREATE INDEX IF NOT EXISTS suggestions_embedding_idx ON suggestions USING hnsw (embedding vector_cosine_ops)');
        } catch (\Throwable $e) {
            // pgvector not installed - continue without embedding column
            // The system will work without similarity search
            \Illuminate\Support\Facades\Log::warning('pgvector extension not available: '.$e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suggestions');
    }
};
