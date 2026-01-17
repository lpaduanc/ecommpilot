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
        Schema::table('stores', function (Blueprint $table) {
            $table->string('niche', 50)->nullable()->after('metadata');
            $table->string('niche_subcategory', 50)->nullable()->after('niche');
            $table->decimal('monthly_goal', 12, 2)->nullable()->after('niche_subcategory');
            $table->decimal('annual_goal', 14, 2)->nullable()->after('monthly_goal');
            $table->decimal('target_ticket', 10, 2)->nullable()->after('annual_goal');
            $table->decimal('monthly_revenue', 12, 2)->nullable()->after('target_ticket');
            $table->integer('monthly_visits')->nullable()->after('monthly_revenue');
            $table->json('competitors')->nullable()->after('monthly_visits');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn([
                'niche',
                'niche_subcategory',
                'monthly_goal',
                'annual_goal',
                'target_ticket',
                'monthly_revenue',
                'monthly_visits',
                'competitors',
            ]);
        });
    }
};
