<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\Referent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `referents` resource (spec 0016).
 *
 * The 12 `personal_data.*` keys mirror UsersAuthorization's field catalogue
 * verbatim (dot-path shape of the write payload, spec 0008): the morph
 * card's own scalar fields, plus the `contacts`/`addresses` sections as a
 * SINGLE key each. Duplicated here (not extracted into a shared trait/base)
 * to keep the Users module completely untouched (spec 0016, zero blast
 * radius) — the two catalogues are expected to stay in lockstep by
 * convention, same as ReferentProfileWriter mirrors ProfileWriter.
 */
class ReferentsAuthorization extends AbstractResourceAuthorization
{
    public function __construct(FieldPermissionRepository $fieldPermissionRepository)
    {
        parent::__construct($fieldPermissionRepository);
    }

    public function resource(): string
    {
        return 'referents';
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        return [
            new FieldDefinition('referent_type_id', 'select'),
            new FieldDefinition('contact_scope', 'select', mandatory: true),
            new FieldDefinition('notes', 'text'),
            new FieldDefinition('personal_data.type', 'select', 'personal_data', mandatory: true),
            new FieldDefinition('personal_data.first_name', 'text', 'personal_data', mandatory: true),
            new FieldDefinition('personal_data.last_name', 'text', 'personal_data', mandatory: true),
            new FieldDefinition('personal_data.company_name', 'text', 'personal_data', mandatory: true),
            new FieldDefinition('personal_data.tax_code', 'text', 'personal_data'),
            new FieldDefinition('personal_data.vat_number', 'text', 'personal_data'),
            new FieldDefinition('personal_data.sdi_code', 'text', 'personal_data'),
            new FieldDefinition('personal_data.birth_date', 'date', 'personal_data'),
            new FieldDefinition('personal_data.birth_city_id', 'select', 'personal_data'),
            new FieldDefinition('personal_data.residence_city_id', 'select', 'personal_data'),
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
            'referent_type_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'contact_scope' => $mayWrite ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(),
            'notes' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
        ], $this->personalDataFieldPermissions($mayWrite));
    }

    /**
     * Ceiling for the 12 `personal_data.*` keys: editable whenever the actor
     * may write the referent at all, else readonly — same write/read
     * boundary as every other referent field, and the same rule
     * UsersAuthorization applies to its own `personal_data.*` keys.
     * `required` always false here: the per-type (individual/company)
     * required-ness is validation-layer business logic already owned by
     * ValidatesUserProfile::profileRules(), not duplicated in the
     * authorization ceiling.
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
            'personal_data.residence_city_id' => $permission,
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
        /** @var Referent|null $model */
        return [
            'delete' => $model !== null && $actor->can('referents.delete'),
            'export' => $actor->can('referents.export'),
            'import' => $actor->can('referents.import'),
            // Gates the ActivityLogSection in the detail (spec 0034); the
            // record-level `referents.view` boundary is enforced separately by
            // GET /api/activity-log/referents/{id} itself.
            'view_activity' => $model !== null && $actor->can('referents.viewActivity'),
        ];
    }
}
