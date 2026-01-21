<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('analysis_execution_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analysis_id')->constrained()->onDelete('cascade');
            $table->unsignedTinyInteger('stage')->comment('1-9 pipeline stage number');
            $table->string('stage_name')->comment('niche_detection, historical_context, etc.');
            $table->enum('status', ['running', 'completed', 'failed'])->default('running');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->json('metrics')->nullable()->comment('Stage-specific metrics');
            $table->string('agent_used')->nullable()->comment('collector, analyst, strategist, critic');
            $table->string('ai_provider')->nullable()->comment('gemini, anthropic, openai');
            $table->json('token_usage')->nullable();
            $table->text('error_message')->nullable();
            $table->text('log_output')->nullable()->comment('Relevant log snippet');
            $table->timestamps();

            $table->index(['analysis_id', 'stage']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analysis_execution_logs');
    }
};
