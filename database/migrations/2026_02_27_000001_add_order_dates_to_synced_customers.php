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
        Schema::table('synced_customers', function (Blueprint $table) {
            $table->timestamp('first_order_at')->nullable()->after('total_spent');
            $table->timestamp('last_order_at')->nullable()->after('first_order_at');

            $table->index(['store_id', 'last_order_at'], 'synced_customers_store_last_order_idx');
            $table->index(['store_id', 'total_orders'], 'synced_customers_store_total_orders_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('synced_customers', function (Blueprint $table) {
            $table->dropIndex('synced_customers_store_last_order_idx');
            $table->dropIndex('synced_customers_store_total_orders_idx');
            $table->dropColumn(['first_order_at', 'last_order_at']);
        });
    }
};
