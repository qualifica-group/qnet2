<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `attributes` resource (spec 0017, aligned to
 * the custom fields' presentation shape — spec 0021).
 *
 * No contextual rules: every field's ceiling is simply visible+editable when
 * the actor may write (create/update), else visible+readonly, mirroring
 * CustomFieldsAuthorization. `config`/`relation_target`/`options` are nested,
 * custom-rendered editors, never a plain scalar input.
 */
class AttributesAuthorization extends AbstractResourceAuthorization
{
    public function __construct(FieldPermissionRepository $fieldPermissionRepository)
    {
        parent::__construct($fieldPermissionRepository);
    }

    public function resource(): string
    {
        return 'attributes';
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        return [
            new FieldDefinition('code', 'text', mandatory: true),
            new FieldDefinition('name', 'text', mandatory: true),
            new FieldDefinition('type', 'select', mandatory: true),
            new FieldDefinition('description', 'text'),
            new FieldDefinition('help_text', 'text'),
            new FieldDefinition('placeholder', 'text'),
            new FieldDefinition('icon', 'text'),
            new FieldDefinition('config', 'custom'),
            new FieldDefinition('relation_target', 'custom'),
            new FieldDefinition('options', 'custom'),
            // Spec 0429: the current field a legacy one continues.
            new FieldDefinition('current_attribute_id', 'select'),
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

        $writable = static fn (bool $required = false): FieldPermission => $mayWrite
            ? FieldPermission::visibleEditable(required: $required)
            : FieldPermission::visibleReadonly();

        return [
            'code' => $writable(required: true),
            'name' => $writable(required: true),
            'type' => $writable(required: true),
            'description' => $writable(),
            'help_text' => $writable(),
            'placeholder' => $writable(),
            'icon' => $writable(),
            'config' => $writable(),
            'relation_target' => $writable(),
            'options' => $writable(),
            'current_attribute_id' => $writable(),
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function actionPermissions(User $actor, ?Model $model): array
    {
        return [
            'delete' => $model !== null && $actor->can('attributes.delete'),
            'export' => $actor->can('attributes.export'),
            'import' => $actor->can('attributes.import'),
            // Gates the ActivityLogSection in the detail (spec 0034); the
            // record-level `attributes.view` boundary is enforced separately
            // by GET /api/activity-log/attributes/{id} itself.
            'view_activity' => $model !== null && $actor->can('attributes.viewActivity'),
        ];
    }
}
