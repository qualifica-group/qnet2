<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tightens `rewards.reward_status_id` to NOT NULL (spec 0060, D-5/Lane B):
 * the column was added NULLABLE by 2026_07_24_150100 (Lane A, scoped to the
 * reward-statuses configurator only). This migration is the write-path
 * counterpart, run only once RewardAssignmentWriter::createAdded() (Lane B)
 * guarantees every NEW reward gets a default status — so existing rows are
 * first backfilled to the system `pending` row (resolved by `system_key`,
 * never a hardcoded id, mirroring 2026_07_17_200001's own
 * `where('system_key', ...)` lookup), then the column is tightened.
 *
 * On an empty table (migrate:fresh, tests) the backfill `UPDATE` matches zero
 * rows — a no-op, keeping the clean seed clean.
 *
 * `->change()` on a FK column relies on Laravel's own native schema rebuild
 * (SQLite) / `MODIFY COLUMN ... NOT NULL` (MySQL, leaves the separate FK
 * constraint object untouched) — same reasoning as
 * 2026_07_17_200001_add_opportunity_status_id_to_opportunities_table.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        $pendingStatusId = DB::table('reward_statuses')->where('system_key', 'pending')->value('id');

        DB::table('rewards')->whereNull('reward_status_id')->update(['reward_status_id' => $pendingStatusId]);

        Schema::table('rewards', function (Blueprint $table) {
            $table->foreignId('reward_status_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('rewards', function (Blueprint $table) {
            $table->foreignId('reward_status_id')->nullable()->change();
        });
    }
};
