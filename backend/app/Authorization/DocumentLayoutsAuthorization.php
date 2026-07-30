<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `document-layouts` resource (spec 0069).
 *
 * Every field's ceiling is visible+editable when the actor may write
 * (create/update), else visible+readonly — EXCEPT `code`/`module` (D-2),
 * writable ONLY in create ($model === null); once persisted both are
 * permanently readonly regardless of write ability, mirroring
 * PaymentMethodsAuthorization's own `code` ceiling. `config` is a declared
 * field (not an internal detail): a role can hold `update` on the layout's
 * metadata without being able to rewrite the document itself.
 */
class DocumentLayoutsAuthorization extends AbstractResourceAuthorization
{
    public function __construct(FieldPermissionRepository $fieldPermissionRepository)
    {
        parent::__construct($fieldPermissionRepository);
    }

    public function resource(): string
    {
        return 'document-layouts';
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
            new FieldDefinition('module', 'select', mandatory: true),
            new FieldDefinition('is_active', 'boolean'),
            new FieldDefinition('is_default', 'boolean'),
            new FieldDefinition('config', 'json', mandatory: true),
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
        $mayWriteOnCreateOnly = $mayWrite && $model === null;

        return [
            'name' => $mayWrite ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(),
            // code/module are writable only in create (D-2): permanently
            // readonly once a $model exists, regardless of write ability —
            // the immutability guard is enforced ahead of this ceiling by
            // UpdateDocumentLayoutRequest's own `prohibited` rules.
            'code' => $mayWriteOnCreateOnly ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(),
            'module' => $mayWriteOnCreateOnly ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(),
            'description' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'is_active' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'is_default' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'config' => $mayWrite ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(),
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function actionPermissions(User $actor, ?Model $model): array
    {
        return [
            'delete' => $model !== null && $actor->can('document-layouts.delete'),
            'export' => $actor->can('document-layouts.export'),
            'import' => $actor->can('document-layouts.import'),
            // Gates the ActivityLogSection in the detail (spec 0034); the
            // record-level `document-layouts.view` boundary is enforced
            // separately by GET /api/activity-log/document-layouts/{id}.
            'view_activity' => $model !== null && $actor->can('document-layouts.viewActivity'),
        ];
    }
}
