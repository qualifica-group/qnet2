<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `agent-fiscal-profiles` resource (spec 0212,
 * D-10). Every field's ceiling is visible+editable when the actor may write,
 * else visible+readonly; the referent and the validity start are mandatory.
 */
class AgentFiscalProfilesAuthorization extends AbstractResourceAuthorization
{
    /** Fields a profile cannot be saved without. */
    private const array REQUIRED_FIELDS = ['referent_id', 'valid_from'];

    public function __construct(FieldPermissionRepository $fieldPermissionRepository)
    {
        parent::__construct($fieldPermissionRepository);
    }

    public function resource(): string
    {
        return 'agent-fiscal-profiles';
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        return [
            new FieldDefinition('referent_id', 'select', mandatory: true),
            new FieldDefinition('withholding_enabled', 'boolean'),
            new FieldDefinition('withholding_rate', 'number'),
            new FieldDefinition('withholding_base_percent', 'number'),
            new FieldDefinition('enasarco_enabled', 'boolean'),
            new FieldDefinition('enasarco_mandate_type', 'select'),
            new FieldDefinition('valid_from', 'date', mandatory: true),
            new FieldDefinition('valid_until', 'date'),
            new FieldDefinition('notes', 'textarea'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function actions(): array
    {
        return ['delete', 'export', 'view_activity'];
    }

    /**
     * @return array<string, FieldPermission>
     */
    protected function fieldPermissionCeiling(User $actor, ?Model $model): array
    {
        $mayWrite = $this->actorMayWrite($actor, $model);
        $permissions = [];

        foreach ($this->fields() as $field) {
            $permissions[$field->key] = match (true) {
                ! $mayWrite => FieldPermission::visibleReadonly(),
                in_array($field->key, self::REQUIRED_FIELDS, true) => FieldPermission::visibleEditable(required: true),
                default => FieldPermission::visibleEditable(),
            };
        }

        return $permissions;
    }

    /**
     * @return array<string, bool>
     */
    public function actionPermissions(User $actor, ?Model $model): array
    {
        return [
            'delete' => $model !== null && $actor->can('agent-fiscal-profiles.delete'),
            'export' => $actor->can('agent-fiscal-profiles.export'),
            'view_activity' => $model !== null && $actor->can('agent-fiscal-profiles.viewActivity'),
        ];
    }
}
