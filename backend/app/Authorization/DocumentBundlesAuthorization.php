<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `document-bundles` resource (spec 0175,
 * D-14). No contextual rules: every field's ceiling is visible+editable
 * when the actor may write (create/update), else visible+readonly,
 * mirroring TaskImportancesAuthorization. The bundle's files are managed
 * through the existing `/api/attachments` endpoints (alias `document_bundle`),
 * not a field here.
 */
class DocumentBundlesAuthorization extends AbstractResourceAuthorization
{
    public function resource(): string
    {
        return 'document-bundles';
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        return [
            new FieldDefinition('name', 'text', mandatory: true),
            new FieldDefinition('description', 'textarea'),
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
            'name' => $mayWrite ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(required: true),
            'description' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'is_active' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function actionPermissions(User $actor, ?Model $model): array
    {
        return [
            'delete' => $model !== null && $actor->can('document-bundles.delete'),
            'export' => $actor->can('document-bundles.export'),
            'import' => $actor->can('document-bundles.import'),
            'view_activity' => $model !== null && $actor->can('document-bundles.viewActivity'),
        ];
    }
}
