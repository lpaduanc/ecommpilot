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
        $tables = [
            'stores',
            'analyses',
            'suggestions',
            'users',
            'synced_products',
            'synced_orders',
            'synced_customers',
            'synced_coupons',
            'chat_conversations',
            'chat_messages',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->uuid('uuid')->nullable()->after('id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'stores',
            'analyses',
            'suggestions',
            'users',
            'synced_products',
            'synced_orders',
            'synced_customers',
            'synced_coupons',
            'chat_conversations',
            'chat_messages',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('uuid');
            });
        }
    }
};
