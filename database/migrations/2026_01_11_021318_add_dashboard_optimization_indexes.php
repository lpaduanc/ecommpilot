<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('synced_orders', function (Blueprint $table) {
            // Composite index for dashboard stats query (store_id + payment_status + external_created_at)
            // This dramatically improves the performance of the consolidated stats query
            $table->index(['store_id', 'payment_status', 'external_created_at'], 'idx_orders_dashboard_stats');

            // Composite index for date range queries (store_id + external_created_at)
            // Improves revenue chart, top products, and other date-filtered queries
            $table->index(['store_id', 'external_created_at'], 'idx_orders_date_range');
        });

        // Convert items column to jsonb if it's json, then add GIN index (PostgreSQL specific)
        // This significantly speeds up jsonb_array_elements queries in getTopProducts()
        DB::statement('ALTER TABLE synced_orders ALTER COLUMN items TYPE jsonb USING items::jsonb');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_orders_items_gin ON synced_orders USING GIN (items jsonb_path_ops)');

        // Convert shipping_address to jsonb as well for consistency
        DB::statement('ALTER TABLE synced_orders ALTER COLUMN shipping_address TYPE jsonb USING shipping_address::jsonb');

        Schema::table('synced_products', function (Blueprint $table) {
            // Composite index for low stock queries (store_id + is_active + stock_quantity)
            $table->index(['store_id', 'is_active', 'stock_quantity'], 'idx_products_low_stock');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('synced_orders', function (Blueprint $table) {
            $table->dropIndex('idx_orders_dashboard_stats');
            $table->dropIndex('idx_orders_date_range');
        });

        DB::statement('DROP INDEX IF EXISTS idx_orders_items_gin');

        // Revert jsonb to json (optional - might want to keep jsonb for performance)
        DB::statement('ALTER TABLE synced_orders ALTER COLUMN items TYPE json USING items::json');
        DB::statement('ALTER TABLE synced_orders ALTER COLUMN shipping_address TYPE json USING shipping_address::json');

        Schema::table('synced_products', function (Blueprint $table) {
            $table->dropIndex('idx_products_low_stock');
        });
    }
};
