<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\DocumentChecklist;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `document-checklists` resource (spec 0403).
 * Every field is editable when the actor may write, except `code`, which turns
 * read-only once the checklist has been synced successfully (D-4). The `sync`
 * action follows `update`: asking for a new sync is a write on the checklist.
 */
class DocumentChecklistsAuthorization extends AbstractResourceAuthorization
{
    public function resource(): string
    {
        return 'document-checklists';
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        return [
            new FieldDefinition('name', 'text', mandatory: true),
            new FieldDefinition('code', 'text', mandatory: true),
            new FieldDefinition('description', 'textarea'),
            new FieldDefinition('is_active', 'boolean'),
            new FieldDefinition('items', 'section'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function actions(): array
    {
        return ['delete', 'view_activity', 'sync'];
    }

    /**
     * @return array<string, FieldPermission>
     */
    protected function fieldPermissionCeiling(User $actor, ?Model $model): array
    {
        $mayWrite = $this->actorMayWrite($actor, $model);
        $codeLocked = $model instanceof DocumentChecklist && $model->isCodeLocked();

        return [
            'name' => $mayWrite ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(required: true),
            'code' => $mayWrite && ! $codeLocked ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(required: true),
            'description' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'is_active' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'items' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function actionPermissions(User $actor, ?Model $model): array
    {
        return [
            'delete' => $model !== null && $actor->can('document-checklists.delete'),
            'view_activity' => $model !== null && $actor->can('document-checklists.viewActivity'),
            'sync' => $model !== null && $actor->can('document-checklists.update'),
        ];
    }
}
