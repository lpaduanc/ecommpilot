<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adiciona configurações de tracking/analytics por loja:
     * - Google Analytics 4 (GA4)
     * - Google Tag (gtag.js)
     * - Meta Pixel (Facebook Pixel)
     * - Microsoft Clarity
     * - Hotjar
     */
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->json('tracking_settings')->nullable()->after('competitors');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('tracking_settings');
        });
    }
};
