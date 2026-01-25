<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->boolean('has_suggestion_discussion')->default(false)->after('has_ai_chat');
            $table->boolean('has_suggestion_history')->default(false)->after('has_suggestion_discussion');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['has_suggestion_discussion', 'has_suggestion_history']);
        });
    }
};
