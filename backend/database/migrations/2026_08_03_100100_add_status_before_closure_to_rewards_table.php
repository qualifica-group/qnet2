<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs the reward lifecycle automation (spec 0073, D-2): the status a reward
 * carried BEFORE App\Services\Rewards\RewardLifecycleManager closed it because
 * its originating request entered a `closed_lost` working status, restored
 * verbatim when the request reopens.
 *
 * Modelled on `contracts.status_before_suspension_id`
 * (`2026_08_01_100100_create_contracts_table.php`), `nullOnDelete` for the
 * same defense-in-depth reason: losing the referenced status must never block
 * the status' own deletion, and a reward whose saved status vanished simply
 * has nothing to restore.
 *
 * The column doubles as the "closed by the automation" MARKER (D-2): non-null
 * means the current status was imposed by the automation, null means it is
 * the one a human chose. That is what makes reconcile() idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rewards', function (Blueprint $table): void {
            $table->foreignId('status_before_closure_id')
                ->nullable()
                ->after('reward_status_id')
                ->constrained('reward_statuses')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('rewards', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('status_before_closure_id');
        });
    }
};
