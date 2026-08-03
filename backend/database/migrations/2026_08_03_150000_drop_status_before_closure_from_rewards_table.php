<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drops the column backing the request -> reward lifecycle automation
 * (`2026_08_03_100100_add_status_before_closure_to_rewards_table.php`, spec
 * 0073 D-2): the automation itself is gone (user directive 2026-08-03 — a
 * buono no longer follows the working status of its request), so the saved
 * status has nothing left to restore and the "closed by the automation"
 * marker nothing left to mark.
 *
 * Rewards keep the status they carry TODAY, including the ones the automation
 * had moved onto "Chiuso negativo": no retroactive restore pass, the mirror
 * image of the no-retroactive-close rule the introducing migration applied.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rewards', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('status_before_closure_id');
        });
    }

    public function down(): void
    {
        Schema::table('rewards', function (Blueprint $table): void {
            $table->foreignId('status_before_closure_id')
                ->nullable()
                ->after('reward_status_id')
                ->constrained('reward_statuses')
                ->nullOnDelete();
        });
    }
};
