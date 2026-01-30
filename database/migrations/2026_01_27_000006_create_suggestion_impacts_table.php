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
        Schema::create('suggestion_impacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('suggestion_id')->constrained('suggestions')->onDelete('cascade');
            $table->string('type', 50); // coupon, campaign, method, metric, other
            $table->string('label', 255); // nome do campo (ex: "Cupom utilizado", "Campanha")
            $table->text('value')->nullable(); // valor do campo
            $table->decimal('numeric_value', 15, 2)->nullable(); // valor numérico (para métricas)
            $table->json('metadata')->nullable(); // dados extras
            $table->timestamps();

            $table->index(['suggestion_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suggestion_impacts');
    }
};
