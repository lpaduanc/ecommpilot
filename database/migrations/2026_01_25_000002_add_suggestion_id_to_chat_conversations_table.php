<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->foreignId('suggestion_id')->nullable()->after('store_id')->constrained('suggestions')->onDelete('cascade');
            $table->index(['user_id', 'suggestion_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->dropForeign(['suggestion_id']);
            $table->dropIndex(['user_id', 'suggestion_id', 'status']);
            $table->dropColumn('suggestion_id');
        });
    }
};
