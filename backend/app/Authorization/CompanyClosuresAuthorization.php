<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `company-closures` resource (spec 0304): every
 * field is visible+editable when the actor may write (create/update), else
 * visible+readonly.
 */
class CompanyClosuresAuthorization extends AbstractResourceAuthorization
{
    private const array REQUIRED = ['name', 'starts_on', 'ends_on', 'kind', 'applies_to_all_sites'];

    public function __construct(FieldPermissionRepository $fieldPermissionRepository)
    {
        parent::__construct($fieldPermissionRepository);
    }

    public function resource(): string
    {
        return 'company-closures';
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        return [
            new FieldDefinition('name', 'text', mandatory: true),
            new FieldDefinition('starts_on', 'date', mandatory: true),
            new FieldDefinition('ends_on', 'date', mandatory: true),
            new FieldDefinition('kind', 'select', mandatory: true),
            new FieldDefinition('absence_type_id', 'select'),
            new FieldDefinition('applies_to_all_sites', 'boolean', mandatory: true),
            new FieldDefinition('operational_site_ids', 'multiselect'),
            new FieldDefinition('repeats_yearly', 'boolean'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function actions(): array
    {
        return ['delete', 'view_activity'];
    }

    /**
     * @return array<string, FieldPermission>
     */
    protected function fieldPermissionCeiling(User $actor, ?Model $model): array
    {
        $mayWrite = $this->actorMayWrite($actor, $model);
        $ceiling = [];

        foreach ($this->fields() as $field) {
            $ceiling[$field->key] = $mayWrite
                ? FieldPermission::visibleEditable(required: in_array($field->key, self::REQUIRED, true))
                : FieldPermission::visibleReadonly();
        }

        return $ceiling;
    }

    /**
     * @return array<string, bool>
     */
    public function actionPermissions(User $actor, ?Model $model): array
    {
        return [
            'delete' => $model !== null && $actor->can('company-closures.delete'),
            'view_activity' => $model !== null && $actor->can('company-closures.viewActivity'),
        ];
    }
}
