<?php

namespace App\Authorization;

use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Contracts\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Resolves a permission by name and guard from an index instead of scanning
 * the catalogue.
 *
 * The package walks every permission model on each lookup, and every Gate check
 * does one, policy abilities included (`update` is not a permission, so it scans
 * the whole catalogue to find nothing). A grid page runs hundreds of checks, so
 * the cost was checks x catalogue size: it grew with every new permission and
 * every new row action, and was about 90% of a grid request.
 */
final class IndexedPermissionRegistrar extends PermissionRegistrar
{
    private const array NAME_LOOKUP_ATTRIBUTES = ['name', 'guard_name'];

    private const string KEY_SEPARATOR = "\0";

    /** @var array<string, Permission>|null */
    private ?array $byNameAndGuard = null;

    public function getPermissions(array $params = [], bool $onlyOne = false): Collection
    {
        if (! $onlyOne || array_keys($params) !== self::NAME_LOOKUP_ATTRIBUTES) {
            return parent::getPermissions($params, $onlyOne);
        }

        $this->byNameAndGuard ??= $this->buildIndex();
        $permission = $this->byNameAndGuard[$this->key($params['name'], $params['guard_name'])] ?? null;

        return new Collection($permission ? [$permission] : []);
    }

    public function forgetCachedPermissions(): bool
    {
        $this->byNameAndGuard = null;

        return parent::forgetCachedPermissions();
    }

    public function clearPermissionsCollection(): void
    {
        $this->byNameAndGuard = null;

        parent::clearPermissionsCollection();
    }

    /**
     * @return array<string, Permission>
     */
    private function buildIndex(): array
    {
        $index = [];

        foreach (parent::getPermissions() as $permission) {
            $index[$this->key($permission->name, $permission->guard_name)] ??= $permission;
        }

        return $index;
    }

    private function key(string $name, string $guardName): string
    {
        return $name.self::KEY_SEPARATOR.$guardName;
    }
}
