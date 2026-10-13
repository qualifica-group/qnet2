<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Single source of truth for role-assignment privilege guards (ADR 0011).
 *
 * Both flows depend on this collaborator so the privilege-escalation control
 * exists in exactly ONE place and cannot drift:
 *
 * - User side (UserService): which role NAMES an actor may assign to a user, and
 *   the last-super-admin protection when a user loses the role.
 * - Role side (RoleService): which user IDS an actor may set as members of a role,
 *   and the last-super-admin protection when the super-admin role's membership
 *   shrinks.
 *
 * Rule, identical on both sides: only a super-admin actor may grant or revoke the
 * privileged `super-admin` role; a non-super-admin actor's super-admin changes are
 * filtered out (never error-as-escalation), and the LAST super-admin is always
 * protected. The guard is the final authority even if the request layer is
 * bypassed (e.g. a direct service call).
 */
class RoleAssignmentGuard
{
    /**
     * The privileged role that grants every ability via Gate::before
     * (see AppServiceProvider). Only a super-admin actor may assign it.
     */
    public const string PRIVILEGED_ROLE = 'super-admin';

    /**
     * Role names the given actor is allowed to assign (single source of truth,
     * shared by the user FormRequests, UserService and UsersTableDefinition).
     *
     * A super-admin may assign any role; anyone else may assign every role
     * EXCEPT the privileged `super-admin` role, preventing privilege escalation.
     *
     * @return array<int, string>
     */
    public function assignableRoleNames(User $actor): array
    {
        return array_values($this->assignableRoleMap($actor));
    }

    /**
     * The assignable roles as an id => name map (same authority/exclusion as
     * {@see assignableRoleNames}). Used by the user FormRequests to validate the
     * submitted role IDS and resolve them back to the names the guard/service
     * operate on, and to feed the role-id ↔ name boundary without a second query.
     *
     * @return array<int, string>
     */
    public function assignableRoleMap(User $actor): array
    {
        $query = Role::query()->orderBy('name');

        if (! $this->actorIsSuperAdmin($actor)) {
            $query->where('name', '!=', self::PRIVILEGED_ROLE);
        }

        /** @var array<int, string> $map */
        $map = $query->pluck('name', 'id')->all();

        return $map;
    }

    /**
     * Re-filter requested role NAMES against the actor's assignable set (user
     * side), so a non-super-admin can never assign `super-admin` even if the
     * request layer is bypassed.
     *
     * @param  array<int, string>  $requested
     * @return array<int, string>
     */
    public function authorizedRoleNames(User $actor, array $requested): array
    {
        $assignable = $this->assignableRoleNames($actor);

        return array_values(array_filter(
            $requested,
            static fn ($role): bool => is_string($role) && in_array($role, $assignable, true),
        ));
    }

    /**
     * The user IDS an actor may set as the members of the given role (role side).
     *
     * For a normal role the requested ids pass through (membership of a normal
     * role is just `roles.update`, already authorized). For the privileged
     * `super-admin` role only a super-admin actor may change membership: a
     * non-super-admin actor's request is rejected by returning the role's CURRENT
     * member ids unchanged — mirroring how the user side filters out `super-admin`
     * rather than escalating.
     *
     * @param  array<int, int>  $requestedUserIds
     * @return array<int, int>
     */
    public function authorizedUserIdsForRole(User $actor, Role $role, array $requestedUserIds): array
    {
        $requested = array_values(array_unique(array_map('intval', $requestedUserIds)));

        if ($role->name === self::PRIVILEGED_ROLE && ! $this->actorIsSuperAdmin($actor)) {
            return $this->currentMemberIds($role);
        }

        return $requested;
    }

    /**
     * Prevent stripping the `super-admin` role from the last super-admin via a
     * user update (would lock out all privileged access). User side.
     *
     * @param  array<int, string>  $newRoleNames
     *
     * @throws HttpException 422
     */
    public function guardLastSuperAdminRoleRemoval(User $user, array $newRoleNames): void
    {
        $losingPrivilege = $user->hasRole(self::PRIVILEGED_ROLE)
            && ! in_array(self::PRIVILEGED_ROLE, $newRoleNames, true);

        if ($losingPrivilege && $this->superAdminCount() <= 1) {
            abort(422, 'Cannot remove the super-admin role from the last super-admin.');
        }
    }

    /**
     * Prevent shrinking the `super-admin` role's membership below the last
     * super-admin from the role side. Only relevant when the role being synced IS
     * `super-admin`. Role side counterpart of guardLastSuperAdminRoleRemoval.
     *
     * @param  array<int, int>  $newUserIds
     *
     * @throws HttpException 422
     */
    public function guardLastSuperAdminMembershipShrink(Role $role, array $newUserIds): void
    {
        if ($role->name !== self::PRIVILEGED_ROLE) {
            return;
        }

        $current = $this->currentMemberIds($role);
        $removed = array_diff($current, array_map('intval', $newUserIds));

        if ($removed !== [] && $this->superAdminCount() <= count($removed)) {
            abort(422, 'Cannot remove the super-admin role from the last super-admin.');
        }
    }

    /**
     * Prevent deleting the last remaining super-admin user (account lockout).
     *
     * @throws HttpException 422
     */
    public function guardLastSuperAdminDeletion(User $user): void
    {
        if ($user->hasRole(self::PRIVILEGED_ROLE) && $this->superAdminCount() <= 1) {
            abort(422, 'Cannot delete the last super-admin.');
        }
    }

    /**
     * Number of human users currently holding the privileged super-admin role
     * (the technical users of the API clients do not count: spec 0210).
     */
    public function superAdminCount(): int
    {
        return User::role(self::PRIVILEGED_ROLE)->excludingServiceAccounts()->count();
    }

    private function actorIsSuperAdmin(User $actor): bool
    {
        return $actor->hasRole(self::PRIVILEGED_ROLE);
    }

    /**
     * @return array<int, int>
     */
    private function currentMemberIds(Role $role): array
    {
        /** @var array<int, int> $ids */
        $ids = $role->users()->pluck('users.id')->map(static fn ($id): int => (int) $id)->all();

        return $ids;
    }
}
