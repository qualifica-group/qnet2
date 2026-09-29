<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `email-templates` resource (spec 0175,
 * D-14).
 *
 * Every field's ceiling is visible+editable when the actor may write
 * (create/update), else visible+readonly — EXCEPT `module` (D-10/data
 * contract: "module immutabile in update"), writable ONLY in create
 * ($model === null); once persisted it is permanently readonly regardless of
 * write ability, mirroring WorkOrdersAuthorization's own `code`/`quote_id`
 * ceiling. The immutability itself is enforced one layer up by
 * UpdateEmailTemplateRequest's own `prohibited` rule, not by this ceiling.
 */
class EmailTemplatesAuthorization extends AbstractResourceAuthorization
{
    public function resource(): string
    {
        return 'email-templates';
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        return [
            new FieldDefinition('name', 'text', mandatory: true),
            new FieldDefinition('module', 'select', mandatory: true),
            new FieldDefinition('subject', 'text', mandatory: true),
            new FieldDefinition('body', 'richtext', mandatory: true),
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
        $mayWriteOnlyAtCreate = $mayWrite && $model === null;

        return [
            'name' => $mayWrite ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(required: true),
            'module' => $mayWriteOnlyAtCreate ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(required: true),
            'subject' => $mayWrite ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(required: true),
            'body' => $mayWrite ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(required: true),
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
            'delete' => $model !== null && $actor->can('email-templates.delete'),
            'export' => $actor->can('email-templates.export'),
            'import' => $actor->can('email-templates.import'),
            'view_activity' => $model !== null && $actor->can('email-templates.viewActivity'),
        ];
    }
}
