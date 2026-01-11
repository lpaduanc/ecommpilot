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
        Schema::create('suggestion_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('suggestion_id')->constrained('suggestions')->onDelete('cascade');
            $table->foreignId('store_id')->constrained('stores')->onDelete('cascade');
            $table->json('metrics_before');
            $table->json('metrics_after')->nullable();
            $table->decimal('revenue_variation', 10, 2)->nullable();
            $table->decimal('avg_ticket_variation', 10, 2)->nullable();
            $table->decimal('conversion_variation', 10, 2)->nullable();
            $table->integer('days_to_result')->nullable();
            $table->boolean('success')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('suggestion_id');
            $table->index('store_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suggestion_results');
    }
};
