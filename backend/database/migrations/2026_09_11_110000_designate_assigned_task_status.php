<?php

use App\Enums\TaskStatusGroup;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Designates the "Assegnato" row as the Task module's DERIVED-ASSIGNED
 * status (spec 0118, D-4/D-5): the landing status
 * App\Services\Tasks\TaskInitialStatusResolver picks for a brand-new Task
 * whose derivation does not qualify for the `open` row — two or more
 * assignees, or a single one who is neither the creator nor the requester.
 * Introduces App\Enums\TaskStatusSystemKey::Assigned. Structurally identical
 * to 2026_09_11_100000_designate_in_progress_task_status, the exact
 * precedent this migration mirrors.
 *
 * TWO paths, because migrations run BEFORE seeders:
 *   - populated install: QualificaTaskTaxonomySeeder already created the
 *     ordinary row (name 'Assegnato', color blue, icon user,
 *     completion_percentage 10 — Database\Seeders\QualificaCatalog\
 *     TaskTaxonomyCatalogue, before this spec moves it out of STATUSES). It
 *     is PROMOTED in place: only `system_key` changes, name/color/icon/
 *     percentage/sort_order stay exactly as the admin left them.
 *   - fresh install (`migrate:fresh`, before any seed runs): no such row
 *     exists yet, so it is CREATED here already protected, with the values
 *     TaskTaxonomyCatalogue::STATUSES used to carry for it — the row the
 *     seeder would otherwise have created, just already keyed. `sort_order`
 *     15 keeps it right after `in_progress` (10, pinned head) and before the
 *     first ordinary custom row (20 onward) — the same relative position
 *     "Assegnato" holds today, seeded as an ordinary row placed by
 *     StatusOrderManager::placeNew() straight after the two head rows. Those
 *     values stay admin-configurable afterwards like any other protected
 *     row.
 *
 * down() only removes the key — it returns the row to the ordinary catalog.
 * It never deletes it, key or no key: a Task may already reference it, and
 * unlike a bootstrap row's retirement this is a promotion being undone, not
 * a row that never had a real reason to exist on its own.
 */
return new class extends Migration
{
    private const string TABLE = 'task_statuses';

    private const string SYSTEM_KEY = 'assigned';

    private const string NAME = 'Assegnato';

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
            'color' => 'blue',
            'icon' => 'user',
            'sort_order' => 15,
            'is_active' => true,
            'system_key' => self::SYSTEM_KEY,
            'group' => TaskStatusGroup::Open->value,
            'completion_percentage' => 10,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::table(self::TABLE)->where('system_key', self::SYSTEM_KEY)->update(['system_key' => null]);
    }
};
