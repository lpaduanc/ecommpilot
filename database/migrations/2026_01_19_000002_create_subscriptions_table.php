<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('plan_id')->constrained()->onDelete('restrict');

            $table->string('status')->default('active'); // active, cancelled, expired, trial

            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            // Campos para gateway de pagamento (futuro)
            $table->string('payment_gateway')->nullable();
            $table->string('external_subscription_id')->nullable();
            $table->json('payment_metadata')->nullable();

            $table->timestamps();

            // Index para busca rápida
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
