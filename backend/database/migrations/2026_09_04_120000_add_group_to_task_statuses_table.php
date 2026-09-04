<?php

use App\Enums\TaskStatusGroup;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Splits the Task status PHASE off `system_key` into its own `group` column
 * (spec 0101, D-5 rectified by the user directive 2026-09-04), giving
 * `task_statuses` the same two-column shape every other status configurator
 * already has (`contract_statuses`, spec 0072).
 *
 * WHY. `system_key` is UNIQUE: it can mark at most ONE row per value. Using
 * it as the phase set therefore capped the configurator at six statuses,
 * one per phase — while the client's own vocabulary has ten, several of
 * which share a phase (five are "open", two are "in validation"). The phase
 * is a MANY-to-one classification and needs a non-unique column of its own.
 *
 * Three of the six keys created by 2026_09_04_100400 were phases, not
 * protected rows: `in_progress`, `pending` and `in_validation` are dropped
 * from App\Enums\TaskStatusSystemKey and live on as TaskStatusGroup cases.
 * Their rows are NOT deleted — they lose the key and stay as ordinary,
 * renameable, deletable statuses carrying the matching `group`. The three
 * that remain protected (`open`, `closed_positive`, `closed_negative`) are
 * the minimum the module needs: somewhere to open a Task, and somewhere to
 * close it on each outcome.
 *
 * `group` is NOT NULL with an `open` default so the column is well-defined
 * on every pre-existing row before the backfill below runs.
 */
return new class extends Migration
{
    private const string TABLE = 'task_statuses';

    private const string COLUMN = 'group';

    /**
     * The phase each of the six bootstrap `system_key` values maps onto.
     * The three that are also staying system keys map onto their namesake
     * phase; the three being retired map onto the phase they always were.
     *
     * @var array<string, string>
     */
    private const array PHASE_BY_LEGACY_KEY = [
        'open' => 'open',
        'in_progress' => 'open',
        'pending' => 'pending',
        'in_validation' => 'in_validation',
        'closed_positive' => 'closed_positive',
        'closed_negative' => 'closed_negative',
    ];

    /**
     * The three rows 2026_09_04_100400 created for keys that were PHASES
     * rather than protected rows, with the bootstrap values it gave each.
     * They are retired here: with the phase now on `group`, a row per phase
     * is redundant — the phase set is the enum, not a set of records — and
     * leaving them behind would ship every installation three statuses
     * nobody chose, on top of whatever pick-list the admin configures.
     *
     * Retirement is guarded (see retirePhaseRows): a row still referenced by
     * a Task, or already renamed, is kept and merely de-keyed. The values
     * below double as the fingerprint of an untouched row and as what down()
     * puts back.
     *
     * @var array<string, array{system_key: string, color: string, sort_order: int, completion_percentage: int}>
     */
    private const array RETIRED_PHASE_ROWS = [
        'In corso' => ['system_key' => 'in_progress', 'color' => 'blue', 'sort_order' => 10, 'completion_percentage' => 25],
        'In sospeso' => ['system_key' => 'pending', 'color' => 'amber', 'sort_order' => 20, 'completion_percentage' => 50],
        'In validazione' => ['system_key' => 'in_validation', 'color' => 'orange', 'sort_order' => 30, 'completion_percentage' => 75],
    ];

    public function up(): void
    {
        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->string(self::COLUMN, 16)->default(TaskStatusGroup::Open->value)->after('system_key');
        });

        $this->backfillPhases();
        $this->retirePhaseRows();
    }

    public function down(): void
    {
        $this->restorePhaseRows();

        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->dropColumn(self::COLUMN);
        });
    }

    /**
     * Every row that carries a bootstrap key gets the phase that key stood
     * for. Custom rows (`system_key` NULL) keep the column default, `open`:
     * they belonged to no phase before this migration, and `open` is the
     * only non-closing, non-waiting value — the neutral one.
     */
    private function backfillPhases(): void
    {
        foreach (self::PHASE_BY_LEGACY_KEY as $systemKey => $phase) {
            DB::table(self::TABLE)
                ->where('system_key', $systemKey)
                ->update([self::COLUMN => $phase]);
        }
    }

    /**
     * Removes the three rows that stood for a phase. The key goes first and
     * unconditionally — it no longer exists in the enum, so leaving it would
     * make the model's cast fail on read. The ROW then goes too, but only
     * when it is safe to remove: still named as the migration created it (so
     * an admin who adopted it into their own pick-list keeps it) and unused
     * by any Task (`task_status_id` is restrictOnDelete, so this turns a
     * would-be error into a skip). A kept row simply stays on as an
     * ordinary status carrying the phase backfillPhases() gave it.
     */
    private function retirePhaseRows(): void
    {
        foreach (self::RETIRED_PHASE_ROWS as $name => $bootstrap) {
            $id = DB::table(self::TABLE)
                ->where('system_key', $bootstrap['system_key'])
                ->value('id');

            if ($id === null) {
                continue;
            }

            DB::table(self::TABLE)->where('id', $id)->update(['system_key' => null]);

            $isUntouched = DB::table(self::TABLE)->where('id', $id)->where('name', $name)->exists();
            $isInUse = DB::table('tasks')->where('task_status_id', $id)->exists();

            if ($isUntouched && ! $isInUse) {
                DB::table(self::TABLE)->where('id', $id)->delete();
            }
        }
    }

    /**
     * Rollback: put each retired row back with the values 2026_09_04_100400
     * gave it, or merely re-key the one that survived. Skipped whenever the
     * key or the name is already taken, so neither UNIQUE index can be
     * violated whatever happened in between.
     */
    private function restorePhaseRows(): void
    {
        $now = now();

        foreach (self::RETIRED_PHASE_ROWS as $name => $bootstrap) {
            if (DB::table(self::TABLE)->where('system_key', $bootstrap['system_key'])->exists()) {
                continue;
            }

            $survivor = DB::table(self::TABLE)->where('name', $name)->whereNull('system_key')->value('id');

            if ($survivor !== null) {
                DB::table(self::TABLE)->where('id', $survivor)->update(['system_key' => $bootstrap['system_key']]);

                continue;
            }

            DB::table(self::TABLE)->insert([
                'name' => $name,
                'description' => null,
                'color' => $bootstrap['color'],
                'icon' => null,
                'sort_order' => $bootstrap['sort_order'],
                'is_active' => true,
                'system_key' => $bootstrap['system_key'],
                'completion_percentage' => $bootstrap['completion_percentage'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
};
