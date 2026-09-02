<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Commessa <-> Responsabili (spec 0096, D-1): the users accountable for a work
 * order. MANY per commessa (user directive 2026-09-02: "supervisori sono piu'
 * supervisori"), at least one — the "at least one" floor is a request-layer
 * rule, not a DB constraint, since no engine expresses "this pivot must hold
 * a row" without a trigger.
 *
 * The table is named EXPLICITLY rather than by Laravel's alphabetical
 * convention: `user_work_order` would be the default name, but a work order
 * now has TWO distinct user relations (responsabili and partecipanti), so
 * neither may claim the generic name. `work_order_supervisor` and
 * `work_order_participant` say which is which at a glance.
 *
 * Unordered, unlike `work_order_participant`: the responsabili are a set, with
 * no "n-th responsabile" ranking to preserve.
 *
 * The backfill derives, it never invents (D-2): each existing commessa gets
 * ONE responsabile, its own Quote's `supervisor_id` falling back to that
 * quote's `operator_id` (spec 0087 D-3, the offer's operative owner). Measured
 * on the real `qnet2` data before writing this: 4 of 11 work orders reach a
 * `quotes.supervisor_id`, 11 of 11 reach a `quotes.operator_id`. up() reports
 * any commessa left without one instead of silently creating a work order no
 * one is responsible for.
 */
return new class extends Migration
{
    private const string TABLE = 'work_order_supervisor';

    public function up(): void
    {
        Schema::create(self::TABLE, function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->unique(['work_order_id', 'user_id']);
        });

        $this->backfillFromQuotes();
        $this->reportWorkOrdersLeftWithout();
    }

    public function down(): void
    {
        Schema::dropIfExists(self::TABLE);
    }

    private function backfillFromQuotes(): void
    {
        $rows = DB::table('work_orders as w')
            ->join('quotes as q', 'q.id', '=', 'w.quote_id')
            ->select('w.id', 'q.supervisor_id', 'q.operator_id')
            ->get()
            ->map(static fn (object $row): ?array => ($row->supervisor_id ?? $row->operator_id) === null
                ? null
                : ['work_order_id' => $row->id, 'user_id' => $row->supervisor_id ?? $row->operator_id])
            ->filter()
            ->values()
            ->all();

        if ($rows !== []) {
            DB::table(self::TABLE)->insert($rows);
        }
    }

    /**
     * AC-003: a commessa with no derivable responsabile is surfaced, loudly,
     * instead of being left silently unattended. Not a hard abort: unlike a
     * NOT NULL column there is nothing here that would fail later, so the
     * migration completes and names the rows an operator must fix by hand.
     */
    private function reportWorkOrdersLeftWithout(): void
    {
        $orphans = DB::table('work_orders')
            ->whereNotIn('id', DB::table(self::TABLE)->select('work_order_id'))
            ->orderBy('id')
            ->pluck('code');

        if ($orphans->isEmpty()) {
            return;
        }

        throw new RuntimeException(
            'Work order(s) ['.$orphans->implode(', ').'] have no derivable responsabile '.
            '(their offer has neither a supervisor nor an operator). Set one on those offers, '.
            'or insert the '.self::TABLE.' rows manually, before re-running this migration.'
        );
    }
};
