<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `work-order-workflows` resource (spec 0322, twin of quote-workflows).
 *
 * No contextual rules: every field's ceiling is simply visible+editable when
 * the actor may write (create/update), else visible+readonly. `criteria`/
 * `statuses` (the nested child collections) are edited via their own request
 * payload keys, not plain scalar fields here — this catalogue only covers
 * the workflow's own columns.
 */
class WorkOrderWorkflowsAuthorization extends AbstractResourceAuthorization
{
    public function __construct(FieldPermissionRepository $fieldPermissionRepository)
    {
        parent::__construct($fieldPermissionRepository);
    }

    public function resource(): string
    {
        return 'work-order-workflows';
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        return [
            new FieldDefinition('name', 'text', mandatory: true),
            new FieldDefinition('is_active', 'boolean'),
        ];
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

        return [
            'name' => $mayWrite ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(),
            'is_active' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function actionPermissions(User $actor, ?Model $model): array
    {
        return [
            'delete' => $model !== null && $actor->can('work-order-workflows.delete'),
            'export' => $actor->can('work-order-workflows.export'),
            'import' => $actor->can('work-order-workflows.import'),
            // Gates the ActivityLogSection in the detail (spec 0034); the
            // record-level `work-order-workflows.view` boundary is enforced
            // separately by GET /api/activity-log/work-order-workflows/{id}.
            'view_activity' => $model !== null && $actor->can('work-order-workflows.viewActivity'),
        ];
    }
}
