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
        Schema::table('analyses', function (Blueprint $table) {
            // Progresso parcial da análise
            $table->unsignedTinyInteger('current_stage')->default(0)->after('status');
            $table->unsignedTinyInteger('total_stages')->default(9)->after('current_stage');

            // Dados coletados em cada estágio (permite retomada)
            $table->json('stage_data')->nullable()->after('total_stages');

            // Controle de retries por estágio
            $table->unsignedTinyInteger('stage_retry_count')->default(0)->after('stage_data');
            $table->unsignedTinyInteger('last_error_stage')->nullable()->after('stage_retry_count');

            // Flag para indicar se é uma retomada
            $table->boolean('is_resuming')->default(false)->after('last_error_stage');

            // Timestamp do último progresso salvo
            $table->timestamp('last_progress_at')->nullable()->after('is_resuming');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('analyses', function (Blueprint $table) {
            $table->dropColumn([
                'current_stage',
                'total_stages',
                'stage_data',
                'stage_retry_count',
                'last_error_stage',
                'is_resuming',
                'last_progress_at',
            ]);
        });
    }
};
