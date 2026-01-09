<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Check and add softDeletes only if column doesn't exist
        if (! Schema::hasColumn('stores', 'deleted_at')) {
            Schema::table('stores', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        // synced_products already has softDeletes in original migration
        // synced_orders already has softDeletes in original migration

        if (! Schema::hasColumn('synced_customers', 'deleted_at')) {
            Schema::table('synced_customers', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        if (! Schema::hasColumn('analyses', 'deleted_at')) {
            Schema::table('analyses', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('stores', 'deleted_at')) {
            Schema::table('stores', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }

        // synced_products, synced_orders - handled by original migrations

        if (Schema::hasColumn('synced_customers', 'deleted_at')) {
            Schema::table('synced_customers', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }

        if (Schema::hasColumn('analyses', 'deleted_at')) {
            Schema::table('analyses', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
