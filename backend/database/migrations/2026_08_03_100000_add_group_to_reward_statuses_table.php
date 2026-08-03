<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gives `reward_statuses` the same phase classification the quote/contract
 * configurators already carry (spec 0073, D-5/D-6): `group`
 * (App\Enums\RewardStatusGroup: open|pending|closed_won|closed_lost) plus the
 * THREE system rows the table was missing — "Aperto" (`new`), "Chiuso
 * positivo" (`won`), "Chiuso negativo" (`lost`) — alongside the one it
 * already had, "In attesa" (`pending`).
 *
 * Every pre-existing CUSTOM row lands on `open` (the column default): a
 * pending/closed meaning is never INFERRED for a user-created row, the same
 * conservative rule `2026_07_31_090000_split_quote_status_closed_group`
 * applied to the quote configurator.
 *
 * `sort_order` is renormalized here to the sequence
 * App\Services\Statuses\StatusOrderManager maintains from now on (spec 0073,
 * D-6): the two HEAD rows first (Aperto=0, In attesa=10), then the customs in
 * their current relative order, then the two TAIL rows (Chiuso positivo,
 * Chiuso negativo). Raw DB queries, no Model/Service: a migration must keep
 * behaving the same when that service later changes.
 *
 * NO retroactive lifecycle pass (spec 0073, scope/out): rewards belonging to
 * requests ALREADY closed negatively keep their current status — the
 * automation applies from the next working-status write onwards.
 */
return new class extends Migration
{
    /**
     * Same STEP as App\Services\Statuses\StatusOrderManager: duplicated on
     * purpose (a migration is a frozen snapshot, it must not import a service
     * constant that may change later).
     */
    private const int STEP = 10;

    /**
     * @var array<int, array{name: string, color: string, system_key: string, group: string}>
     */
    private const array NEW_SYSTEM_ROWS = [
        ['name' => 'Aperto', 'color' => 'blue', 'system_key' => 'new', 'group' => 'open'],
        ['name' => 'Chiuso positivo', 'color' => 'green', 'system_key' => 'won', 'group' => 'closed_won'],
        ['name' => 'Chiuso negativo', 'color' => 'red', 'system_key' => 'lost', 'group' => 'closed_lost'],
    ];

    public function up(): void
    {
        Schema::table('reward_statuses', function (Blueprint $table): void {
            $table->string('group', 16)->default('open')->after('color');
        });

        DB::table('reward_statuses')->where('system_key', 'pending')->update(['group' => 'pending']);

        $this->seedNewSystemRows();
        $this->renormalizeSortOrder();
    }

    public function down(): void
    {
        DB::table('reward_statuses')
            ->whereIn('system_key', array_column(self::NEW_SYSTEM_ROWS, 'system_key'))
            ->delete();

        DB::table('reward_statuses')->where('system_key', 'pending')->update(['sort_order' => 0]);

        Schema::table('reward_statuses', function (Blueprint $table): void {
            $table->dropColumn('group');
        });
    }

    private function seedNewSystemRows(): void
    {
        $now = now();

        foreach (self::NEW_SYSTEM_ROWS as $row) {
            DB::table('reward_statuses')->insert([
                ...$row,
                'description' => null,
                'sort_order' => 0,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Aperto=0, In attesa=STEP, customs (current relative order) from 2*STEP,
     * then Chiuso positivo / Chiuso negativo last, in that order.
     */
    private function renormalizeSortOrder(): void
    {
        DB::table('reward_statuses')->where('system_key', 'new')->update(['sort_order' => 0]);
        DB::table('reward_statuses')->where('system_key', 'pending')->update(['sort_order' => self::STEP]);

        $sortOrder = self::STEP;

        $customIds = DB::table('reward_statuses')
            ->whereNull('system_key')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->pluck('id');

        foreach ($customIds as $id) {
            $sortOrder += self::STEP;

            DB::table('reward_statuses')->where('id', $id)->update(['sort_order' => $sortOrder]);
        }

        foreach (['won', 'lost'] as $tailKey) {
            $sortOrder += self::STEP;

            DB::table('reward_statuses')->where('system_key', $tailKey)->update(['sort_order' => $sortOrder]);
        }
    }
};
