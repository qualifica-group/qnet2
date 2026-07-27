<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\Registry;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `registries` resource (spec 0020).
 *
 * The 12 `personal_data.*` keys mirror ReferentsAuthorization/
 * UsersAuthorization's field catalogue verbatim (dot-path shape of the write
 * payload, spec 0008). Duplicated here (not extracted into a shared
 * trait/base) to keep the Referents/Users modules completely untouched
 * (spec 0020, zero blast radius) — the catalogues are expected to stay in
 * lockstep by convention, same as RegistryProfileWriter mirrors
 * ReferentProfileWriter.
 */
class RegistriesAuthorization extends AbstractResourceAuthorization
{
    public function __construct(FieldPermissionRepository $fieldPermissionRepository)
    {
        parent::__construct($fieldPermissionRepository);
    }

    public function resource(): string
    {
        return 'registries';
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        return [
            new FieldDefinition('source_id', 'select'),
            new FieldDefinition('sector_ids', 'multiselect'),
            new FieldDefinition('referent_ids', 'multiselect'),
            new FieldDefinition('manager_slots', 'multiselect'),
            new FieldDefinition('supervisor_id', 'select'),
            new FieldDefinition('commercial_id', 'select'),
            new FieldDefinition('reporter_id', 'select'),
            new FieldDefinition('vat_group', 'text'),
            new FieldDefinition('is_supplier', 'boolean', mandatory: true),
            new FieldDefinition('is_qualified_supplier', 'boolean'),
            new FieldDefinition('agreement_status', 'select'),
            new FieldDefinition('agreement_notes', 'text'),
            new FieldDefinition('size_class', 'select'),
            new FieldDefinition('employee_count', 'number'),
            new FieldDefinition('personal_data.type', 'select', 'personal_data', mandatory: true),
            new FieldDefinition('personal_data.first_name', 'text', 'personal_data', mandatory: true),
            new FieldDefinition('personal_data.last_name', 'text', 'personal_data', mandatory: true),
            new FieldDefinition('personal_data.company_name', 'text', 'personal_data', mandatory: true),
            new FieldDefinition('personal_data.tax_code', 'text', 'personal_data'),
            new FieldDefinition('personal_data.vat_number', 'text', 'personal_data'),
            new FieldDefinition('personal_data.sdi_code', 'text', 'personal_data'),
            new FieldDefinition('personal_data.birth_date', 'date', 'personal_data'),
            new FieldDefinition('personal_data.birth_city_id', 'select', 'personal_data'),
            new FieldDefinition('personal_data.gender', 'select', 'personal_data'),
            new FieldDefinition('personal_data.contacts', 'collection', 'personal_data'),
            new FieldDefinition('personal_data.addresses', 'collection', 'personal_data'),
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

        return array_merge([
            'source_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'sector_ids' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'referent_ids' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'manager_slots' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'supervisor_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'commercial_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'reporter_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'vat_group' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'is_supplier' => $mayWrite ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(),
            'is_qualified_supplier' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'agreement_status' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'agreement_notes' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'size_class' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'employee_count' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
        ], $this->personalDataFieldPermissions($mayWrite));
    }

    /**
     * Ceiling for the 12 `personal_data.*` keys: editable whenever the actor
     * may write the registry at all, else readonly — same write/read
     * boundary as every other registry field, and the same rule
     * ReferentsAuthorization/UsersAuthorization apply to their own
     * `personal_data.*` keys. `required` always false here: the per-type
     * (individual/company) required-ness is validation-layer business logic
     * already owned by ValidatesUserProfile::profileRules(), not duplicated
     * in the authorization ceiling.
     *
     * @return array<string, FieldPermission>
     */
    private function personalDataFieldPermissions(bool $mayWrite): array
    {
        $permission = $mayWrite ? FieldPermission::visibleEditable(required: false) : FieldPermission::visibleReadonly();

        return [
            'personal_data.type' => $permission,
            'personal_data.first_name' => $permission,
            'personal_data.last_name' => $permission,
            'personal_data.company_name' => $permission,
            'personal_data.tax_code' => $permission,
            'personal_data.vat_number' => $permission,
            'personal_data.sdi_code' => $permission,
            'personal_data.birth_date' => $permission,
            'personal_data.birth_city_id' => $permission,
            'personal_data.gender' => $permission,
            'personal_data.contacts' => $permission,
            'personal_data.addresses' => $permission,
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function actionPermissions(User $actor, ?Model $model): array
    {
        /** @var Registry|null $model */
        return [
            'delete' => $model !== null && $actor->can('registries.delete'),
            'export' => $actor->can('registries.export'),
            'import' => $actor->can('registries.import'),
            // Gates the ActivityLogSection in the detail (spec 0034); the
            // record-level `registries.view` boundary is enforced separately
            // by GET /api/activity-log/registries/{id} itself.
            'view_activity' => $model !== null && $actor->can('registries.viewActivity'),
        ];
    }
}
