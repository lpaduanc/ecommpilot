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
        Schema::table('suggestions', function (Blueprint $table) {
            $table->boolean('was_successful')->nullable()->after('status');
            $table->text('feedback')->nullable()->after('was_successful');
            $table->json('metrics_impact')->nullable()->after('feedback');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suggestions', function (Blueprint $table) {
            $table->dropColumn(['was_successful', 'feedback', 'metrics_impact']);
        });
    }
};
