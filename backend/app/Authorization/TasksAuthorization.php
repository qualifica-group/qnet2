<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\Task;
use App\Models\User;
use App\Services\Tasks\TaskAbilityResolver;
use App\Services\Tasks\TaskActionAvailability;
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
 * visible+readonly default, EXCEPT the 18 fields in
 * `TaskAbilityResolver::PROTECTED_FIELDS` (spec 0116, D-5; spec 0121, D-1
 * adds `requires_validation`): those additionally
 * require the actor to own the Task's MANDATE
 * (`TaskAbilityResolver::canUpdateProtectedFields()`) once a record exists.
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
        'description' => 'textarea',
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
        return ['delete', 'export', 'import', 'view_activity', 'view_documents', 'complete', 'complete_to_validation', 'uncomplete', 'approve', 'reject', 'block', 'unblock', 'request_update'];
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
            if ($key === 'task_status_id' && $model === null) {
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
     *
     * @return array<string, bool>
     */
    public function actionPermissions(User $actor, ?Model $model): array
    {
        $task = $model instanceof Task ? $model : null;

        $canComplete = $task !== null && ! $task->is_blocked && $this->actionAvailability->isCompletable($task)
            && $actor->can('tasks.complete') && TaskAbilityResolver::canComplete($actor, $task);

        return [
            'delete' => $model !== null && $actor->can('tasks.delete'),
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
            'approve' => $task !== null && ! $task->is_blocked && $this->actionAvailability->isValidatable($task)
                && $actor->can('tasks.validate') && TaskAbilityResolver::canValidate($actor, $task),
            'reject' => $task !== null && ! $task->is_blocked && $this->actionAvailability->isValidatable($task)
                && $actor->can('tasks.validate') && TaskAbilityResolver::canValidate($actor, $task),
            'block' => $task !== null && $this->actionAvailability->isBlockable($task)
                && $actor->can('tasks.block') && TaskAbilityResolver::canBlock($actor, $task),
            'unblock' => $task !== null && $this->actionAvailability->isUnblockable($task)
                && $actor->can('tasks.block') && TaskAbilityResolver::canBlock($actor, $task),
            // spec 0118, D-10: same availability window as `complete`
            // (`isCompletable()` — no twin method), plus the matrix row that
            // additionally admits the watcher.
            'request_update' => $task !== null && ! $task->is_blocked && $this->actionAvailability->isCompletable($task)
                && $actor->can('tasks.requestUpdate') && TaskAbilityResolver::canRequestUpdate($actor, $task),
        ];
    }
}
