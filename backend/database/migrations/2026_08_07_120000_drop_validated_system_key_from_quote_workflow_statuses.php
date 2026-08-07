<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Retires the 'validated' system key of `quote_workflow_statuses` (user
 * directive 2026-08-07): "Validato" stops being a system row and becomes an
 * ordinary status whose `group` is `validated`, freely assignable to any row
 * from the configurator's group select — exactly like `pending`.
 *
 * Data only, no schema change: every row that carried the key is unmarked
 * (`system_key` null) and KEEPS its `group`, so the seeded sets — the global
 * default one's "Validato" row included — read the same in the UI while
 * losing the pinned, non-deletable identity.
 */
return new class extends Migration
{
    private const string TABLE = 'quote_workflow_statuses';

    private const string VALIDATED = 'validated';

    public function up(): void
    {
        DB::table(self::TABLE)
            ->where('system_key', self::VALIDATED)
            ->update(['system_key' => null, 'updated_at' => now()]);
    }

    /**
     * Restores the mark on ONE row per set (the first `validated`-group row
     * by `sort_order`): unique(quote_workflow_id, system_key) admits no more
     * than one, and the sets this migration touched had exactly one.
     */
    public function down(): void
    {
        $ids = DB::table(self::TABLE)
            ->whereNull('system_key')
            ->where('group', self::VALIDATED)
            ->orderBy('sort_order')
            ->get(['id', 'quote_workflow_id'])
            ->unique('quote_workflow_id')
            ->pluck('id');

        DB::table(self::TABLE)
            ->whereIn('id', $ids)
            ->update(['system_key' => self::VALIDATED, 'updated_at' => now()]);
    }
};
