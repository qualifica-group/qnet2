<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Spec 0330 D-10: every anagrafica that already has referents gets, as its
 * principale, the referent inserted first (lowest pivot id). Idempotent: a
 * re-run recomputes the same value from the same pivot.
 */
return new class extends Migration
{
    public function up(): void
    {
        $firstReferent = DB::table('referent_registry as rr')
            ->select('rr.referent_id')
            ->whereColumn('rr.registry_id', 'registries.id')
            ->orderBy('rr.id')
            ->limit(1);

        DB::table('registries')
            ->whereExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('referent_registry')
                    ->whereColumn('referent_registry.registry_id', 'registries.id');
            })
            ->update(['primary_referent_id' => $firstReferent]);
    }

    /**
     * No-op: the principale is user-editable data, and the pre-backfill value
     * (null) cannot be told apart from a principale chosen later. Rolling the
     * schema migration back drops the column anyway.
     */
    public function down(): void {}
};
