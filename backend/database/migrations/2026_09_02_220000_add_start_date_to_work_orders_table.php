<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `work_orders.start_date` (spec 0096, D-1): NOT NULL — a Commessa always has
 * a start date, set from the Contract's "Programma" dialog at creation.
 *
 * `callback_date` is DELIBERATELY untouched: "data richiamo" is a different,
 * still-nullable concept.
 *
 * Same three-step shape as 2026_09_01_100100_add_unit_of_measure_id_to_
 * products_table.php (add nullable -> backfill -> tighten to NOT NULL), so
 * the ALTER never fails against pre-existing rows on either MySQL (prod) or
 * SQLite (dev/test). The backfill derives, it never invents (D-2):
 * `start_date` = DATE(created_at), the only non-arbitrary date already on the
 * row. Should any row stay unresolved, up() ABORTS naming the ids rather than
 * letting an opaque NOT NULL integrity error surface (AC-003).
 */
return new class extends Migration
{
    private const string TABLE = 'work_orders';

    private const string START_DATE_INDEX = 'work_orders_start_date_index';

    public function up(): void
    {
        // Step 1: add the column nullable, so the ALTER is safe against
        // pre-existing rows.
        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->date('start_date')->nullable()->after('type')->index(self::START_DATE_INDEX);
        });

        // Step 2: derive the value for the rows that already exist.
        $this->backfillStartDate();

        // Step 3: refuse to tighten the schema over data we could not derive.
        $this->assertBackfillComplete();

        // Step 4: only now the column can carry NOT NULL.
        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->date('start_date')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        // The index must go FIRST and in its own statement: SQLite refuses to
        // drop a column an index still references ("error in index
        // work_orders_start_date_index after drop column"), and the whole
        // suite's migration round-trip tests run on SQLite.
        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->dropIndex(self::START_DATE_INDEX);
        });

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->dropColumn('start_date');
        });
    }

    /**
     * The row's own creation day: the only date already on `work_orders` that
     * is not arbitrary. Query-builder only, so the statement runs unchanged
     * on MySQL and SQLite.
     */
    private function backfillStartDate(): void
    {
        DB::table(self::TABLE)
            ->whereNull('start_date')
            ->orderBy('id')
            ->select('id', 'created_at')
            ->get()
            ->each(function (object $row): void {
                DB::table(self::TABLE)
                    ->where('id', $row->id)
                    ->update(['start_date' => substr((string) $row->created_at, 0, 10)]);
            });
    }

    /**
     * AC-003: a readable abort instead of an opaque NOT NULL integrity error,
     * naming exactly which work orders could not be resolved.
     */
    private function assertBackfillComplete(): void
    {
        $unresolved = DB::table(self::TABLE)->whereNull('start_date')->orderBy('id')->pluck('id');

        if ($unresolved->isEmpty()) {
            return;
        }

        throw new RuntimeException(
            'Cannot make work_orders.start_date NOT NULL: work order id(s) ['.
            $unresolved->implode(', ').'] have no created_at to derive it from. '.
            'Fill the column manually before re-running this migration.'
        );
    }
};
