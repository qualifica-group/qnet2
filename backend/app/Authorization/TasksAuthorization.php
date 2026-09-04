<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `tasks` resource (spec 0101).
 *
 * The field catalogue is the FROZEN order of the data_contract's own POST
 * payload (AC-053), and two keys are deliberately ABSENT from it:
 *  - `creator_id`, server-owned and immutable (D-10);
 *  - `completion_percentage`, derived from the status and never written
 *    (D-6).
 * A field that is not client-writable is not permissionable either: leaving
 * them out is what makes "not submittable" and "not configurable" the same
 * statement, instead of two that could drift. Both are additionally
 * `prohibited` at the FormRequest layer, ahead of and independent from this
 * ceiling — the privileged role bypasses every ceiling, so immutability
 * cannot be expressed here alone.
 *
 * `start_time`/`end_time` are declared `text` (D-11): the shared
 * FieldDefinition catalogue has no `time` type, and adding one is out of
 * scope for this spec.
 *
 * Every field's ceiling is the plain visible+editable-when-may-write /
 * visible+readonly default: unlike Commesse, no Task field is create-only.
 */
class TasksAuthorization extends AbstractResourceAuthorization
{
    /**
     * The two fields vital to creating a Task — the only `required` ones in
     * the data_contract, hence the only `mandatory` ones here.
     *
     * @var array<int, string>
     */
    private const array MANDATORY_FIELDS = ['title', 'task_status_id'];

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
        'is_blocked' => 'boolean',
        'requires_closure_feedback' => 'boolean',
        'closure_feedback' => 'textarea',
        'assignee_ids' => 'multiselect',
        'watcher_ids' => 'multiselect',
    ];

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
        return ['delete', 'export', 'import', 'view_activity'];
    }

    /**
     * @return array<string, FieldPermission>
     */
    protected function fieldPermissionCeiling(User $actor, ?Model $model): array
    {
        $mayWrite = $this->actorMayWrite($actor, $model);
        $ceiling = [];

        foreach (self::FIELD_TYPES as $key => $type) {
            $required = in_array($key, self::MANDATORY_FIELDS, true);

            $ceiling[$key] = $mayWrite
                ? FieldPermission::visibleEditable(required: $required)
                : FieldPermission::visibleReadonly(required: $required);
        }

        return $ceiling;
    }

    /**
     * @return array<string, bool>
     */
    public function actionPermissions(User $actor, ?Model $model): array
    {
        return [
            'delete' => $model !== null && $actor->can('tasks.delete'),
            'export' => $actor->can('tasks.export'),
            'import' => $actor->can('tasks.import'),
            // Gates the ActivityLogSection in the detail (spec 0034); the
            // record-level `tasks.view` boundary is enforced separately by
            // GET /api/activity-log/tasks/{id}.
            'view_activity' => $model !== null && $actor->can('tasks.viewActivity'),
        ];
    }
}
