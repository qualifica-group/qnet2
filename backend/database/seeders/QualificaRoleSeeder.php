<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\QualificaCatalog\OperatorRoleCatalogue as Catalogue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Spatie\Permission\Guard;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * The application roles of the client's operator roster ("Mansionario
 * Operatori", user directive 2026-09-15): each role is the permission matrix
 * of one mansione, composed from the building blocks declared in
 * OperatorRoleCatalogue. QualificaOperatorSeeder assigns them.
 *
 * Self-sufficient by design: it re-runs `permissions:sync` first, so a role can
 * never end up silently empty because the catalogue had not been bootstrapped.
 * Every role is upserted by name and FULLY synced (permissions and field
 * matrix), so a re-run converges instead of accumulating grants.
 */
class QualificaRoleSeeder extends Seeder
{
    public function run(): void
    {
        // Step 1: guarantee the permission catalogue the matrices filter.
        Artisan::call('permissions:sync');

        $catalogue = Permission::query()->pluck('name');

        // Step 2: one role per mansione, with its permission matrix.
        foreach (Catalogue::ROLES as $name => $role) {
            $synced = $this->syncRole($name, $role['description'], $this->permissionsOf($role['blocks'], $catalogue));

            // Step 3: the per-FIELD restrictions of the roles scoped to their
            // own requests (empty for every other role, so a re-run also
            // clears a matrix left behind by an older definition).
            $scoped = in_array(Catalogue::OWN_REQUESTS, $role['blocks'], true);
            $this->syncFieldPermissions(
                $synced,
                $scoped ? Catalogue::OWN_REQUESTS_HIDDEN_FIELDS : [],
                $scoped ? Catalogue::OWN_REQUESTS_READONLY_FIELDS : [],
            );
        }

        // Step 4: drop the roles the Italian names replaced. A model delete, so
        // spatie detaches their permissions and memberships and the field
        // matrix cascades with the row.
        Role::query()->whereIn('name', Catalogue::RETIRED_ROLES)->get()->each->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * The union of the role's building blocks, filtered against the real
     * catalogue so a permission that does not exist is never invented.
     *
     * @param  array<int, string>  $blocks
     * @param  Collection<int, string>  $catalogue
     * @return Collection<int, string>
     */
    private function permissionsOf(array $blocks, Collection $catalogue): Collection
    {
        return $catalogue
            ->filter(fn (string $permission): bool => collect($blocks)
                ->contains(fn (string $block): bool => $this->blockGrants($block, $permission)))
            ->values();
    }

    private function blockGrants(string $block, string $permission): bool
    {
        $resource = Str::beforeLast($permission, '.');
        $ability = Str::afterLast($permission, '.');

        return match ($block) {
            Catalogue::MARKETING => in_array($resource, Catalogue::MARKETING_MODULES, true)
                || $this->isSelectOnlyGrant($resource, $ability, Catalogue::MARKETING_SELECT_ONLY_RESOURCES),
            Catalogue::LEAD_CONVERSION => in_array($permission, Catalogue::LEAD_CONVERSION_PERMISSIONS, true),
            Catalogue::ALL_REQUESTS => $resource === Catalogue::REQUEST_MODULE
                || in_array($permission, Catalogue::REQUEST_EXTRA_PERMISSIONS, true)
                || $this->isSelectOnlyGrant($resource, $ability, Catalogue::ALL_REQUESTS_SELECT_ONLY_RESOURCES),
            Catalogue::OWN_REQUESTS => ($resource === Catalogue::REQUEST_MODULE
                    && ! in_array($ability, Catalogue::OWN_REQUESTS_DENIED_ABILITIES, true))
                || in_array($permission, Catalogue::REQUEST_EXTRA_PERMISSIONS, true)
                || $permission === Catalogue::FIELD_CHANGE_PROPOSAL
                || $this->isSelectOnlyGrant($resource, $ability, Catalogue::OWN_REQUESTS_SELECT_ONLY_RESOURCES),
            Catalogue::CATALOG_AND_REGISTRIES => in_array($resource, Catalogue::CATALOG_AND_REGISTRIES_MODULES, true)
                || $this->isSelectOnlyGrant($resource, $ability, Catalogue::CATALOG_AND_REGISTRIES_SELECT_ONLY_RESOURCES),
            Catalogue::STATUS_CONFIGURATOR => in_array($resource, Catalogue::STATUS_CONFIGURATOR_MODULES, true),
            Catalogue::REWARDS => in_array($resource, Catalogue::REWARDS_MODULES, true),
            Catalogue::USERS_AND_ROLES => in_array($resource, Catalogue::USERS_AND_ROLES_MODULES, true)
                && ! in_array($ability, Catalogue::USERS_AND_ROLES_DENIED_ABILITIES, true),
            Catalogue::SITE_REQUESTS => $permission === Catalogue::REQUEST_MODULE.'.viewSite',
            Catalogue::FIELD_CHANGE_REVIEW => $resource === Catalogue::FIELD_CHANGE_MODULE,
            Catalogue::ALL_ENROLLEES => $resource === Catalogue::ENROLLEE_MODULE,
            Catalogue::ENROLLEES_READ => $resource === Catalogue::ENROLLEE_MODULE
                && in_array($ability, Catalogue::ENROLLEES_READ_ABILITIES, true),
            Catalogue::SITE_ENROLLEES => $permission === Catalogue::ENROLLEE_MODULE.'.viewSite',
        };
    }

    /**
     * @param  array<int, string>  $resources
     */
    private function isSelectOnlyGrant(string $resource, string $ability, array $resources): bool
    {
        return $ability === 'viewAny' && in_array($resource, $resources, true);
    }

    /**
     * @param  Collection<int, string>  $permissions
     */
    private function syncRole(string $name, string $description, Collection $permissions): Role
    {
        $role = Role::findOrCreate($name, Guard::getDefaultName(User::class));
        $role->description = $description;
        $role->save();
        $role->syncPermissions($permissions->all());

        return $role;
    }

    /**
     * Replaces the role's WHOLE field-permission matrix with the two lists — a
     * full sync, exactly like RoleService::syncFieldPermissions() on the admin
     * UI path. ONE writer for both degrees, because the delete-then-create is
     * what makes the sync convergent.
     *
     * @param  array<string, array<int, string>>  $hiddenFields  resource => field keys the role never sees
     * @param  array<string, array<int, string>>  $readonlyFields  resource => field keys the role sees but cannot write
     */
    private function syncFieldPermissions(Role $role, array $hiddenFields, array $readonlyFields): void
    {
        $role->fieldPermissions()->delete();

        $this->createFieldPermissions($role, $hiddenFields, visible: false);
        $this->createFieldPermissions($role, $readonlyFields, visible: true);
    }

    /**
     * @param  array<string, array<int, string>>  $fieldsByResource
     */
    private function createFieldPermissions(Role $role, array $fieldsByResource, bool $visible): void
    {
        foreach ($fieldsByResource as $resource => $fields) {
            foreach ($fields as $field) {
                $role->fieldPermissions()->create([
                    'resource' => $resource,
                    'field' => $field,
                    'visible' => $visible,
                    // Never editable: the matrix intersects the code ceiling
                    // (spec 0006), a seed only ever RESTRICTS it.
                    'editable' => false,
                    'required' => false,
                ]);
            }
        }
    }
}
