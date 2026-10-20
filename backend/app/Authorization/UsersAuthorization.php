<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\BusinessFunction;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Services\RoleAssignmentGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

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
    /**
     * The contract history and the condition in force (spec 0283 D-2): one
     * key for both, as they are the same rows.
     */
    public const string CONTRACTS_FIELD = 'employment.contracts';

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
     * The `employment.*` keys (spec 0015, plus the competence of spec
     * 0111 — one `product_lines` collection replacing the former
     * `business_function_id`/`product_category_ids` pair — spec 0129's
     * `covers_all_product_categories` wildcard flag and spec 0194's
     * `is_assignable` switch) mirror the nested
     * employment object's own dot-path shape, the same way: no dedicated
     * resource permission, governed entirely by this field-permission matrix
     * (like personal_data).
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
            new FieldDefinition('password', 'password'),
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
            new FieldDefinition('employment.covers_all_product_categories', 'boolean', 'employment'),
            new FieldDefinition('employment.is_assignable', 'boolean', 'employment'),
            new FieldDefinition('employment.job_role_ids', 'multiselect', 'employment'),
            new FieldDefinition('employment.business_function_ids', 'multiselect', 'employment'),
            new FieldDefinition('employment.managed_business_function_ids', 'multiselect', 'employment'),
            // Spec 0166 (D-8): a user may report to several managers now,
            // replacing the single `employment.reports_to_id` (select).
            new FieldDefinition('employment.reports_to_ids', 'multiselect', 'employment'),
            new FieldDefinition('employment.product_lines', 'collection', 'employment'),
            new FieldDefinition('employment.relationship_type', 'select', 'employment'),
            new FieldDefinition('employment.company_id', 'select', 'employment'),
            new FieldDefinition(self::CONTRACTS_FIELD, 'collection', 'employment'),
            new FieldDefinition('employment.primary_operational_site_id', 'select', 'employment'),
            new FieldDefinition('employment.remote_operational_site_ids', 'multiselect', 'employment'),
            new FieldDefinition('employment.hired_at', 'date', 'employment'),
            new FieldDefinition('employment.terminated_at', 'date', 'employment'),
            new FieldDefinition('employment.standard_daily_minutes', 'number', 'employment'),
            new FieldDefinition('employment.break_daily_minutes', 'number', 'employment'),
            new FieldDefinition('employment.iban', 'text', 'employment'),
            new FieldDefinition('employment.iban_holder', 'text', 'employment'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function actions(): array
    {
        return ['delete', 'export', 'import', 'upload_avatar', 'delete_avatar', 'view_activity', 'impersonate'];
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

        return array_merge([
            'email' => $mayWrite ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(),
            'locale' => $mayWrite ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(),
            'is_active' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'password' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'roles' => $this->rolesFieldPermission($actor, $model, $mayWrite),
        ], $this->personalDataFieldPermissions($mayWrite), $this->employmentFieldPermissions($actor, $mayWrite));
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
     * Ceiling for the `employment.*` keys: editable whenever the actor may
     * write the user at all, else readonly — same write/read boundary as the
     * personal_data section (no employment.* resource permission, spec 0015).
     *
     * Two exceptions (spec 0272): `relationship_type`/`company_id` are the
     * read-only copy of the current contract condition (D-2), so they are never
     * editable here; the payment fields (D-5/D-6) are hidden altogether unless
     * the actor holds `users.manageCompensation`, and editable only with it
     * plus the base write ability.
     *
     * @return array<string, FieldPermission>
     */
    private function employmentFieldPermissions(User $actor, bool $mayWrite): array
    {
        $permission = $mayWrite ? FieldPermission::visibleEditable(required: false) : FieldPermission::visibleReadonly();
        $payment = match (true) {
            ! $actor->can(UserPolicy::MANAGE_COMPENSATION_PERMISSION) => FieldPermission::hidden(),
            default => $permission,
        };

        return [
            'employment.covers_all_product_categories' => $permission,
            'employment.is_assignable' => $permission,
            'employment.job_role_ids' => $permission,
            'employment.business_function_ids' => $permission,
            'employment.managed_business_function_ids' => $this->managedFunctionsPermission($actor, $mayWrite),
            'employment.reports_to_ids' => $permission,
            'employment.product_lines' => $permission,
            'employment.relationship_type' => FieldPermission::visibleReadonly(),
            'employment.company_id' => FieldPermission::visibleReadonly(),
            // Written through the history endpoints, with the base write ability.
            self::CONTRACTS_FIELD => $permission,
            'employment.primary_operational_site_id' => $permission,
            'employment.remote_operational_site_ids' => $permission,
            'employment.hired_at' => $permission,
            'employment.terminated_at' => $permission,
            'employment.standard_daily_minutes' => $permission,
            'employment.break_daily_minutes' => $permission,
            'employment.iban' => $payment,
            'employment.iban_holder' => $payment,
        ];
    }

    /**
     * Spec 0276 D-4: the managed functions are written on
     * `business_functions.manager_id`, so editing them from the user needs the
     * user field editable AND that function field editable for the actor
     * (resolved with an `update` context: a model-less call would test `create`).
     */
    private function managedFunctionsPermission(User $actor, bool $mayWrite): FieldPermission
    {
        $userSide = $mayWrite ? FieldPermission::visibleEditable(required: false) : FieldPermission::visibleReadonly();

        $functionSide = app(AuthorizationRegistry::class)
            ->resolve('business-functions')
            ->fieldPermissions($actor, new BusinessFunction)['manager_id'] ?? null;

        return $userSide->editable && $functionSide?->editable ? $userSide : FieldPermission::visibleReadonly();
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
            // The detail's "Accedi come" (spec 0050): UserPolicy::impersonate plus
            // the two business rules ImpersonationService enforces (never self,
            // never an inactive target), so the action is offered only when it can succeed.
            'impersonate' => $model !== null
                && ! $actor->is($model)
                && $model->is_active
                && Gate::forUser($actor)->allows('impersonate', $model),
        ];
    }
}
