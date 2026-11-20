<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0395: the working phase of a commessa (the legacy `orders.status`)
 * becomes a column of its own, independent from "Stato lavorazione". The
 * backfill approximates the legacy for the commesse already imported (D-6);
 * re-running the `work-orders` source with "update existing" writes the exact
 * legacy value on the untouched ones.
 */
return new class extends Migration
{
    /** The force-close reasons the legacy import writes for statuses 3, 4 and 5. */
    private const array LEGACY_CLOSE_PHASES = [
        'Chiusa (legacy)' => 'completed',
        'Annullata (legacy)' => 'cancelled',
        'Disdetta (legacy)' => 'terminated',
    ];

    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->string('work_phase', 20)->default('to_plan')->after('is_force_closed')->index();
            $table->string('work_phase_before_termination', 20)->nullable()->after('work_phase');
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropIndex(['work_phase']);
            $table->dropColumn(['work_phase', 'work_phase_before_termination']);
        });
    }

    private function backfill(): void
    {
        $hasSupervisor = static fn ($query) => $query->whereExists(
            static fn ($exists) => $exists->select(DB::raw(1))->from('work_order_supervisor')->whereColumn('work_order_supervisor.work_order_id', 'work_orders.id'),
        );

        // Step 1: the open ones: a Responsabile means the work has started (legacy AddTecnico).
        $hasSupervisor(DB::table('work_orders')->where('is_force_closed', false))->update(['work_phase' => 'in_progress']);

        // Step 2: the closed ones, by the group of their closing status...
        $closed = static fn () => DB::table('work_orders')->where('is_force_closed', true);
        $closed()->update(['work_phase' => 'cancelled']);
        $closed()->whereIn('work_order_workflow_status_id', DB::table('work_order_workflow_statuses')->where('group', 'completed')->select('id'))
            ->update(['work_phase' => 'completed']);

        // Step 3: ...a contract termination done in qnet-2 (spec 0324 closure)...
        $terminated = static fn () => $closed()->whereNotNull('status_before_contract_termination_id');
        $terminated()->update(['work_phase' => 'terminated', 'work_phase_before_termination' => 'to_plan']);
        $hasSupervisor($terminated())->update(['work_phase_before_termination' => 'in_progress']);

        // Step 4: ...and, last, the legacy status carried by the import's reason.
        foreach (self::LEGACY_CLOSE_PHASES as $reason => $phase) {
            $closed()->where('force_close_reason', $reason)->update(['work_phase' => $phase]);
        }
    }
};
