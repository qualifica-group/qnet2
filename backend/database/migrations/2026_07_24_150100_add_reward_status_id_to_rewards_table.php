<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds `rewards.reward_status_id` (spec 0060), nullable, restrictOnDelete —
 * this is the FK the BR-4 delete-guard (RewardStatusService::delete()) reads
 * to reject removing a status still assigned to a reward. Deliberately
 * NULLABLE, no backfill, no NOT NULL tightening here: this migration is
 * scoped to the reward-statuses configurator (Lane A). Turning the column
 * mandatory (spec 0060 D-5: NOT NULL + backfill to the `pending` system row)
 * belongs together with the write-path change that finally guarantees every
 * new reward gets a default status (RewardAssignmentWriter::createAdded(),
 * Lane B) — mirrors the two-step precedent
 * (2026_07_23_120000_create_rewards_table.php then a LATER mandatory-FK
 * migration, e.g. 2026_07_17_200001_add_opportunity_status_id_to_opportunities_table.php),
 * except split across lanes instead of across an already-existing table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rewards', function (Blueprint $table) {
            $table->foreignId('reward_status_id')
                ->nullable()
                ->after('reward_type_id')
                ->constrained('reward_statuses')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('rewards', function (Blueprint $table) {
            $table->dropForeign(['reward_status_id']);
            $table->dropColumn('reward_status_id');
        });
    }
};
