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
        Schema::create('suggestion_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('suggestion_id')->constrained('suggestions')->onDelete('cascade');
            $table->smallInteger('step_index')->nullable(); // índice do passo da IA (0, 1, 2...) ou NULL se tarefa geral
            $table->string('title', 500);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('pending'); // pending, in_progress, completed
            $table->date('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index(['suggestion_id', 'status']);
            $table->index('step_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suggestion_tasks');
    }
};
