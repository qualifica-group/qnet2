<?php

declare(strict_types=1);

namespace App\Services;

use App\DataObjects\Tasks\CreateTaskData;
use App\DataObjects\Tasks\UpdateTaskData;
use App\Models\Task;
use App\Models\User;
use App\Services\Notifications\TaskNotifier;
use App\Services\Tasks\TaskAbilityResolver;
use App\Services\Tasks\TaskActionOnlyStatusGuard;
use App\Services\Tasks\TaskClosureFeedbackGuard;
use App\Services\Tasks\TaskCreationCompletion;
use App\Services\Tasks\TaskDefaultLookupResolver;
use App\Services\Tasks\TaskDeleteCascade;
use App\Services\Tasks\TaskDescriptionWriter;
use App\Services\Tasks\TaskEvidenceWriter;
use App\Services\Tasks\TaskHierarchyGuard;
use App\Services\Tasks\TaskInitialStatusResolver;
use App\Services\Tasks\TaskManualStatusGuard;
use App\Services\Tasks\TaskParentAccessGuard;
use App\Services\Tasks\TaskParentDateRangeGuard;
use App\Services\Tasks\TaskPivotDelta;
use App\Services\Tasks\TaskRecordLinkCoherence;
use App\Services\Tasks\TaskRecurrenceService;
use App\Services\Tasks\TaskStageGuard;
use App\Services\Tasks\TaskValidationRequirementGuard;
use App\Services\Tasks\TaskVisibilityScope;
use App\Services\Tasks\TaskWatcherOverlapGuard;
use App\Services\Tasks\TaskWriteLock;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Business logic for the `tasks` resource (spec 0101).
 *
 * SIZE (engineering.md §6): the for-select read path lives in
 * App\Services\Tasks\TaskForSelectService, the record-link coherence rules in
 * App\Services\Tasks\TaskRecordLinkCoherence, the born-completed write in
 * App\Services\Tasks\TaskCreationCompletion — this class holds the write
 * PATHS alone, calling every guard inside the SAME transaction as the write
 * it protects (AC-032): a further split goes by write path, never by moving
 * a guard call out of its transaction.
 *
 * The controller stays thin; this Service is the single authority over the
 * write-time rules a FormRequest structurally cannot enforce, ALL evaluated
 * on the RESULTING record and ALL inside the write transaction, so a refusal
 * leaves the Task exactly as it was (AC-032): the sub-task hierarchy (D-12),
 * the record-link coherence (referent/registry AC-014, lead/registry spec
 * 0154 D-4, commessa/opportunity exclusivity spec 0154 D-11), the closing
 * feedback (D-7), the watcher/creator/requester/assignee overlap (spec 0118
 * D-9), the validation-requirement bypass (spec 0121 D-5) and the parent
 * date range (spec 0123 D-7/D-8). The structural write lock (spec 0116 D-7)
 * is the mirror case: evaluated on the SUBMITTED keys against the Task's
 * CURRENT persisted state, ahead of fill().
 *
 * `creator_id` is set here from the actor and nowhere else (D-10): absent
 * from Task's #[Fillable]. `task_status_id` is derived on create by
 * `TaskInitialStatusResolver` (spec 0118 D-4, spec 0153 D-4, spec 0154 D-10
 * for the manual override) — update() never re-derives it. `task_type_id`/
 * `task_priority_id`/`task_importance_id` fall back to `is_default` rows
 * when omitted (spec 0154, D-8), read-only through TaskDefaultLookupResolver.
 * `is_completed: true` on create hands the just-built Task to
 * TaskCreationCompletion (spec 0154, D-6) BEFORE assignees/watchers are
 * notified, so its own D-7 gate can choose between the ordinary voce 7/8
 * pair and the closure notification.
 *
 * delete() carries the sub-task guard (D-8a) and the structural write lock's
 * assertDeletable() (spec 0116 AC-033); TasksTableDefinition overrides
 * deleteModel() to route the generic bulk-delete through this same method.
 */
class TaskService
{
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
        'workOrderStage',
        'requester',
        'creator',
        'parentTask',
        'assignees',
        'watchers',
        'recurrence',
        'lead.registry',
    ];

    public function __construct(
        private readonly TaskActionOnlyStatusGuard $actionOnlyStatusGuard,
        private readonly TaskClosureFeedbackGuard $closureFeedbackGuard,
        private readonly TaskCreationCompletion $creationCompletion,
        private readonly TaskDefaultLookupResolver $defaultLookups,
        private readonly TaskDescriptionWriter $descriptionWriter,
        private readonly TaskEvidenceWriter $evidenceWriter,
        private readonly TaskHierarchyGuard $hierarchyGuard,
        private readonly TaskInitialStatusResolver $initialStatusResolver,
        private readonly TaskNotifier $notifier,
        private readonly TaskParentAccessGuard $parentAccessGuard,
        private readonly TaskParentDateRangeGuard $parentDateRangeGuard,
        private readonly TaskPivotDelta $pivotDelta,
        private readonly TaskRecordLinkCoherence $recordLinkCoherence,
        private readonly TaskRecurrenceService $recurrenceService,
        private readonly TaskStageGuard $stageGuard,
        private readonly TaskValidationRequirementGuard $validationRequirementGuard,
        private readonly TaskWatcherOverlapGuard $watcherOverlapGuard,
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
            // Step 1: record-link coherence (a brand-new row has no id yet,
            // so no cycle is expressible here — D-12), resolving `registry_id`
            // from the commessa when it was left blank (spec 0154, D-11).
            $registryId = $this->recordLinkCoherence->resolveRegistryId(
                $data->registryId,
                $data->referentId,
                $data->leadId,
                $data->workOrderId,
                $data->opportunityId,
            );

            // Step 2: build the row, creator/registry from Step 1/the actor,
            // the three lookups defaulted when omitted (spec 0154, D-8).
            $task = new Task($data->attributes());
            $task->registry_id = $registryId;
            $task->creator_id = $creator->id;
            $task->task_type_id ??= $this->defaultLookups->taskTypeId();
            $task->task_priority_id ??= $this->defaultLookups->taskPriorityId();
            $task->task_importance_id ??= $this->defaultLookups->taskImportanceId();

            // Step 2a: the actor must see and be able to edit the parent
            // (spec 0125, D-4), ahead of every guard that reads the parent.
            $this->parentAccessGuard->assertMayAttach($task->parent_task_id, $creator);

            // Step 2b: write-lock cascade (spec 0123, D-9) — a Task cannot be
            // born under a frozen parent or ancestor.
            TaskWriteLock::assertParentChainUnlocked($task);

            // Step 2c: date-range coherence with the parent (spec 0123, D-7).
            $this->parentDateRangeGuard->assertChildWithinParent($task);

            // Step 2d: the task board "Fase" rule (spec 0146, D-3/AC-015).
            $this->stageGuard->applyOnCreate($task);

            // Step 3: the initial status — manually chosen and re-derived
            // when it names `open`/`assigned` (spec 0154, D-10), otherwise
            // fully derived (spec 0118 D-3/D-4, spec 0153 D-4).
            $task->task_status_id = $this->initialStatusResolver->resolveForCreate(
                $data->taskStatusId,
                $data->assigneeIds,
                $creator->id,
                $data->requesterId,
            );

            // Step 4: the closing-feedback rule, on the resulting state (D-7).
            $this->closureFeedbackGuard->assertSatisfied($task);

            // Step 5: watcher overlap rule (spec 0118 D-9). On create there is
            // no persisted state to fall back to: the resulting sets ARE the
            // submitted ones, plus the creator taken from the actor.
            $this->watcherOverlapGuard->assertNoOverlap(
                $creator->id,
                $data->requesterId,
                $data->assigneeIds,
                $data->watcherIds,
            );

            // Step 5b: recurrence (spec 0120 D-3/D-4), set on the in-memory
            // row so the FK is persisted by the INSERT below.
            if ($data->recurrence !== null) {
                $this->recurrenceService->set($task, $data->recurrence);
            }

            $task->save();

            // Step 5c: rich text description/evidence (spec 0128 D-2/D-3;
            // spec 0154 D-3) — only once the Task has an id, since an inline
            // description image becomes one of ITS OWN attachments. A
            // second, small UPDATE only when either sanitized value actually
            // differs from the null default.
            $this->descriptionWriter->applyOnCreate($task, $data->description, $creator);
            $this->evidenceWriter->apply($task, $data->evidence);

            if ($task->isDirty(['description', 'evidence'])) {
                $task->save();
            }

            // Step 6: assegnatari/osservatori (D-1), same transaction.
            $task->assignees()->sync($data->assigneeIds);
            $task->watchers()->sync($data->watcherIds);

            // Step 7: born already completed (spec 0154, D-6) overrides the
            // birth above with the closed_positive one, BEFORE the
            // notification decision right below reads it.
            if ($data->isCompleted) {
                $this->creationCompletion->apply($task, $creator);
            }

            // Step 8: voci 7/8 of the notification map (spec 0119) UNLESS the
            // Task was born completed, which sends the closure notification
            // instead (D-6) — never both. Gated on `notify_assigned_users`
            // (spec 0154, D-7): false sends neither. Deferred to
            // DB::afterCommit() inside the notifier, so this rolls back with
            // the transaction (AC-012, AC-013).
            if ($data->notifyAssignedUsers) {
                if ($data->isCompleted) {
                    $this->creationCompletion->notifyClosure($task, $creator);
                } else {
                    $this->notifier->assigned($task, $creator, $data->assigneeIds);
                    $this->notifier->watching($task, $creator, $data->watcherIds);
                }
            }

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
     * The other guards run against the model's RESULTING state, so a PATCH
     * that submits only `task_status_id` is still judged against the
     * persisted flag/feedback/parent (AC-035 of spec 0101). The watcher
     * overlap rule (spec 0118 D-9) is the same idea applied to the two user
     * pivots: `assignee_ids`/`watcher_ids` are read from the payload when
     * submitted, from the persisted pivot otherwise (AC-033) — `requester_id`
     * needs no such fallback because it is a plain column already merged by
     * fill() by the time the guard runs.
     *
     * $actor is carried only for the notification map (spec 0119 D-3/D-9):
     * the people ADDED to either pivot are told, the actor never is.
     */
    public function update(Task $task, UpdateTaskData $data, User $actor): Task
    {
        DB::transaction(function () use ($task, $data, $actor): void {
            TaskWriteLock::assertStructuralWriteAllowed($task, $this->submittedKeys($data), $actor);

            // Spec 0126, D-4b/D-4c: whether task_status_id may change AT ALL,
            // read off the Task exactly as it stood pre-fill (current phase,
            // current is_blocked) — the resulting-target check (D-4a) stays
            // TaskActionOnlyStatusGuard's job, below, after fill().
            TaskManualStatusGuard::assertAllowed($task, $data->taskStatusId);

            $task->fill($data->submittedAttributes());

            // Rich text description/evidence (spec 0128 D-2/D-3/D-4; spec
            // 0154 D-3), each only when its own key was actually submitted —
            // an untouched one is never re-sanitized.
            if ($data->descriptionSubmitted) {
                $this->descriptionWriter->applyOnUpdate($task, $data->description, $actor);
            }

            if ($data->evidenceSubmitted) {
                $this->evidenceWriter->apply($task, $data->evidence);
            }

            // States reserved to the domain actions (spec 0123, D-4): checked
            // first and unconditionally on the actor, ahead of every other
            // resulting-state guard, since D-4 carries no exemption at all.
            $this->actionOnlyStatusGuard->assertReachableByPatch($task);

            // Spec 0125, D-4: only a parent that actually CHANGES is judged,
            // so an untouched one is never re-evaluated and detaching (null)
            // is always allowed.
            if ($task->isDirty('parent_task_id')) {
                $this->parentAccessGuard->assertMayAttach($task->parent_task_id, $actor);
            }

            $this->hierarchyGuard->assertAcyclic($task->id, $task->parent_task_id);

            // Write-lock cascade (spec 0123, D-9): moving a Task under a
            // frozen parent/ancestor is refused, gated on the key actually
            // submitted so an untouched parent_task_id is never re-judged —
            // a Task ALREADY under a frozen chain is caught instead by
            // assertStructuralWriteAllowed above, on every structural field.
            if ($data->parentTaskIdSubmitted) {
                TaskWriteLock::assertParentChainUnlocked($task);
            }

            // Date-range coherence with the parent (spec 0123, D-7/D-8), both
            // on the RESULTING state. The child side is gated on the keys
            // actually submitted, so a PATCH untouched on all three never
            // re-judges a row that predates the rule (D-7); the parent side
            // is unconditional here because it self-gates on dirty dates.
            if ($data->parentTaskIdSubmitted || $data->startDateSubmitted || $data->endDateSubmitted) {
                $this->parentDateRangeGuard->assertChildWithinParent($task);
            }

            $this->parentDateRangeGuard->assertChildrenWithinRange($task);

            // The task board "Fase" rule (spec 0146, D-3/AC-015/AC-016) —
            // gated on the key actually submitted, so an untouched
            // work_order_stage_id is judged as INHERITED (silently detached
            // if no longer coherent) rather than as a fresh client choice.
            $this->stageGuard->applyOnUpdate($task, $data->workOrderStageIdSubmitted);

            // Record-link coherence (referent/registry AC-014, lead/registry
            // spec 0154 D-4, commessa/opportunity spec 0154 D-11), on the
            // RESULTING state, unconditional — a pair that predates the rule
            // self-heals rather than silently drifting.
            $task->registry_id = $this->recordLinkCoherence->resolveRegistryId(
                $task->registry_id,
                $task->referent_id,
                $task->lead_id,
                $task->work_order_id,
                $task->opportunity_id,
            );

            $this->closureFeedbackGuard->assertSatisfied($task);
            $this->validationRequirementGuard->assertClosableBy($task, $actor);
            $this->watcherOverlapGuard->assertNoOverlap(
                $task->creator_id,
                $task->requester_id,
                $data->hasAssigneeIds() ? ($data->assigneeIds ?? []) : $this->pivotDelta->persistedIds($task, 'assignees'),
                $data->hasWatcherIds() ? ($data->watcherIds ?? []) : $this->pivotDelta->persistedIds($task, 'watchers'),
            );

            // Recurrence (spec 0120 D-10/D-12/D-13): three-way on the
            // SUBMITTED key alone — absent leaves the series as-is, null
            // cancels it, an object creates or replaces it with the D-10/D-11
            // regeneration. Evaluated against $task's RESULTING end_date
            // (fill() already ran above), before save() persists the FK.
            if ($data->hasRecurrence()) {
                $data->recurrence === null
                    ? $this->recurrenceService->cancel($task)
                    : $this->recurrenceService->replace($task, $data->recurrence);
            }

            $task->save();

            // Who this PATCH ADDS to each pivot, read BEFORE the sync: once
            // the sync has run the persisted pivot IS the submitted one and
            // every delta reads empty (spec 0119 D-9).
            $addedAssigneeIds = $this->pivotDelta->addedIds($task, 'assignees', $data->assigneeIds);
            $addedWatcherIds = $this->pivotDelta->addedIds($task, 'watchers', $data->watcherIds);

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

            // Only the newcomers hear about it (D-9): an empty delta sends
            // nothing, so a PATCH that removes members or touches neither
            // pivot stays silent (AC-015, AC-017). Gated on
            // `notify_new_assigned_users` (spec 0154, D-7): false suppresses
            // both for this PATCH.
            if ($data->notifyNewAssignedUsers) {
                $this->notifier->assigned($task, $actor, $addedAssigneeIds);
                $this->notifier->watching($task, $actor, $addedWatcherIds);
            }
        });

        return $this->loadDetail($task);
    }

    /**
     * Delete the Task and its WHOLE sub-tree, in ONE transaction (D-5, spec
     * 0153, REQUIREMENT CHANGED): TaskDeleteCascade collects every
     * descendant and checks each is itself deletable by $actor, or nothing
     * is deleted at all — a 422 naming the first offending descendant, never
     * the old 409-for-sub-tasks.
     *
     * Spec 0125 D-1: the delete row of the matrix is re-asserted here, past
     * Gate::before (D-8, spec 0153: super-admin does NOT bypass delete). The
     * write-lock ANCESTOR cascade (spec 0123, D-9) still applies unchanged:
     * canDelete() already rules out $task's OWN frozen/blocked state, so
     * assertDeletable() below only ever fires for a locked ancestor further
     * up the chain (409).
     */
    public function delete(Task $task, User $actor): void
    {
        abort_unless(
            TaskAbilityResolver::canDelete($actor, $task),
            403,
            'Only the creator, the requester, an assignee or a manager may delete this task, and only while it is open and unblocked.',
        );

        TaskWriteLock::assertDeletable($task);

        DB::transaction(function () use ($task, $actor): void {
            $descendants = TaskDeleteCascade::collectDescendants($task);

            foreach ($descendants as $descendant) {
                TaskDeleteCascade::assertDescendantDeletable($descendant, $actor);
            }

            foreach (array_reverse($descendants) as $descendant) {
                $descendant->delete();
            }

            $task->delete();
        });
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
     * `description`/`evidence`/`assignee_ids`/`watcher_ids`/`recurrence` when
     * their own key was present — none of the five travel through
     * submittedAttributes(), which only carries plain mass-assignable
     * `tasks` columns, yet all five are structural (D-5; spec 0120 D-13 for
     * `recurrence`; spec 0128/spec 0154 for `description`/`evidence`, which
     * their own writer sets directly rather than through fill()).
     *
     * @return array<int, string>
     */
    private function submittedKeys(UpdateTaskData $data): array
    {
        $keys = array_keys($data->submittedAttributes());

        if ($data->descriptionSubmitted) {
            $keys[] = 'description';
        }

        if ($data->evidenceSubmitted) {
            $keys[] = 'evidence';
        }

        if ($data->hasAssigneeIds()) {
            $keys[] = 'assignee_ids';
        }

        if ($data->hasWatcherIds()) {
            $keys[] = 'watcher_ids';
        }

        if ($data->hasRecurrence()) {
            $keys[] = 'recurrence';
        }

        return $keys;
    }
}
