<?php

namespace Database\Seeders;

use App\Models\TaskCategory;
use App\Models\TaskImportance;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use App\Models\TaskType;
use App\Services\Lookups\LookupOrderManager;
use App\Services\Statuses\StatusOrderManager;
use Database\Seeders\QualificaCatalog\TaskTaxonomyCatalogue;
use Illuminate\Database\Seeder;

/**
 * The client's classification vocabulary for the Tasks module (spec 0101):
 * the four PURE lookups a Task is classified on — Tipologia, Categoria,
 * Priorita', Importanza — plus the status pick-list. Hard-coded reference
 * data, not fixtures: a production installation needs these rows, which is
 * why this is a step of QualificaProductionDataSeeder and carries no `Demo`
 * prefix. The rows themselves live in
 * QualificaCatalog\TaskTaxonomyCatalogue.
 *
 * The rows are ORDINARY records: no enum, no constant, no name-based
 * condition anywhere in the application. Once seeded they are renamed,
 * recoloured, reordered or deleted from their module like any other row.
 * The only exception is the three PROTECTED statuses, matched by
 * `system_key` and never by label (D-5).
 *
 * `sort_order` is never written literally: it comes from the order managers
 * that own that column (LookupOrderManager for the four pure lookups,
 * StatusOrderManager for the statuses, which additionally pins the protected
 * head/tail rows), so the seeded sequence is the one the module itself would
 * produce and no step constant is duplicated here.
 *
 * Idempotent on the natural `name` key (AC-005): a re-run adopts the
 * existing row and overwrites neither a rename nor a manual recolour or
 * reorder made from the module. The `is_default` marker (spec 0154, D-8) is
 * idempotent the same way: it is only ever applied while NO row of that
 * table carries it yet, so an admin's own default choice (from the module,
 * or a previous run of this very seeder) always survives a re-run.
 */
class QualificaTaskTaxonomySeeder extends Seeder
{
    public function __construct(
        private readonly LookupOrderManager $lookupOrderManager,
        private readonly StatusOrderManager $statusOrderManager,
    ) {}

    public function run(): void
    {
        // Step 1: the four pure lookups — identical shape, one loop each.
        $this->seedLookup(TaskType::class, TaskTaxonomyCatalogue::TYPES);
        $this->seedLookup(TaskCategory::class, TaskTaxonomyCatalogue::CATEGORIES);
        $this->seedLookup(TaskPriority::class, TaskTaxonomyCatalogue::PRIORITIES);
        $this->seedLookup(TaskImportance::class, TaskTaxonomyCatalogue::IMPORTANCES);

        // Step 2: give the three protected rows the client's wording, before
        // anything else can collide with a name on them.
        $this->reshapeProtectedStatuses();
        // Step 3: the client's own statuses, placed between head and tail.
        $this->seedStatuses();

        // Step 4: the client's chosen defaults (spec 0154, D-8) — "Attivita'"
        // type, "Media" priority, "Media" importance. task_categories has no
        // `is_default` column at all (D-1 introduces nesting there, not a
        // default marker), so it is deliberately absent from this step.
        $this->markDefaultIfNoneSet(TaskType::class, TaskTaxonomyCatalogue::DEFAULT_TYPE);
        $this->markDefaultIfNoneSet(TaskPriority::class, TaskTaxonomyCatalogue::DEFAULT_PRIORITY);
        $this->markDefaultIfNoneSet(TaskImportance::class, TaskTaxonomyCatalogue::DEFAULT_IMPORTANCE);
    }

    /**
     * The four pure lookups share the exact same name/color/icon/sort_order
     * shape (D-4), so they share one insert loop rather than four copies.
     *
     * @param  class-string<TaskType>|class-string<TaskCategory>|class-string<TaskPriority>|class-string<TaskImportance>  $modelClass
     * @param  array<int, array{0: string, 1: string, 2: string}>  $rows
     */
    private function seedLookup(string $modelClass, array $rows): void
    {
        foreach ($rows as [$name, $color, $icon]) {
            $modelClass::query()->firstOrCreate(
                ['name' => $name],
                [
                    'color' => $color,
                    'icon' => $icon,
                    // Resolved per row and consumed only when the row is
                    // actually created: an existing one keeps the order the
                    // module gave it.
                    'sort_order' => $this->lookupOrderManager->placeNew($modelClass),
                ],
            );
        }
    }

    /**
     * Marks $name as the default row of $modelClass (spec 0154, D-8) — but
     * ONLY while no row of that table carries the flag yet. That guard is
     * what keeps a re-run idempotent without a dedicated "did we already do
     * this" column: once an admin (or a prior run) has chosen a default,
     * `is_default = true` exists somewhere on the table and this becomes a
     * no-op, exactly like reshapeProtectedStatuses() leaves a rename alone.
     *
     * @param  class-string<TaskType>|class-string<TaskPriority>|class-string<TaskImportance>  $modelClass
     */
    private function markDefaultIfNoneSet(string $modelClass, string $name): void
    {
        if ($modelClass::query()->where('is_default', true)->exists()) {
            return;
        }

        $modelClass::query()->where('name', $name)->update(['is_default' => true]);
    }

    /**
     * Rewrites the three protected rows to the client's wording, matched by
     * `system_key` — the only handle the code may use (D-5). A row whose
     * name is no longer the bootstrap one was renamed from the module and is
     * left untouched, which is what keeps this step idempotent.
     */
    private function reshapeProtectedStatuses(): void
    {
        foreach (TaskTaxonomyCatalogue::PROTECTED_STATUSES as $systemKey => $target) {
            $status = TaskStatus::query()->where('system_key', $systemKey)->first();

            if ($status === null || $status->name !== $target['bootstrap_name']) {
                continue;
            }

            $status->update([
                'name' => $target['name'],
                'group' => $target['group'],
                'color' => $target['color'],
                'icon' => $target['icon'],
                'completion_percentage' => $target['completion_percentage'],
            ]);
        }
    }

    /**
     * The client's ordinary statuses. StatusOrderManager::placeNew() puts
     * each one after the last ordinary row and pushes the two closing rows
     * past it, so the sequence stays "opening, work, closing" however many
     * are added.
     */
    private function seedStatuses(): void
    {
        foreach (TaskTaxonomyCatalogue::STATUSES as [$name, $group, $color, $icon, $percentage]) {
            TaskStatus::query()->firstOrCreate(
                ['name' => $name],
                [
                    'group' => $group,
                    'color' => $color,
                    'icon' => $icon,
                    'completion_percentage' => $percentage,
                    'sort_order' => $this->statusOrderManager->placeNew(TaskStatus::class),
                ],
            );
        }
    }
}
