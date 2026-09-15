<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\Task;
use App\Models\User;
use App\Services\Tasks\TaskAbilityResolver;
use App\Services\Tasks\TaskActionAvailability;
use App\Services\Tasks\TaskManualStatusGuard;
use App\Services\Tasks\TaskWriteLock;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `tasks` resource (spec 0101, delta spec
 * 0116).
 *
 * The field catalogue is the FROZEN order of the data_contract's own POST
 * payload (AC-053), and three keys are deliberately ABSENT from it:
 *  - `creator_id`, server-owned and immutable (D-10);
 *  - `completion_percentage`, derived from the status and never written
 *    (D-6);
 *  - `is_blocked` (spec 0116, D-6): written ONLY by the `block`/`unblock`
 *    domain actions below, never by this PATCH — exactly like
 *    `ContractsAuthorization` keeps `validated_at`/`terminated_at` off a
 *    PATCH. Leaving it permissionable would let an actor without
 *    `tasks.block` flip it through the generic update endpoint.
 * A field that is not client-writable is not permissionable either: leaving
 * them out is what makes "not submittable" and "not configurable" the same
 * statement, instead of two that could drift. All three are additionally
 * `prohibited` at the FormRequest layer, ahead of and independent from this
 * ceiling — the privileged role bypasses every ceiling, so immutability
 * cannot be expressed here alone.
 *
 * `start_time`/`end_time` are declared `text` (D-11): the shared
 * FieldDefinition catalogue has no `time` type, and adding one is out of
 * scope for this spec.
 *
 * Every field's ceiling is the plain visible+editable-when-may-write /
 * visible+readonly default, EXCEPT the 19 fields in
 * `TaskAbilityResolver::PROTECTED_FIELDS` (spec 0116, D-5; spec 0121, D-1
 * adds `requires_validation`; spec 0120, D-12 adds `recurrence`): those
 * additionally require the actor to own the Task's MANDATE
 * (`TaskAbilityResolver::canUpdateProtectedFields()`) once a record exists.
 * `recurrence` is additionally gated by `UpdateTaskRequest::authorize()`
 * with a genuine 403 rather than this ceiling's usual 422 (D-12, AC-028):
 * unlike every other protected field, submitting it without the mandate is
 * refused outright, not merely as a no-op change the actor cannot make.
 * In CREATE context (`$model === null`) that extra gate is skipped
 * on purpose — there is no record yet to hold a role on, and whoever creates
 * the Task becomes its creator, so every field simply follows
 * `actorMayWrite()` as it always did.
 */
class TasksAuthorization extends AbstractResourceAuthorization
{
    /**
     * The fields `required` somewhere in the data_contract, hence the only
     * `mandatory` ones here (spec 0008): `title`/`requester_id`/
     * `assignee_ids`/`end_date` are required on BOTH POST and PATCH (spec
     * 0118 D-1/D-2). `task_status_id` stays in this list purely to keep the
     * EDIT-context ceiling unchanged (it is still `sometimes|required` on
     * PATCH, D-6) — its CREATE-context behaviour diverges from what this
     * flat, context-free list can express, so that divergence is carved out
     * directly in `fieldPermissionCeiling()` below rather than here (spec
     * 0118 D-3, AC-016): `mandatory` still marks it "locked against the DB
     * matrix" for edit, even though a brand-new Task never sees it as
     * required at all.
     *
     * @var array<int, string>
     */
    private const array MANDATORY_FIELDS = ['title', 'task_status_id', 'requester_id', 'assignee_ids', 'end_date'];

    /**
     * The catalogue as `field key => form type`, in the frozen order
     * (AC-053).
     *
     * @var array<string, string>
     */
    private const array FIELD_TYPES = [
        'title' => 'text',
        'task_status_id' => 'select',
        'description' => 'richtext',
        'registry_id' => 'select',
        'referent_id' => 'select',
        'parent_task_id' => 'select',
        'task_type_id' => 'select',
        'task_priority_id' => 'select',
        'task_importance_id' => 'select',
        'task_category_id' => 'select',
        'opportunity_id' => 'select',
        'work_order_id' => 'select',
        'requester_id' => 'select',
        'start_date' => 'date',
        'end_date' => 'date',
        'completion_date' => 'date',
        'start_time' => 'text',
        'end_time' => 'text',
        'estimated_minutes' => 'number',
        'requires_closure_feedback' => 'boolean',
        'requires_validation' => 'boolean',
        'closure_feedback' => 'textarea',
        'assignee_ids' => 'multiselect',
        'watcher_ids' => 'multiselect',
        // spec 0120: no existing form type fits an object with its own
        // internal shape (frequency/interval/weekdays/...), so the field
        // carries a dedicated `recurrence` type the frontend renders with its
        // own `TaskRecurrenceSection` rather than a generic control.
        'recurrence' => 'recurrence',
    ];

    public function __construct(
        FieldPermissionRepository $fieldPermissionRepository,
        private readonly TaskActionAvailability $actionAvailability,
    ) {
        parent::__construct($fieldPermissionRepository);
    }

    public function resource(): string
    {
        return 'tasks';
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        $fields = [];

        foreach (self::FIELD_TYPES as $key => $type) {
            $fields[] = new FieldDefinition($key, $type, mandatory: in_array($key, self::MANDATORY_FIELDS, true));
        }

        return $fields;
    }

    /**
     * @return array<int, string>
     */
    public function actions(): array
    {
        return ['delete', 'export', 'import', 'view_activity', 'view_documents', 'complete', 'complete_to_validation', 'uncomplete', 'approve', 'reject', 'block', 'unblock', 'request_update', 'close_via_status', 'create_subtask', 'change_status'];
    }

    /**
     * `task_status_id` is the one field whose ceiling depends on CREATE vs
     * EDIT (spec 0118 D-3, AC-016): on create it is neither required nor
     * editable — it is not even submittable (`StoreTaskRequest` rejects it
     * with `prohibited`), so permissioning it would offer a control the
     * FormRequest immediately refuses. `FieldDefinition::$mandatory` has no
     * such context (it is the same flat catalogue for every actor and every
     * request), so the override lives here instead of in `MANDATORY_FIELDS`
     * — which stays as-is precisely so the EDIT ceiling (today's behaviour,
     * D-6) does not move.
     *
     * @return array<string, FieldPermission>
     */
    protected function fieldPermissionCeiling(User $actor, ?Model $model): array
    {
        $mayWrite = $this->actorMayWrite($actor, $model);
        $mayEditProtectedFields = $model === null
            || ($model instanceof Task && TaskAbilityResolver::canUpdateProtectedFields($actor, $model));

        $ceiling = [];

        foreach (self::FIELD_TYPES as $key => $type) {
            // spec 0127 D-2: written only by TaskCompletionService, so never editable.
            if (($key === 'task_status_id' && $model === null) || $key === 'completion_date') {
                $ceiling[$key] = FieldPermission::visibleReadonly(required: false);

                continue;
            }

            $required = in_array($key, self::MANDATORY_FIELDS, true);
            $isProtected = in_array($key, TaskAbilityResolver::PROTECTED_FIELDS, true);
            $editable = $mayWrite && (! $isProtected || $mayEditProtectedFields);

            $ceiling[$key] = $editable
                ? FieldPermission::visibleEditable(required: $required)
                : FieldPermission::visibleReadonly(required: $required);
        }

        return $ceiling;
    }

    /**
     * Every flag is the AND of three things (spec 0116, data_contract
     * "permissions.actions"): the ability, the record-role matrix
     * (`TaskAbilityResolver`) and the state's availability
     * (`TaskActionAvailability`). D-8's freeze veto is folded in directly:
     * a `is_blocked` Task admits none of the four status-changing actions,
     * regardless of what the matrix or the phase would otherwise allow —
     * `block`/`unblock` already carry that condition through
     * `isBlockable()`/`isUnblockable()`. `complete_to_validation` (spec 0121,
     * D-6) is the one exception to the three-way AND: it is `complete`
     * itself ANDed with `completionRequiresValidation()` alone, never a
     * fourth independent evaluation of the guards `complete` already ran.
     * `complete`/`approve` carry a further veto (spec 0123, D-6): a Task with
     * an open DIRECT sub-task admits neither, so `complete_to_validation`
     * inherits it too through `complete`. `close_via_status` (D-5),
     * `create_subtask` (D-9) and `change_status` (spec 0126, D-4) are three
     * MORE exceptions to the three-way AND: none reads `TaskActionAvailability`
     * at all — the first two are PATCH-reachability questions
     * (`TaskAbilityResolver`/`TaskManualStatusGuard` plus the closing-feedback
     * rule), the third a write-lock-cascade one (`TaskWriteLock`).
     * `request_update` is the one domain action the `is_blocked` veto no
     * longer reaches (spec 0126, D-6, REQUIREMENT CHANGED): `close_via_status`
     * and `change_status`, by contrast, ARE now vetoed by `is_blocked` — the
     * D-7 carve-out of spec 0116 that used to exempt a manual status change
     * from the freeze is gone.
     *
     * @return array<string, bool>
     */
    public function actionPermissions(User $actor, ?Model $model): array
    {
        $task = $model instanceof Task ? $model : null;

        // D-6 (spec 0123): a Task with an open DIRECT sub-task admits none
        // of the three flags below, regardless of what the ability/matrix/
        // phase would otherwise allow.
        $hasOpenSubtasks = $task !== null && $this->actionAvailability->hasOpenSubtasks($task);

        $canComplete = $task !== null && ! $task->is_blocked && ! $hasOpenSubtasks
            && $this->actionAvailability->isCompletable($task)
            && $actor->can('tasks.complete') && TaskAbilityResolver::canComplete($actor, $task);

        return [
            // spec 0125, D-2: the matrix row, not the ability alone, so the
            // flag agrees with TaskService::delete()'s own re-assertion.
            'delete' => $task !== null && $actor->can('tasks.delete') && TaskAbilityResolver::canDelete($actor, $task),
            'export' => $actor->can('tasks.export'),
            'import' => $actor->can('tasks.import'),
            // Gates the ActivityLogSection in the detail (spec 0034); the
            // record-level `tasks.view` boundary is enforced separately by
            // GET /api/activity-log/tasks/{id}.
            'view_activity' => $model !== null && $actor->can('tasks.viewActivity'),
            // Gates the documents tab in the detail (reused polymorphic
            // Attachment subsystem, spec 0117 D-8). Unlike every flag below
            // it, this one ANDs nothing else: the attachment endpoints carry
            // no per-record boundary, so neither the record-role matrix nor
            // TaskVisibilityScope narrows it -- the same exposure the
            // Opportunita' documents section already has, accepted by the
            // user rather than inherited by accident.
            'view_documents' => $model !== null && $actor->can('tasks.viewDocuments'),
            'complete' => $canComplete,
            // spec 0121, D-6: the ONLY way the frontend learns which pop-up
            // to show without recomputing the matrix itself. Derived from
            // the SAME `complete` flag above (never a second evaluation of
            // its guards) ANDed with the one rule that decides the percorso
            // (TaskAbilityResolver::completionRequiresValidation()).
            'complete_to_validation' => $canComplete && TaskAbilityResolver::completionRequiresValidation($actor, $task),
            'uncomplete' => $task !== null && ! $task->is_blocked && $this->actionAvailability->isUncompletable($task)
                && $actor->can('tasks.complete') && TaskAbilityResolver::canComplete($actor, $task),
            'approve' => $task !== null && ! $task->is_blocked && ! $hasOpenSubtasks
                && $this->actionAvailability->isValidatable($task)
                && $actor->can('tasks.validate') && TaskAbilityResolver::canValidate($actor, $task),
            'reject' => $task !== null && ! $task->is_blocked && $this->actionAvailability->isValidatable($task)
                && $actor->can('tasks.validate') && TaskAbilityResolver::canValidate($actor, $task),
            'block' => $task !== null && $this->actionAvailability->isBlockable($task)
                && $actor->can('tasks.block') && TaskAbilityResolver::canBlock($actor, $task),
            'unblock' => $task !== null && $this->actionAvailability->isUnblockable($task)
                && $actor->can('tasks.block') && TaskAbilityResolver::canBlock($actor, $task),
            // spec 0118, D-10: same availability window as `complete`
            // (`isCompletable()` — no twin method), plus the matrix row that
            // additionally admits the watcher. Spec 0126, D-6 (REQUIREMENT
            // CHANGED): no longer ANDed with `! $task->is_blocked` — a
            // blocked Task now admits this one action.
            'request_update' => $task !== null && $this->actionAvailability->isCompletable($task)
                && $actor->can('tasks.requestUpdate') && TaskAbilityResolver::canRequestUpdate($actor, $task),
            // spec 0123, D-5: whether the actor may PATCH task_status_id
            // straight into a CLOSING phase (`close_negative` today, since
            // D-4a already reserves `in_validation`/`closed_positive` to the
            // domain actions for everyone). Spec 0126, D-4 (REQUIREMENT
            // CHANGED): now ANDed with `! $task->is_blocked` — a manual
            // status change, closing or not, is refused on a blocked Task
            // (D-4c/D-6), overturning spec 0116 D-7's "operative on a blocked
            // Task" carve-out for this one field. `actorMayWrite()` folds in
            // `tasks.update` the same way `fieldPermissionCeiling()` does.
            'close_via_status' => $task !== null && ! $task->is_blocked && $this->actorMayWrite($actor, $task)
                && ! TaskAbilityResolver::completionRequiresValidation($actor, $task)
                && ! ($task->requires_closure_feedback && trim((string) $task->closure_feedback) === ''),
            // spec 0123, D-9: gates the "Crea sotto-task" button. Structural,
            // not operative — a Task the write lock itself, or its cascade,
            // would refuse a `parent_task_id` insert under. Spec 0125 D-3 adds
            // the matrix row TaskParentAccessGuard enforces on the POST.
            'create_subtask' => $task !== null && $actor->can('tasks.create')
                && TaskAbilityResolver::canCreateSubtask($actor, $task)
                && ! TaskWriteLock::isLocked($task) && ! TaskWriteLock::isLockedByAncestor($task),
            // spec 0126, D-4: whether the actor may PATCH task_status_id AT
            // ALL — the CURRENT-state twin of `close_via_status`'s
            // resulting-state question. `TaskManualStatusGuard` is the single
            // source of the phase check (D-4b), reused here rather than
            // re-derived, so this flag and `TaskService::update()`'s own 422
            // never drift.
            'change_status' => $task !== null && ! $task->is_blocked && $this->actorMayWrite($actor, $task)
                && TaskManualStatusGuard::isCurrentPhaseOpenOrPending($task),
        ];
    }
}
