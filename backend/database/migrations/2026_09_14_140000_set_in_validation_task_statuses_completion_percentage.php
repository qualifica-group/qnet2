<?php

use App\Enums\TaskStatusGroup;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data migration for spec 0126, D-5: every `task_statuses` row in the
 * `in_validation` phase must read 100% complete — validation is a GATE, not
 * a stage of progress, so a task waiting on it should already show as done
 * to whoever reads only the percentage. Before this only the catalogue's
 * bootstrap rows carried 30/80 (Database\Seeders\QualificaCatalog\
 * TaskTaxonomyCatalogue, "Preanalisi da validare"/"Esecuzione da
 * validare") — no PROTECTED row has ever used this phase (see that
 * catalogue's PROTECTED_STATUSES), so this never touches a system_key row.
 *
 * up() is a blanket rewrite: EVERY row with group = in_validation is forced
 * to 100, catalogue bootstrap or admin-created custom row alike — the
 * invariant Store/UpdateTaskStatusRequest starts enforcing right after this
 * migration (D-5) has to hold for every row already in the phase, not only
 * the two the client shipped with.
 *
 * down() is DELIBERATELY NOT a full inverse. Reversing to "whatever each row
 * happened to hold before" would need a side-table recording every id's
 * prior value, which nothing else in this migration set does — see
 * 2026_09_11_100000/2026_09_11_110000, which restore only what they
 * themselves changed (`system_key`), never a value they never owned. down()
 * instead restores the only percentages this feature actually knows: the
 * two catalogue bootstrap names, matched by NAME like every other reshape in
 * this module (never by id, which a fresh install cannot predict). A custom
 * admin-created in_validation row rolls back at 100, not at whatever it held
 * before this migration ran — a known limitation of this data migration's
 * rollback, reported to the spec owner rather than silently assumed.
 */
return new class extends Migration
{
    private const string TABLE = 'task_statuses';

    /**
     * The two catalogue bootstrap names down() can honestly restore, and the
     * percentage each carried before this migration (D-5).
     *
     * @var array<string, int>
     */
    private const array BOOTSTRAP_PERCENTAGES = [
        'Preanalisi da validare' => 30,
        'Esecuzione da validare' => 80,
    ];

    public function up(): void
    {
        DB::table(self::TABLE)
            ->where('group', TaskStatusGroup::InValidation->value)
            ->where('completion_percentage', '!=', 100)
            ->update(['completion_percentage' => 100]);
    }

    public function down(): void
    {
        foreach (self::BOOTSTRAP_PERCENTAGES as $name => $percentage) {
            DB::table(self::TABLE)
                ->where('name', $name)
                ->where('group', TaskStatusGroup::InValidation->value)
                ->update(['completion_percentage' => $percentage]);
        }
    }
};
