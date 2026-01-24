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
        Schema::table('suggestions', function (Blueprint $table) {
            $table->timestamp('accepted_at')->nullable()->after('completed_at');
        });

        // Migrate existing statuses to new format
        // pending -> new
        // ignored -> rejected
        // in_progress/completed stay the same but need accepted_at set
        DB::table('suggestions')
            ->where('status', 'pending')
            ->update(['status' => 'new']);

        DB::table('suggestions')
            ->where('status', 'ignored')
            ->update(['status' => 'rejected']);

        // Set accepted_at for in_progress and completed suggestions
        DB::table('suggestions')
            ->whereIn('status', ['in_progress', 'completed'])
            ->whereNull('accepted_at')
            ->update(['accepted_at' => DB::raw('created_at')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert status changes
        DB::table('suggestions')
            ->where('status', 'new')
            ->update(['status' => 'pending']);

        DB::table('suggestions')
            ->where('status', 'rejected')
            ->update(['status' => 'ignored']);

        DB::table('suggestions')
            ->where('status', 'accepted')
            ->update(['status' => 'pending']);

        Schema::table('suggestions', function (Blueprint $table) {
            $table->dropColumn('accepted_at');
        });
    }
};
