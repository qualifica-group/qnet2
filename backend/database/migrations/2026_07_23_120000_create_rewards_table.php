<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reward assignment entity (spec 0059): one row is ONE voucher/reward/
 * incentive assigned to a Referent (`referent_id`, D-5 — a plain FK, the
 * polymorphism lives on the ORIGIN, not the beneficiary), typed by
 * `reward_type_id`, with a polymorphic ORIGIN (`source_type`/`source_id`,
 * morph map alias, today always `opportunity` — enforced strictly by
 * `AppServiceProvider::boot()`). No status/value column: "active"/
 * "completed" are DERIVED at read time from the origin's own state (D-2),
 * never persisted here.
 *
 * `referent_id` cascadeOnDelete: an assignment without its beneficiary has
 * no meaning (D-5). `reward_type_id` restrictOnDelete: this is the FK that
 * finally activates the 409 branch of RewardTypeService::delete(). Both FK
 * columns already get an implicit index from InnoDB when the constraint is
 * added (see `leads`/`opportunities` for the same convention) — no
 * redundant explicit `->index()` on top.
 *
 * `rewards_unique_assignment` prevents the identical voucher type being
 * assigned twice from the same origin to the same referent (AC-004).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referent_id')->constrained('referents')->cascadeOnDelete();
            $table->foreignId('reward_type_id')->constrained('reward_types')->restrictOnDelete();

            // The workflow status of the assignment (spec 0060, D-5): mandatory,
            // defaulted to the system `pending` row by
            // RewardAssignmentWriter::createAdded(). restrictOnDelete is the FK
            // the BR-4 guard (RewardStatusService::delete()) reads to reject
            // removing a status still assigned to a reward.
            $table->foreignId('reward_status_id')->constrained('reward_statuses')->restrictOnDelete();

            $table->morphs('source');
            $table->date('assigned_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(
                ['referent_id', 'reward_type_id', 'source_type', 'source_id'],
                'rewards_unique_assignment',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rewards');
    }
};
