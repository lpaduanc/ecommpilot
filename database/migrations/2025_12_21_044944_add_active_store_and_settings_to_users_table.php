<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('active_store_id')
                ->nullable()
                ->after('ai_credits')
                ->constrained('stores')
                ->nullOnDelete();
            
            $table->json('notification_settings')->nullable()->after('active_store_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['active_store_id']);
            $table->dropColumn(['active_store_id', 'notification_settings']);
        });
    }
};
