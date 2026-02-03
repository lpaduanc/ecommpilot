<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adiciona campo website_url para armazenar o site real/customizado da loja,
     * diferente do domínio que vem da integração Nuvemshop.
     */
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->string('website_url')->nullable()->after('domain');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('website_url');
        });
    }
};
