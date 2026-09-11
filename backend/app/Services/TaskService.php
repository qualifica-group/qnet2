<?php

declare(strict_types=1);

namespace App\Services;

use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\DataObjects\Tasks\CreateTaskData;
use App\DataObjects\Tasks\UpdateTaskData;
use App\Models\Task;
use App\Models\User;
use App\Services\Tasks\TaskClosureFeedbackGuard;
use App\Services\Tasks\TaskHierarchyGuard;
use App\Services\Tasks\TaskVisibilityScope;
use App\Services\Tasks\TaskWriteLock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Business logic for the `tasks` resource (spec 0101). The controller stays
 * thin; this Service is the single authority over the write-time rules that
 * a FormRequest structurally cannot enforce. Three of them are evaluated on
 * the RESULTING record rather than on the submitted payload: the sub-task
 * hierarchy (D-12), the referent/anagrafica coherence (AC-014) and the
 * closing feedback (D-7). A fourth, the structural write lock (spec 0116
 * D-7), is the mirror case: it is evaluated on the SUBMITTED keys against
 * the Task's CURRENT persisted state, ahead of fill() — a task_status_id
 * sent in the same PATCH that would move the Task out of a frozen phase must
 * not smuggle a structural field past the lock that was in force when the
 * request arrived. All four run INSIDE the write transaction, so a refusal
 * leaves the Task exactly as it was (AC-032).
 *
 * `creator_id` is set here from the authenticated actor and nowhere else
 * (D-10): it is absent from Task's #[Fillable], so no payload can reach it.
 *
 * delete() carries the sub-task guard (D-8a) and the structural write lock's
 * assertDeletable() (spec 0116 AC-033); TasksTableDefinition overrides
 * deleteModel() to route the generic bulk-delete through this same method,
 * so neither guard can be side-stepped (AC-016).
 */
class TaskService
{
    private const string REFERENT_REGISTRY_PIVOT = 'referent_registry';

    /**
     * Relations eager-loaded for the detail read tree (TaskResource), so a
     * single request never N+1s. `subtasks` is loaded SCOPED (see
     * subtaskEagerLoad()); every other relation is a plain link.
     *
     * @var array<int, string>
     */
    private const array DETAIL_RELATIONS = [
        'taskStatus',
        'taskType',
        'taskCategory',
        'taskPriority',
        'taskImportance',
        'registry',
        'referent',
        'opportunity',
        'workOrder',
        'requester',
        'creator',
        'parentTask',
        'assignees',
        'watchers',
    ];

    public function __construct(
        private readonly TaskClosureFeedbackGuard $closureFeedbackGuard,
        private readonly TaskHierarchyGuard $hierarchyGuard,
    ) {}

    public function loadDetail(Task $task): Task
    {
        return $task->load([...self::DETAIL_RELATIONS, ...$this->subtaskEagerLoad()]);
    }

    /**
     * Create a Task. `creator_id` comes from $creator, never from the
     * payload (D-10, AC-010). The two user pivots are synced inside the same
     * transaction as the insert, so a guard refusal rolls both back.
     */
    public function create(CreateTaskData $data, User $creator): Task
    {
        $task = DB::transaction(function () use ($data, $creator): Task {
            // Step 1: the payload-level coherence rules (a brand-new row has
            // no id yet, so no cycle is expressible here — D-12).
            $this->assertReferentBelongsToRegistry($data->registryId, $data->referentId);

            // Step 2: build the row, with the creator taken from the actor.
            $task = new Task($data->attributes());
            $task->creator_id = $creator->id;

            // Step 3: the closing-feedback rule, on the resulting state (D-7).
            $this->closureFeedbackGuard->assertSatisfied($task);
            $task->save();

            // Step 4: assegnatari/osservatori (D-1), same transaction.
            $task->assignees()->sync($data->assigneeIds);
            $task->watchers()->sync($data->watcherIds);

            return $task;
        });

        return $this->loadDetail($task);
    }

    /**
     * Update a Task. Only the submitted keys are touched (partial PATCH).
     * The structural write lock (spec 0116 D-7) is asserted FIRST, against
     * the Task as it stood BEFORE this PATCH touches it — otherwise a
     * `task_status_id` submitted in the same request could unfreeze the
     * phase in memory and let a structural field ride along (AC-031/AC-032).
     * The other three guards run against the model's RESULTING state, so a
     * PATCH that submits only `task_status_id` is still judged against the
     * persisted flag/feedback/parent (AC-035 of spec 0101).
     */
    public function update(Task $task, UpdateTaskData $data): Task
    {
        DB::transaction(function () use ($task, $data): void {
            TaskWriteLock::assertStructuralWriteAllowed($task, $this->submittedKeys($data));

            $task->fill($data->submittedAttributes());

            $this->hierarchyGuard->assertAcyclic($task->id, $task->parent_task_id);
            $this->assertReferentBelongsToRegistry($task->registry_id, $task->referent_id);
            $this->closureFeedbackGuard->assertSatisfied($task);
            $task->save();

            // Full-replace only when the key was actually submitted
            // (AC-012): an untouched relation must not trigger a no-op sync.
            if ($data->hasAssigneeIds()) {
                $task->assignees()->sync($data->assigneeIds ?? []);
                $task->unsetRelation('assignees');
            }

            if ($data->hasWatcherIds()) {
                $task->watchers()->sync($data->watcherIds ?? []);
                $task->unsetRelation('watchers');
            }
        });

        return $this->loadDetail($task);
    }

    /**
     * Delete the Task. D-8a: a Task with sub-tasks is refused with a 409 —
     * and the child count deliberately IGNORES the visibility scope (AC-017),
     * since the correctness of the constraint cannot depend on who is
     * looking. `task_assignee`/`task_watcher` rows cascade away via their own
     * FKs; `parent_task_id` is restrictOnDelete, so even a delete that
     * side-stepped this Service would fail at the database.
     */
    public function delete(Task $task): void
    {
        if ($task->subtasks()->exists()) {
            abort(409, 'This task has sub-tasks and cannot be deleted.');
        }

        TaskWriteLock::assertDeletable($task);

        $task->delete();
    }

    /**
     * Minimal, searchable, paginated Task list for the for-select standard
     * (ADR 0011), mirroring ReferentService::forSelect. The rows are
     * restricted by the visibility scope like every other read (D-9); the
     * endpoint itself carries no resource permission gate.
     *
     * $excludeId (data_contract) drops one id from the list — the Task's own
     * id in the parent picker, so a Task is never offered as its own parent
     * (AC-082).
     */
    public function forSelect(ForSelectQuery $query, ?int $excludeId = null): ForSelectResult
    {
        $base = $this->forSelectBase();

        if ($excludeId !== null) {
            $base->whereKeyNot($excludeId);
        }

        if ($query->hasSearch()) {
            $base->where('tasks.title', 'like', '%'.$query->search.'%');
        }

        $total = (clone $base)->count();

        /** @var Collection<int, Task> $page */
        $page = $base->orderBy('tasks.title')
            ->orderBy('tasks.id')
            ->offset($query->offset)
            ->limit($query->limit)
            ->get();

        return new ForSelectResult(
            items: $this->appendHydratedIds($page, $query),
            total: $total,
            offset: $query->offset,
            limit: $query->limit,
        );
    }

    /**
     * The scoped, minimally-projected for-select base query. `taskStatus` is
     * eager-loaded because TaskForSelectResource exposes the status name as
     * the item subtitle.
     *
     * @return Builder<Task>
     */
    private function forSelectBase(): Builder
    {
        return TaskVisibilityScope::scopeToActor(
            Task::query()->select(['tasks.id', 'tasks.title', 'tasks.task_status_id'])->with('taskStatus'),
            Auth::user(),
        );
    }

    /**
     * Append the explicitly-requested `ids[]` (edit-mode hydration) that are
     * not already on the page, deduplicated. They bypass search — but NOT the
     * visibility scope, which is a security boundary, not a filter. Total is
     * unaffected.
     *
     * @param  Collection<int, Task>  $page
     * @return Collection<int, Task>
     */
    private function appendHydratedIds(Collection $page, ForSelectQuery $query): Collection
    {
        if (! $query->hasIds()) {
            return $page;
        }

        $missingIds = array_values(array_diff($query->ids, $page->pluck('id')->all()));

        if ($missingIds === []) {
            return $page;
        }

        return $page->concat($this->forSelectBase()->whereKey($missingIds)->get());
    }

    /**
     * The scoped `subtasks` eager load (D-12, AC-066): the detail lists only
     * the children the actor is allowed to see, while delete()'s own guard
     * counts every child regardless.
     *
     * @return array<string, callable>
     */
    private function subtaskEagerLoad(): array
    {
        $actor = Auth::user();

        return [
            'subtasks' => static function (HasMany $subtasks) use ($actor): void {
                TaskVisibilityScope::scopeToActor($subtasks->getQuery(), $actor)
                    ->with(['taskStatus', 'assignees']);
            },
        ];
    }

    /**
     * The column keys the client actually submitted on this PATCH, plus
     * `assignee_ids`/`watcher_ids` when their own key was present — the two
     * pivots are structural (D-5) but never travel through
     * submittedAttributes(), which only carries `tasks` columns.
     *
     * @return array<int, string>
     */
    private function submittedKeys(UpdateTaskData $data): array
    {
        $keys = array_keys($data->submittedAttributes());

        if ($data->hasAssigneeIds()) {
            $keys[] = 'assignee_ids';
        }

        if ($data->hasWatcherIds()) {
            $keys[] = 'watcher_ids';
        }

        return $keys;
    }

    /**
     * AC-014: a Referente must belong, through the `referent_registry` pivot,
     * to the Anagrafica the Task points at. Evaluated on the RESULTING pair,
     * so a PATCH that moves either side alone is still checked.
     *
     * A referente WITHOUT an anagrafica is refused by the same rule: the
     * association is what makes a referente meaningful on a Task, and the
     * form disables the field until an anagrafica is picked (AC-080).
     *
     * @throws ValidationException 422 on `referent_id`
     */
    private function assertReferentBelongsToRegistry(?int $registryId, ?int $referentId): void
    {
        if ($referentId === null) {
            return;
        }

        $belongs = $registryId !== null && DB::table(self::REFERENT_REGISTRY_PIVOT)
            ->where('referent_id', $referentId)
            ->where('registry_id', $registryId)
            ->exists();

        if (! $belongs) {
            throw ValidationException::withMessages([
                'referent_id' => ['The selected referent does not belong to the selected registry.'],
            ]);
        }
    }
}
