<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `enasarco-parameters` resource (spec 0213,
 * D-8). Every field is visible+editable when the actor may write, else
 * visible+readonly; the year and the four values are mandatory.
 */
class EnasarcoParametersAuthorization extends AbstractResourceAuthorization
{
    /** Fields a parameter row cannot be saved without. */
    private const array REQUIRED_FIELDS = ['year', 'total_rate', 'agent_share_percent', 'single_firm_ceiling', 'multi_firm_ceiling'];

    public function __construct(FieldPermissionRepository $fieldPermissionRepository)
    {
        parent::__construct($fieldPermissionRepository);
    }

    public function resource(): string
    {
        return 'enasarco-parameters';
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        return [
            new FieldDefinition('year', 'number', mandatory: true),
            new FieldDefinition('total_rate', 'number', mandatory: true),
            new FieldDefinition('agent_share_percent', 'number', mandatory: true),
            new FieldDefinition('single_firm_ceiling', 'number', mandatory: true),
            new FieldDefinition('multi_firm_ceiling', 'number', mandatory: true),
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
            'delete' => $model !== null && $actor->can('enasarco-parameters.delete'),
            'export' => $actor->can('enasarco-parameters.export'),
            'view_activity' => $model !== null && $actor->can('enasarco-parameters.viewActivity'),
        ];
    }
}
