<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug', 50)->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);

            // Limites de recursos (-1 = ilimitado)
            $table->integer('orders_limit')->default(750);
            $table->integer('stores_limit')->default(1);
            $table->integer('analysis_per_day')->default(1);
            $table->integer('analysis_history_limit')->default(4);
            $table->integer('data_retention_months')->default(12);

            // Features booleanas
            $table->boolean('has_ai_chat')->default(false);
            $table->boolean('has_custom_dashboards')->default(false);
            $table->boolean('has_external_integrations')->default(false);

            // Features extras em JSON (flexível para futuro)
            $table->json('features')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
