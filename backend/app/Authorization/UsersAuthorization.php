<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;
use App\Services\RoleAssignmentGuard;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `users` resource (spec 0004).
 *
 * Reuses RoleAssignmentGuard::PRIVILEGED_ROLE (single source of truth for the
 * super-admin role name) — no super-admin logic is duplicated here. The
 * per-role-id escalation filter (which roles an actor may actually assign)
 * stays owned by RoleAssignmentGuard / ResolvesAssignableRoles; this class
 * only decides whether the whole `roles` field is editable at all.
 */
class UsersAuthorization extends AbstractResourceAuthorization
{
    public function __construct(FieldPermissionRepository $fieldPermissionRepository)
    {
        parent::__construct($fieldPermissionRepository);
    }

    public function resource(): string
    {
        return 'users';
    }

    /**
     * The 12 `personal_data.*` keys mirror the dot-path shape of the write
     * payload (spec 0008): the morph card's own scalar fields, plus the
     * `contacts`/`addresses` sections as a SINGLE key each (D1 — no
     * per-column granularity for their child rows).
     *
     * The 12 `employment.*` keys (spec 0015) mirror the nested employment
     * object's own dot-path shape, the same way: no dedicated resource
     * permission, governed entirely by this field-permission matrix (like
     * personal_data).
     *
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        return [
            new FieldDefinition('email', 'email', mandatory: true),
            new FieldDefinition('locale', 'select', mandatory: true),
            new FieldDefinition('is_active', 'boolean'),
            new FieldDefinition('roles', 'multiselect'),
            new FieldDefinition('password', 'password', mandatory: true),
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
            new FieldDefinition('employment.is_manager', 'boolean', 'employment'),
            new FieldDefinition('employment.job_description', 'text', 'employment'),
            new FieldDefinition('employment.reports_to_id', 'select', 'employment'),
            new FieldDefinition('employment.business_function_id', 'select', 'employment'),
            new FieldDefinition('employment.relationship_type', 'select', 'employment'),
            new FieldDefinition('employment.company_id', 'select', 'employment'),
            new FieldDefinition('employment.operational_site_id', 'select', 'employment'),
            new FieldDefinition('employment.qualification_type', 'select', 'employment'),
            new FieldDefinition('employment.hired_at', 'date', 'employment'),
            new FieldDefinition('employment.terminated_at', 'date', 'employment'),
            new FieldDefinition('employment.standard_daily_minutes', 'number', 'employment'),
            new FieldDefinition('employment.break_daily_minutes', 'number', 'employment'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function actions(): array
    {
        return ['delete', 'export', 'import', 'upload_avatar', 'delete_avatar', 'view_activity'];
    }

    /**
     * The security ceiling for `users` fields (spec 0004 rules, unchanged;
     * spec 0006 renamed this from `fieldPermissions()` — the DB matrix merge
     * now lives once in AbstractResourceAuthorization::fieldPermissions()).
     *
     * @return array<string, FieldPermission>
     */
    protected function fieldPermissionCeiling(User $actor, ?Model $model): array
    {
        /** @var User|null $model */
        $mayWrite = $this->actorMayWrite($actor, $model);
        $isCreate = $model === null;

        return array_merge([
            'email' => $mayWrite ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(),
            'locale' => $mayWrite ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(),
            'is_active' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'password' => $mayWrite ? FieldPermission::visibleEditable(required: $isCreate) : FieldPermission::visibleReadonly(),
            'roles' => $this->rolesFieldPermission($actor, $model, $mayWrite),
        ], $this->personalDataFieldPermissions($mayWrite), $this->employmentFieldPermissions($mayWrite));
    }

    /**
     * Ceiling for the 12 `personal_data.*` keys: editable whenever the actor
     * may write the user at all, else readonly — same write/read boundary as
     * every other user field. `required` always false here: the per-type
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
            'personal_data.residence_city_id' => $permission,
            'personal_data.gender' => $permission,
            'personal_data.contacts' => $permission,
            'personal_data.addresses' => $permission,
        ];
    }

    /**
     * Ceiling for the 12 `employment.*` keys: editable whenever the actor may
     * write the user at all, else readonly — same write/read boundary as the
     * personal_data section (no employment.* resource permission, spec 0015).
     *
     * @return array<string, FieldPermission>
     */
    private function employmentFieldPermissions(bool $mayWrite): array
    {
        $permission = $mayWrite ? FieldPermission::visibleEditable(required: false) : FieldPermission::visibleReadonly();

        return [
            'employment.is_manager' => $permission,
            'employment.job_description' => $permission,
            'employment.reports_to_id' => $permission,
            'employment.business_function_id' => $permission,
            'employment.relationship_type' => $permission,
            'employment.company_id' => $permission,
            'employment.operational_site_id' => $permission,
            'employment.qualification_type' => $permission,
            'employment.hired_at' => $permission,
            'employment.terminated_at' => $permission,
            'employment.standard_daily_minutes' => $permission,
            'employment.break_daily_minutes' => $permission,
        ];
    }

    /**
     * `roles` is editable when the actor may write, UNLESS the target is a
     * super-admin and the actor is not — mirrors the escalation boundary
     * already enforced by RoleAssignmentGuard on the write path.
     */
    private function rolesFieldPermission(User $actor, ?User $model, bool $mayWrite): FieldPermission
    {
        $targetIsPrivileged = $model !== null && $model->hasRole(RoleAssignmentGuard::PRIVILEGED_ROLE);
        $actorIsPrivileged = $actor->hasRole(RoleAssignmentGuard::PRIVILEGED_ROLE);

        if ($targetIsPrivileged && ! $actorIsPrivileged) {
            return FieldPermission::visibleReadonly();
        }

        return $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly();
    }

    /**
     * @return array<string, bool>
     */
    public function actionPermissions(User $actor, ?Model $model): array
    {
        /** @var User|null $model */
        return [
            // Mirrors UserPolicy::delete: users.delete AND never self.
            'delete' => $model !== null && ! $actor->is($model) && $actor->can('users.delete'),
            'export' => $actor->can('users.export'),
            'import' => $actor->can('users.import'),
            'upload_avatar' => $model !== null && $actor->can('users.update'),
            'delete_avatar' => $model !== null && $actor->can('users.update'),
            // Gates the ActivityLogSection in the detail (spec 0034); the
            // record-level `users.view` boundary is enforced separately by
            // GET /api/activity-log/users/{id} itself.
            'view_activity' => $model !== null && $actor->can('users.viewActivity'),
        ];
    }
}
