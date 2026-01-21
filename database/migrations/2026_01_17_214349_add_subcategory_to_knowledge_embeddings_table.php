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
        Schema::table('knowledge_embeddings', function (Blueprint $table) {
            $table->string('subcategory', 100)->nullable()->after('niche');
            $table->index(['category', 'niche', 'subcategory'], 'knowledge_category_niche_subcategory_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('knowledge_embeddings', function (Blueprint $table) {
            $table->dropIndex('knowledge_category_niche_subcategory_idx');
            $table->dropColumn('subcategory');
        });
    }
};
