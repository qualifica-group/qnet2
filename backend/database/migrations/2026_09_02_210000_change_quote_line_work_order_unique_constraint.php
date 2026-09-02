<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Una riga, una sola Commessa" (spec 0095, D-4/D-4a): the pivot's UNIQUE
 * moves from the PAIR (`work_order_id`, `quote_line_id`) to `quote_line_id`
 * alone — the pair constraint only stopped the same line being attached
 * TWICE to the SAME work order; it never stopped it being spread across
 * DIFFERENT ones. The pair constraint becomes redundant once the single-
 * column one exists (it already implies "at most once per work order") and
 * is dropped in the SAME migration.
 *
 * AC-041: a straight `unique()` on data that already violates it fails with
 * an opaque DB integrity error. `up()` detects that case FIRST and aborts
 * with an explicit, readable exception naming every offending
 * `quote_line_id` and the work orders currently sharing it — no environment
 * this app runs in has ever exercised the old "one line, many commesse"
 * path (D-4's own regla is new), but the migration must not assume that.
 */
return new class extends Migration
{
    private const string TABLE = 'quote_line_work_order';

    private const string PAIR_UNIQUE_INDEX = 'quote_line_work_order_work_order_id_quote_line_id_unique';

    private const string LINE_UNIQUE_INDEX = 'quote_line_work_order_quote_line_id_unique';

    private const string WORK_ORDER_FK_INDEX = 'quote_line_work_order_work_order_id_index';

    private const string LINE_FK_INDEX = 'quote_line_work_order_quote_line_id_foreign';

    public function up(): void
    {
        $this->assertNoPreExistingConflicts();

        // Step 1: dare alla FK su `work_order_id` un indice PROPRIO prima di
        // togliere il composito. Su MySQL quella FK non ne ha uno suo: si
        // appoggia all'UNIQUE di coppia, di cui `work_order_id` e' la prima
        // colonna. Eliminarlo senza questo passo fallisce con errno 1553
        // ("needed in a foreign key constraint"). SQLite non ha il vincolo,
        // quindi la suite di test NON puo' intercettare questo caso.
        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->index('work_order_id', self::WORK_ORDER_FK_INDEX);
        });

        // Step 2: sostituire il vincolo di coppia con quello su riga singola.
        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->dropUnique(self::PAIR_UNIQUE_INDEX);
            $table->unique('quote_line_id', self::LINE_UNIQUE_INDEX);
        });
    }

    public function down(): void
    {
        // Speculare a up(), e per la stessa ragione MySQL (errno 1553): dopo
        // up() l'UNIQUE su `quote_line_id` e' l'unico indice che sostiene la
        // FK su quella colonna, quindi va prima ricreato l'indice dedicato,
        // altrimenti il drop fallisce. Stesso discorso, a specchio, per
        // `work_order_id`: il suo indice si toglie solo DOPO che il composito
        // (che lo ha come prima colonna) e' tornato a coprirlo.

        // Step 1: ridare alla FK su `quote_line_id` un indice proprio.
        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->index('quote_line_id', self::LINE_FK_INDEX);
        });

        // Step 2: sostituire il vincolo su riga singola con quello di coppia.
        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->dropUnique(self::LINE_UNIQUE_INDEX);
            $table->unique(['work_order_id', 'quote_line_id'], self::PAIR_UNIQUE_INDEX);
        });

        // Step 3: il composito copre ora `work_order_id`, l'indice dedicato
        // introdotto da up() non serve piu'.
        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->dropIndex(self::WORK_ORDER_FK_INDEX);
        });
    }

    /**
     * AC-041: raises a readable exception — instead of letting the `unique()`
     * above surface an opaque DB integrity error — when any `quote_line_id`
     * is already attached to more than one DISTINCT `work_order_id`.
     */
    private function assertNoPreExistingConflicts(): void
    {
        $conflicts = DB::table(self::TABLE)
            ->select('quote_line_id')
            ->selectRaw('COUNT(DISTINCT work_order_id) as work_order_count')
            ->groupBy('quote_line_id')
            ->havingRaw('COUNT(DISTINCT work_order_id) > 1')
            ->get();

        if ($conflicts->isEmpty()) {
            return;
        }

        $lineIds = $conflicts->pluck('quote_line_id')->implode(', ');

        throw new RuntimeException(
            'Cannot enforce UNIQUE(quote_line_id) on quote_line_work_order: '.
            "quote_line_id(s) [{$lineIds}] are each attached to more than one work order. ".
            'Resolve the conflicting rows (keep exactly one work_order_id per quote_line_id) before re-running this migration.'
        );
    }
};
