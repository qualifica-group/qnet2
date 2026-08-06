<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `quote-workflows` resource (spec 0047; moved
 * onto the Offerta by spec 0083, D-6).
 *
 * No contextual rules: every field's ceiling is simply visible+editable when
 * the actor may write (create/update), else visible+readonly. `criteria`/
 * `statuses` (the nested child collections) are edited via their own request
 * payload keys, not plain scalar fields here — this catalogue only covers
 * the workflow's own columns.
 */
class QuoteWorkflowsAuthorization extends AbstractResourceAuthorization
{
    public function __construct(FieldPermissionRepository $fieldPermissionRepository)
    {
        parent::__construct($fieldPermissionRepository);
    }

    public function resource(): string
    {
        return 'quote-workflows';
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
            'delete' => $model !== null && $actor->can('quote-workflows.delete'),
            'export' => $actor->can('quote-workflows.export'),
            'import' => $actor->can('quote-workflows.import'),
            // Gates the ActivityLogSection in the detail (spec 0034); the
            // record-level `quote-workflows.view` boundary is enforced
            // separately by GET /api/activity-log/quote-workflows/{id}.
            'view_activity' => $model !== null && $actor->can('quote-workflows.viewActivity'),
        ];
    }
}
