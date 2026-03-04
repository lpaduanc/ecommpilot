<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('synced_customers', function (Blueprint $table) {
            $table->index(['store_id', 'total_spent'], 'synced_customers_store_total_spent_idx');
        });
    }

    public function down(): void
    {
        Schema::table('synced_customers', function (Blueprint $table) {
            $table->dropIndex('synced_customers_store_total_spent_idx');
        });
    }
};
