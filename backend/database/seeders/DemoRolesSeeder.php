<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Spatie\Permission\Guard;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seed the non-privileged application roles (admin, manager, operator, user,
 * viewer) with their permission matrices. These are demo/development fixtures:
 * the clean default seed only creates the privileged `super-admin` role
 * (RolePermissionSeeder), so this runs on demand via DemoDataSeeder.
 * Requires the permission catalogue to already exist (RolePermissionSeeder).
 */
class DemoRolesSeeder extends Seeder
{
    public function run(): void
    {
        $allPermissions = Permission::query()->pluck('name');

        $this->syncRole('admin', $allPermissions);
        $this->syncRole('manager', $this->managerPermissions($allPermissions));
        $this->syncRole('operator', $this->operatorPermissions($allPermissions));
        $this->syncRole('user', $this->userPermissions($allPermissions));
        $this->syncRole('viewer', $this->viewerPermissions($allPermissions));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function syncRole(string $name, Collection $permissions): void
    {
        $role = Role::findOrCreate($name, Guard::getDefaultName(User::class));
        $role->syncPermissions($permissions->all());
    }

    private function managerPermissions(Collection $allPermissions): Collection
    {
        return $this->resourceAbilities(
            $allPermissions,
            [
                'users' => ['viewAny', 'view', 'create', 'update'],
                'roles' => ['viewAny', 'view'],
                'personal_data' => ['viewAny', 'view'],
                'attachments' => ['viewAny', 'view'],
                'companies' => ['viewAny', 'view', 'create', 'update'],
                // Registries (spec 0020, "Anagrafiche"): an anagraphic entity
                // analogous to companies — same role policy.
                'registries' => ['viewAny', 'view', 'create', 'update'],
                'operational-sites' => ['viewAny', 'view', 'create', 'update'],
                'business-functions' => ['viewAny', 'view', 'create', 'update'],
            ],
        );
    }

    private function operatorPermissions(Collection $allPermissions): Collection
    {
        return $this->resourceAbilities(
            $allPermissions,
            [
                'users' => ['viewAny', 'view'],
                'personal_data' => ['viewAny', 'view', 'create', 'update'],
                'attachments' => ['viewAny', 'view', 'create', 'update'],
                'companies' => ['viewAny', 'view'],
                // Registries (spec 0020): same role policy as companies.
                'registries' => ['viewAny', 'view'],
                'operational-sites' => ['viewAny', 'view'],
                'business-functions' => ['viewAny', 'view'],
            ],
        );
    }

    private function userPermissions(Collection $allPermissions): Collection
    {
        return $this->resourceAbilities(
            $allPermissions,
            [
                'personal_data' => ['viewAny', 'view'],
                'attachments' => ['viewAny', 'view'],
            ],
        );
    }

    private function viewerPermissions(Collection $allPermissions): Collection
    {
        return $allPermissions->filter(
            static fn (string $permission): bool => str_ends_with($permission, '.viewAny')
                || str_ends_with($permission, '.view')
        )->values();
    }

    /**
     * @param  array<string, array<int, string>>  $matrix
     */
    private function resourceAbilities(Collection $allPermissions, array $matrix): Collection
    {
        $wanted = collect($matrix)
            ->flatMap(static fn (array $abilities, string $resource): array => array_map(
                static fn (string $ability): string => "{$resource}.{$ability}",
                $abilities,
            ));

        return $allPermissions->intersect($wanted)->values();
    }
}
