<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `operational-sites` resource (spec 0011).
 *
 * The 6 fields mirror the flat write payload (line1/postal_code + the geo FK
 * cascade country_id/state_id/province_id/city_id) — there is no nested
 * `address` object, unlike CompaniesAuthorization: the site's single address
 * IS the resource. `city_id` and `line1` are mandatory (spec 0008): the DB
 * field-permission matrix can only narrow within the ceiling, never below
 * mandatory. No contextual rules (no protected system row): every field's
 * ceiling is simply visible+editable when the actor may write, else
 * visible+readonly, mirroring BusinessFunctionsAuthorization/
 * CompaniesAuthorization. actionPermissions() IS overridden (spec 0034):
 * `view_activity` maps to `operational-sites.viewActivity` (camelCase
 * ability), which the default "{resource}.{action}" mapping cannot express
 * for an underscored action key.
 */
class OperationalSitesAuthorization extends AbstractResourceAuthorization
{
    public function __construct(FieldPermissionRepository $fieldPermissionRepository)
    {
        parent::__construct($fieldPermissionRepository);
    }

    public function resource(): string
    {
        return 'operational-sites';
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        return [
            new FieldDefinition('alias', 'text'),
            new FieldDefinition('country_id', 'select'),
            new FieldDefinition('state_id', 'select'),
            new FieldDefinition('province_id', 'select'),
            new FieldDefinition('city_id', 'select', mandatory: true),
            new FieldDefinition('line1', 'text', mandatory: true),
            new FieldDefinition('postal_code', 'text'),
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
            'alias' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'country_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'state_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'province_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'city_id' => $mayWrite ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(),
            'line1' => $mayWrite ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(),
            'postal_code' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'is_active' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function actionPermissions(User $actor, ?Model $model): array
    {
        return [
            'delete' => $model !== null && $actor->can('operational-sites.delete'),
            'export' => $actor->can('operational-sites.export'),
            'import' => $actor->can('operational-sites.import'),
            // Gates the ActivityLogSection in the detail (spec 0034); the
            // record-level `operational-sites.view` boundary is enforced
            // separately by GET /api/activity-log/operational-sites/{id}.
            'view_activity' => $model !== null && $actor->can('operational-sites.viewActivity'),
        ];
    }
}
