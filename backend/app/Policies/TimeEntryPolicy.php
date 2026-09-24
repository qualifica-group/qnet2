<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Policies\Abstracts\BasePolicy;
use App\Services\Tasks\TaskAbilityResolver;
use Illuminate\Database\Eloquent\Model;

/**
 * CRUD policy for the `time-entries` resource (spec 0122, D-8): a segnatempo
 * is readable/writable by its OWNER, or by anyone holding `manageAll` ("Admin"
 * of the document — reads and writes for any user). `viewAny` is the plain
 * BasePolicy permission check: the record-level ownership rule narrows
 * `view`/`update`/`delete` only, exactly as `TaskPolicy`/`WorkOrderPolicy` do
 * for their own scoping rule.
 *
 * `abilities()` restricts BasePolicy's default eight down to the six that
 * make sense for this resource (no `import`, no `viewActivity` — neither is
 * used by this module) plus the three extras D-8 lists: `exportMonthly`,
 * `manageAll`, `viewAll` (the "responsabile"/"vista team" read-only widenings,
 * enforced by the stats/team query itself, not by a Policy ability — nothing
 * here needs a dedicated gate for the "responsabile legge i sottoposti" rule,
 * since it targets a `user_id`, not a TimeEntry record).
 */
class TimeEntryPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'time-entries';
    }

    public function view(User $user, Model $model): bool
    {
        return parent::view($user, $model) && $this->isOwnedOrManaged($user, $model);
    }

    public function update(User $user, Model $model): bool
    {
        return parent::update($user, $model) && $this->isAuthorizedToWrite($user, $model);
    }

    public function delete(User $user, Model $model): bool
    {
        return parent::delete($user, $model) && $this->isAuthorizedToWrite($user, $model);
    }

    /**
     * The monthly export ("Creatore NO, Admin SI'", D-8): a separate ability
     * from `export` (the filtered export), since a role may hold one without
     * the other.
     */
    public function exportMonthly(User $user): bool
    {
        return $user->can($this->permission('exportMonthly'));
    }

    /**
     * Resource-level: reads and writes any user's segnatempo, and enables the
     * Utente filter (D-8). Consulted per-record by `isOwnedOrManaged()` above,
     * and directly by the endpoints implementing rule R (data_contract) for
     * the `user_id` they are asked to act on.
     */
    public function manageAll(User $user): bool
    {
        return $user->can($this->permission('manageAll'));
    }

    /**
     * Resource-level: the vista team shows every active user ("Struttura
     * completa", D-10), not just the actor's own descendants.
     */
    public function viewAll(User $user): bool
    {
        return $user->can($this->permission('viewAll'));
    }

    /**
     * @return array<int, string>
     */
    public static function abilities(): array
    {
        return ['viewAny', 'view', 'create', 'update', 'delete', 'export', 'exportMonthly', 'manageAll', 'viewAll'];
    }

    private function isOwnedOrManaged(User $user, Model $model): bool
    {
        return ! $model instanceof TimeEntry
            || $model->user_id === $user->id
            || $user->can($this->permission('manageAll'));
    }

    /**
     * Spec 0153, D-9 (REQUIREMENT CHANGED): a segnatempo filed under a Task
     * follows the Task's OWN `canUpdate()` row instead of the plain
     * ownership/`manageAll` rule above — `time-entries.manageAll` does NOT
     * reach into a Task's segnatempo, and ownership of the entry no longer
     * matters: whoever may update the Task manages ANY of its segnatempo. A
     * segnatempo with no Task keeps `isOwnedOrManaged()`, unchanged.
     */
    private function isAuthorizedToWrite(User $user, Model $model): bool
    {
        if (! $model instanceof TimeEntry || $model->task_id === null) {
            return $this->isOwnedOrManaged($user, $model);
        }

        $task = $this->loadTask($model);

        return $task !== null && TaskAbilityResolver::canManageTimeEntry($user, $task);
    }

    /**
     * Reads the `task` relation ONLY if already eager-loaded by the caller
     * (the Task-scoped list eager-loads it); otherwise a plain query by
     * `task_id` — never the `task` relation accessor itself, which would
     * lazy-load under `Model::preventLazyLoading()` (backend.md §3): `update`/
     * `destroy` authorize BEFORE `TimeEntryService::loadDetail()` ever
     * eager-loads it.
     */
    private function loadTask(TimeEntry $entry): ?Task
    {
        return $entry->relationLoaded('task') ? $entry->getRelation('task') : Task::query()->find($entry->task_id);
    }
}
