<?php

declare(strict_types=1);

namespace App\Authorization;

use Spatie\Permission\Models\Permission;

/**
 * The subset of the permission catalogue that is directly assignable from the
 * Role form: permissions whose resource prefix is a registered "form-module"
 * resource (config/authorization.php — users, roles, business-functions,
 * companies, operational-sites) or one of the permission-only resources of
 * the same config (an agnostic component with real permissions but no form of
 * its own: `notes`, `attachments`).
 *
 * Indirect sub-entity permissions (addresses.*, contacts.*, personal_data.*)
 * are governed via the field-permission matrix on their parent form, so they
 * are never offered here — and RoleService never drops the ones a role already
 * holds when it saves the form.
 *
 * Single source of truth shared by RolesTableDefinition (the offered catalogue,
 * for the form and the `permissions` set filter) and RoleService (preserving
 * unmanaged permissions on sync).
 */
final class AssignablePermissionCatalogue
{
    public function __construct(private readonly AuthorizationRegistry $registry) {}

    /**
     * Whether a permission name belongs to a form-module resource (or to a
     * permission-only one) and may be assigned/managed from the Role form.
     */
    public function isAssignable(string $permission): bool
    {
        $resource = $this->resourceOf($permission);

        return in_array($resource, $this->registry->resourceKeys(), true)
            || in_array($resource, $this->permissionOnlyResources(), true);
    }

    /**
     * The permission-only resources (`notes`, `attachments`): assignable but
     * with no form-module resource of their own (see config/authorization.php).
     * Public — PermissionCatalogueBuilder uses it to build the "shared" area.
     *
     * @return array<int, string>
     */
    public function permissionOnlyResources(): array
    {
        /** @var array<int, string> $resources */
        $resources = config('authorization.permission_only_resources', []);

        return $resources;
    }

    /**
     * The assignable names() grouped by resource prefix — the single place
     * that pairs the catalogue with the resource-split logic, so consumers
     * (PermissionCatalogueBuilder) never re-derive it themselves.
     *
     * @return array<string, array<int, string>>
     */
    public function namesByResource(): array
    {
        $grouped = [];

        foreach ($this->names() as $name) {
            $grouped[$this->resourceOf($name)][] = $name;
        }

        return $grouped;
    }

    /**
     * The assignable permission names present in the catalogue, ordered by
     * name. Optionally narrowed by a case-insensitive substring and capped.
     *
     * @return array<int, string>
     */
    public function names(?string $search = null, ?int $limit = null): array
    {
        /** @var array<int, string> $all */
        $all = Permission::query()->orderBy('name')->pluck('name')->all();

        $matches = array_values(array_filter(
            $all,
            fn (string $name): bool => $this->isAssignable($name)
                && ($search === null || $search === '' || stripos($name, $search) !== false),
        ));

        return $limit === null ? $matches : array_slice($matches, 0, $limit);
    }

    /**
     * The resource prefix of a permission name (`users.view` → `users`; a
     * dotless name is its own prefix).
     */
    private function resourceOf(string $permission): string
    {
        $dot = strpos($permission, '.');

        return $dot === false ? $permission : substr($permission, 0, $dot);
    }
}
