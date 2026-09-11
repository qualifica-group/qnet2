<?php

use App\Enums\TaskStatusGroup;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Designates the "In corso" row as the Task module's RESUME status (spec
 * 0116, D-4): the target the reopening actions (uncomplete, reject) of the
 * upcoming Fase 3 land on. Reintroduces App\Enums\TaskStatusSystemKey::InProgress,
 * retired by 2026_09_04_120000 for an UNRELATED reason — see that enum's
 * docblock, which explains why the two uses of the key do not conflict.
 *
 * TWO paths, because migrations run BEFORE seeders:
 *   - populated install: QualificaTaskTaxonomySeeder already created the
 *     ordinary row (name 'In corso', color violet, icon activity,
 *     completion_percentage 50 — Database\Seeders\QualificaCatalog\
 *     TaskTaxonomyCatalogue, before this spec moves it out of STATUSES). It
 *     is PROMOTED in place: only `system_key` changes, name/color/icon/
 *     percentage/sort_order stay exactly as the admin left them.
 *   - fresh install (`migrate:fresh`, before any seed runs): no such row
 *     exists yet, so it is CREATED here already protected, with the values
 *     TaskTaxonomyCatalogue::STATUSES used to carry for it — the row the
 *     seeder would otherwise have created, just already keyed. Those values
 *     stay admin-configurable afterwards like any other protected row.
 *
 * down() only removes the key — it returns the row to the ordinary catalog.
 * It never deletes it, key or no key: a Task may already reference it, and
 * unlike 2026_09_04_120000's retirement this is a promotion being undone,
 * not a bootstrap row that never had a real reason to exist on its own.
 */
return new class extends Migration
{
    private const string TABLE = 'task_statuses';

    private const string SYSTEM_KEY = 'in_progress';

    private const string NAME = 'In corso';

    public function up(): void
    {
        $existing = DB::table(self::TABLE)
            ->where('name', self::NAME)
            ->whereNull('system_key')
            ->first();

        if ($existing !== null) {
            DB::table(self::TABLE)->where('id', $existing->id)->update(['system_key' => self::SYSTEM_KEY]);

            return;
        }

        $now = now();

        DB::table(self::TABLE)->insert([
            'name' => self::NAME,
            'description' => null,
            'color' => 'violet',
            'icon' => 'activity',
            'sort_order' => 10,
            'is_active' => true,
            'system_key' => self::SYSTEM_KEY,
            'group' => TaskStatusGroup::Open->value,
            'completion_percentage' => 50,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::table(self::TABLE)->where('system_key', self::SYSTEM_KEY)->update(['system_key' => null]);
    }
};
