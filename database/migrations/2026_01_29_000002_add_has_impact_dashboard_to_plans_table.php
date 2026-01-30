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
        Schema::table('plans', function (Blueprint $table) {
            $table->boolean('has_impact_dashboard')->default(false)->after('has_external_integrations');
        });

        // Ativar para Enterprise
        DB::table('plans')
            ->where('slug', 'enterprise')
            ->update(['has_impact_dashboard' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('has_impact_dashboard');
        });
    }
};
