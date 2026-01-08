<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Índices compostos para synced_orders
        Schema::table('synced_orders', function (Blueprint $table) {
            // Índice para consultas de dashboard por período
            $table->index(['store_id', 'external_created_at'], 'idx_orders_store_date');
            // Índice para filtros de pagamento por período
            $table->index(['store_id', 'payment_status', 'external_created_at'], 'idx_orders_store_payment_date');
            // Índice para busca por email de cliente
            $table->index(['customer_email', 'store_id'], 'idx_orders_customer_store');
        });

        // Índices compostos para synced_products
        Schema::table('synced_products', function (Blueprint $table) {
            // Índice para listagem de produtos com estoque
            $table->index(['store_id', 'stock_quantity'], 'idx_products_store_stock');
            // Índice para produtos ativos ordenados por data
            $table->index(['store_id', 'is_active', 'external_created_at'], 'idx_products_store_active_date');
        });

        // Índices para analyses
        Schema::table('analyses', function (Blueprint $table) {
            // Índice para busca de análises recentes por usuário/loja
            $table->index(['user_id', 'store_id', 'created_at'], 'idx_analyses_user_store_date');
        });
    }

    public function down(): void
    {
        Schema::table('synced_orders', function (Blueprint $table) {
            $table->dropIndex('idx_orders_store_date');
            $table->dropIndex('idx_orders_store_payment_date');
            $table->dropIndex('idx_orders_customer_store');
        });

        Schema::table('synced_products', function (Blueprint $table) {
            $table->dropIndex('idx_products_store_stock');
            $table->dropIndex('idx_products_store_active_date');
        });

        Schema::table('analyses', function (Blueprint $table) {
            $table->dropIndex('idx_analyses_user_store_date');
        });
    }
};
