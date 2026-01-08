<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('synced_products', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('synced_orders', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('synced_customers', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('analyses', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('synced_products', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('synced_orders', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('synced_customers', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('analyses', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
