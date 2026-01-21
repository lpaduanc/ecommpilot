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
        Schema::create('success_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('niche')->nullable();
            $table->string('subcategory')->nullable();
            $table->string('category');
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('implementation_details')->nullable();
            $table->json('metrics_impact')->nullable();
            $table->timestamps();

            $table->index(['niche', 'subcategory']);
            $table->index('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('success_cases');
    }
};
