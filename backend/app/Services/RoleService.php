<?php

namespace App\Services;

use App\Authorization\AssignablePermissionCatalogue;
use App\DataObjects\Roles\CreateRoleData;
use App\DataObjects\Roles\UpdateRoleData;
use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Guard;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Business logic for the Roles resource (create / update / delete + permission
 * sync). The controller stays thin; this service is the single authority and the
 * only place the system-role guard is always enforced (Gate::before bypasses the
 * Policy for super-admins, so a hard guard cannot live in RolePolicy).
 */
class RoleService
{
    public function __construct(
        private readonly RoleAssignmentGuard $guard,
        private readonly AssignablePermissionCatalogue $permissionCatalogue,
    ) {}

    /**
     * Create a new role, optionally syncing its permissions and members.
     *
     * Role creation, permission sync and member sync run in a single transaction
     * so a failure never leaves a half-provisioned role behind. The acting user
     * is required so member assignment goes through the same privilege guard the
     * user side uses (no escalation to super-admin from the role form).
     */
    public function create(User $actor, CreateRoleData $data): Role
    {
        return DB::transaction(function () use ($actor, $data): Role {
            /** @var Role $role */
            $role = Role::create([
                'name' => $data->name,
                'guard_name' => $this->guardName(),
                'description' => $data->description,
            ]);

            if ($data->hasPermissions()) {
                $this->syncPermissions($role, $data->permissions);
            }

            if ($data->hasUsers()) {
                $this->syncUsers($actor, $role, $data->users);
            }

            if ($data->hasFieldPermissions()) {
                $this->syncFieldPermissions($role, $data->fieldPermissions);
            }

            return $role;
        });
    }

    /**
     * Update an existing role's name and, when provided, its permissions,
     * members and field-permission matrix. Only keys present in $data are
     * touched, so partial (PATCH) updates leave untouched fields as-is.
     *
     * The protected `super-admin` role can never have its NAME or PERMISSIONS
     * mutated (guardSystemRole). Its MEMBERSHIP, however, may be managed by a
     * super-admin actor (last-super-admin protected) — so guardSystemRole is
     * scoped to name/permission mutation and membership follows the actor rule in
     * RoleAssignmentGuard. The actor is required for the same reason as create().
     */
    public function update(User $actor, Role $role, UpdateRoleData $data): Role
    {
        $this->guardSystemRoleMutation($role, $data);

        return DB::transaction(function () use ($actor, $role, $data): Role {
            $attributes = $data->submittedAttributes();

            // Unconditional save: fire the model's saved event even when no native
            // attribute changed, so the HasCustomFields write pipeline (spec 0021)
            // persists a custom-fields-only edit. A clean save runs no UPDATE query.
            $role->fill($attributes)->save();

            if ($data->hasPermissions()) {
                $this->syncPermissions($role, $data->permissions);
            }

            if ($data->hasUsers()) {
                $this->syncUsers($actor, $role, $data->users);
            }

            if ($data->hasFieldPermissions()) {
                $this->syncFieldPermissions($role, $data->fieldPermissions);
            }

            return $role;
        });
    }

    /**
     * Searched + paginated + hydrated role list for the for-select endpoint
     * (GET /api/roles/for-select), feeding the user-form role multi-select. The
     * role counterpart of UserService::forSelect.
     *
     * Options are scoped to the ACTOR's assignable roles (RoleAssignmentGuard —
     * the single source of truth), so a non super-admin never sees `super-admin`
     * in the picker, exactly like the users table `roles` filter. The same scope
     * is applied to the hydrated `ids[]`, so a non-assignable selected role is
     * never leaked back through this endpoint (its badge label still comes from
     * the user resource on the client). `total` reflects only the assignable +
     * searchable population.
     */
    public function forSelect(ForSelectQuery $query, User $actor): ForSelectResult
    {
        $assignable = $this->guard->assignableRoleNames($actor);

        $base = Role::query()
            ->select(['id', 'name'])
            ->whereIn('name', $assignable);

        if ($query->hasSearch()) {
            $base->where('name', 'like', '%'.$query->search.'%');
        }

        $total = (clone $base)->count();

        /** @var Collection<int, Role> $page */
        $page = $base->orderBy('name')
            ->orderBy('id')
            ->offset($query->offset)
            ->limit($query->limit)
            ->get();

        $items = $this->appendHydratedIds($page, $query, $assignable);

        return new ForSelectResult(
            items: $items,
            total: $total,
            offset: $query->offset,
            limit: $query->limit,
        );
    }

    /**
     * Append the explicitly-requested `ids[]` (edit-mode hydration) not already on
     * the page, deduplicated and still constrained to the actor's assignable roles
     * (so the scope can never be bypassed via ids). Total is unaffected.
     *
     * @param  Collection<int, Role>  $page
     * @param  array<int, string>  $assignable
     * @return Collection<int, Role>
     */
    private function appendHydratedIds(Collection $page, ForSelectQuery $query, array $assignable): Collection
    {
        if (! $query->hasIds()) {
            return $page;
        }

        $presentIds = $page->pluck('id')->all();
        $missingIds = array_values(array_diff($query->ids, $presentIds));

        if ($missingIds === []) {
            return $page;
        }

        /** @var Collection<int, Role> $hydrated */
        $hydrated = Role::query()
            ->select(['id', 'name'])
            ->whereIn('name', $assignable)
            ->whereIn('id', $missingIds)
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return $page->concat($hydrated);
    }

    /**
     * Sync a role's members from a list of user ids, routed through the shared
     * RoleAssignmentGuard (single source of truth). The guard filters out
     * super-admin membership changes a non-super-admin actor may not make and
     * enforces the last-super-admin protection on a membership shrink. Runs in the
     * caller's transaction so a failure never half-applies.
     *
     * @param  array<int, int>  $userIds
     */
    private function syncUsers(User $actor, Role $role, array $userIds): void
    {
        // The technical users are invisible to the member list, so the submitted
        // ids never carry them: keep them or the sync would strip their role.
        $authorizedIds = [
            ...$this->guard->authorizedUserIdsForRole($actor, $role, $userIds),
            ...$role->users()->where('is_service_account', true)->pluck('users.id')->map(static fn ($id): int => (int) $id)->all(),
        ];

        $this->guard->guardLastSuperAdminMembershipShrink($role, $authorizedIds);

        $role->users()->sync($authorizedIds);
    }

    /**
     * Sync a role's permissions from a list of permission names.
     *
     * Permissions are resolved to model instances scoped to the role's own guard
     * (rather than passed as bare names) so syncPermissions never has to infer a
     * guard from the request: on an API (sanctum) call Spatie's name resolution
     * would look the permissions up on the `sanctum` guard, while the catalogue
     * lives on `web`. Resolving explicit models on the role's guard keeps roles
     * and permissions resolvable regardless of the request's active guard.
     *
     * The Role form only manages the assignable (form-module) catalogue, so any
     * indirect sub-entity permissions the role already holds (addresses.*,
     * contacts.*, …, governed via field-permissions) are preserved: they are
     * merged back in so a save never silently drops them. An empty submitted
     * list therefore detaches every ASSIGNABLE permission while leaving the
     * unmanaged ones intact.
     *
     * @param  array<int, string>  $names
     */
    private function syncPermissions(Role $role, array $names): void
    {
        $preserved = $role->getPermissionNames()
            ->reject(fn (string $name): bool => $this->permissionCatalogue->isAssignable($name))
            ->all();

        $wanted = array_values(array_unique([...$names, ...$preserved]));

        $permissions = Permission::query()
            ->where('guard_name', $role->guard_name)
            ->whereIn('name', $wanted)
            ->get();

        $role->syncPermissions($permissions);
    }

    /**
     * Full-replace sync of the role's field-permission matrix rows (spec
     * 0006) to match the submitted set — delete then recreate inside the
     * caller's transaction, so a failure never half-applies. An explicit
     * empty list clears every row (mirrors syncPermissions' "remove all").
     *
     * Duplicate (resource, field) entries in the submission (which the DB
     * unique constraint would otherwise reject) are collapsed to the LAST
     * submitted one, so a malformed/duplicated payload never 500s.
     *
     * @param  array<int, array<string, mixed>>  $entries
     */
    private function syncFieldPermissions(Role $role, array $entries): void
    {
        $rows = collect($entries)
            ->keyBy(static fn (array $entry): string => "{$entry['resource']}.{$entry['field']}")
            ->map(static fn (array $entry): array => [
                'resource' => $entry['resource'],
                'field' => $entry['field'],
                'visible' => (bool) ($entry['visible'] ?? true),
                'editable' => (bool) ($entry['editable'] ?? true),
                'required' => (bool) ($entry['required'] ?? false),
            ])
            ->values();

        $role->fieldPermissions()->delete();

        if ($rows->isNotEmpty()) {
            $role->fieldPermissions()->createMany($rows);
        }
    }

    /**
     * The canonical guard shared by roles, the permission catalogue and user
     * role assignment — the guard of the User auth provider (`web`).
     *
     * We must NOT read config('auth.defaults.guard') here: an authenticated
     * request (sanctum) calls auth()->shouldUse('sanctum'), which mutates that
     * config value for the rest of the request. Spatie\Permission\Guard derives
     * the guard from the auth provider mapping instead, so it stays `web` and
     * roles always resolve against the `web` permission catalogue and the roles
     * users actually hold.
     */
    private function guardName(): string
    {
        return Guard::getDefaultName(User::class);
    }

    /**
     * Delete the given role, guarding the protected system role. The super-admin
     * role can never be deleted (deletion is a structural mutation, not a
     * membership change), so the full system-role guard applies here.
     *
     * @throws HttpException 422
     */
    public function delete(Role $role): void
    {
        if ($role->name === RoleAssignmentGuard::PRIVILEGED_ROLE) {
            abort(422, 'The super-admin role is a system role and cannot be modified or deleted.');
        }

        $role->delete();
    }

    /**
     * Prevent NAME or PERMISSION mutation of the privileged `super-admin` role: it
     * is a system role that must always retain every permission (see
     * RoleAssignmentGuard and AppServiceProvider's Gate::before).
     *
     * Scoped to name/permission mutation ONLY (ADR 0011): a membership-only update
     * of the super-admin role is allowed to proceed and is then governed by the
     * actor rule in RoleAssignmentGuard (only a super-admin actor may change
     * super-admin membership; the last super-admin is protected). This reconciles
     * the system-role guard with role-side membership management.
     *
     * @throws HttpException 422
     */
    private function guardSystemRoleMutation(Role $role, UpdateRoleData $data): void
    {
        if ($role->name !== RoleAssignmentGuard::PRIVILEGED_ROLE) {
            return;
        }

        $mutatesNameOrPermissions = $data->submittedAttributes() !== [] || $data->hasPermissions();

        if ($mutatesNameOrPermissions) {
            abort(422, 'The super-admin role is a system role and cannot be modified or deleted.');
        }
    }
}
