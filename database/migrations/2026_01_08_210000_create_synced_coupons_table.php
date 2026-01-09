<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('synced_coupons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->string('external_id');
            $table->string('code');
            $table->string('type')->default('percentage'); // percentage, absolute, shipping
            $table->decimal('value', 10, 2)->default(0);
            $table->boolean('valid')->default(true);
            $table->integer('used')->default(0);
            $table->integer('max_uses')->nullable();
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();
            $table->decimal('min_price', 10, 2)->nullable();
            $table->json('categories')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['store_id', 'external_id']);
            $table->index(['store_id', 'code']);
            $table->index(['store_id', 'valid']);
            $table->index('end_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('synced_coupons');
    }
};
