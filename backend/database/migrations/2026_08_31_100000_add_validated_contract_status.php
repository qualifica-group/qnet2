<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds the positive-outcome contract status "Validato" (user directive
 * 2026-08-31): `contract_statuses` shipped with the D-2 seed carrying NO
 * `closed_won` row at all, so a validated contract had nowhere to land and
 * the detail's action bar could not tell "da validare" from "validato".
 *
 * A SYSTEM row (`system_key` = 'validated', App\Enums\StatusSystemKey::
 * Validated) pinned to the HEAD sequence right after "Da validare"
 * (App\Models\ContractStatus::SYSTEM_HEAD_KEYS): App\Services\
 * ContractActionService::validate() resolves it by key as its default
 * destination, so it must be neither deletable nor movable to another group
 * (App\Services\Statuses\SystemStatusGuard).
 *
 * The head sequence grows from one row to two, so every existing row from
 * sort_order 10 on shifts +10 — StatusOrderManager's invariant: head rows at
 * 0..(n-1)*STEP, then the custom rows, then the tail.
 */
return new class extends Migration
{
    private const string TABLE = 'contract_statuses';

    private const string VALIDATED = 'validated';

    private const int VALIDATED_SORT_ORDER = 10;

    private const int STEP = 10;

    public function up(): void
    {
        $now = now();

        DB::table(self::TABLE)
            ->where('sort_order', '>=', self::VALIDATED_SORT_ORDER)
            ->increment('sort_order', self::STEP, ['updated_at' => $now]);

        DB::table(self::TABLE)->insert([
            'name' => 'Validato',
            'description' => null,
            'color' => 'emerald',
            'sort_order' => self::VALIDATED_SORT_ORDER,
            'is_active' => true,
            'is_default' => false,
            'system_key' => self::VALIDATED,
            'group' => 'closed_won',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Reversible only while no contract references the row
     * (`contract_status_id` is restrictOnDelete): a rollback on a database
     * where contracts were already validated is rejected by the schema, as
     * intended.
     */
    public function down(): void
    {
        DB::table(self::TABLE)->where('system_key', self::VALIDATED)->delete();

        DB::table(self::TABLE)
            ->where('sort_order', '>', self::VALIDATED_SORT_ORDER)
            ->decrement('sort_order', self::STEP, ['updated_at' => now()]);
    }
};
