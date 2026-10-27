<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\RequestManagement\RequestModule;
use Database\Seeders\QualificaCatalog\CommercialBackOfficeRoleCatalogue as BackOffice;
use Database\Seeders\QualificaCatalog\HrOfficeRoleCatalogue as HrOffice;
use Database\Seeders\QualificaCatalog\OperatorRoleCatalogue as Catalogue;
use Database\Seeders\QualificaCatalog\OwnAbsencesCatalogue as OwnAbsences;
use Database\Seeders\QualificaCatalog\StockKeeperRoleCatalogue as StockKeeper;
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
 * Next to them the HR office role (HrOfficeRoleCatalogue, spec 0283), and the
 * per-field matrix keeping the user record's contractual data to that role and
 * to `admin`; and the commercial back office role (CommercialBackOfficeRoleCatalogue,
 * spec 0299).
 *
 * Self-sufficient by design: it re-runs `permissions:sync` first, so a role can
 * never end up silently empty because the catalogue had not been bootstrapped.
 * Every role is upserted by name and FULLY synced (permissions and field
 * matrix), so a re-run converges instead of accumulating grants.
 */
class QualificaRoleSeeder extends Seeder
{
    /**
     * Hidden from every role this seeder syncs (spec 0314): the opportunity's
     * Lead is for super-admin only, who bypasses the matrix.
     *
     * @var array<string, array<int, string>>
     */
    private const array EVERY_ROLE_HIDDEN_FIELDS = [
        'opportunities' => ['lead_id'],
    ];

    public function run(): void
    {
        // Step 1: guarantee the permission catalogue the matrices filter.
        Artisan::call('permissions:sync');

        $catalogue = Permission::query()->pluck('name');

        // Step 2: one role per mansione, with its permission matrix.
        foreach (Catalogue::ROLES as $name => $role) {
            $blocks = [...$role['blocks'], ...Catalogue::EVERY_ROLE_BLOCKS];
            $synced = $this->syncRole($name, $role['description'], $this->permissionsOf($blocks, $catalogue));

            // Step 3: the per-FIELD restrictions: the user record's
            // contractual data for every mansione, plus those of the roles
            // scoped to their own requests.
            $scoped = in_array(Catalogue::OWN_REQUESTS, $role['blocks'], true);
            $this->syncFieldPermissions(
                $synced,
                hiddenFields: [...HrOffice::SENSITIVE_FIELDS, ...($scoped ? Catalogue::OWN_REQUESTS_HIDDEN_FIELDS : [])],
                readonlyFields: $scoped ? Catalogue::OWN_REQUESTS_READONLY_FIELDS : [],
            );
        }

        // Step 4: the `admin` role, outside the mansioni: the whole catalogue,
        // the contractual data granted explicitly (see grantedFields).
        $admin = $this->syncRole(Catalogue::ADMIN_ROLE, Catalogue::ADMIN_DESCRIPTION, $catalogue);
        $this->syncFieldPermissions($admin, grantedFields: HrOffice::SENSITIVE_FIELDS);

        // Step 4b: the HR office (spec 0283), held on top of a mansione.
        $hrOffice = $this->syncRole(HrOffice::ROLE, HrOffice::DESCRIPTION, $this->hrOfficePermissions($catalogue));
        $this->syncFieldPermissions($hrOffice, readonlyFields: HrOffice::READONLY_FIELDS, grantedFields: HrOffice::SENSITIVE_FIELDS);

        // Step 4c: the commercial back office (spec 0299), held on top of a
        // mansione. Only the rows every role carries: the mansione's hidden
        // rows keep applying.
        $backOffice = $this->syncRole(BackOffice::ROLE, BackOffice::DESCRIPTION, $this->backOfficePermissions($catalogue));
        $this->syncFieldPermissions($backOffice);

        // Step 4d: the equipment keeper (spec 0307 D-21), held on top of a
        // mansione, with the `stock` module only.
        $stockKeeper = $this->syncRole(StockKeeper::ROLE, StockKeeper::DESCRIPTION, $this->stockKeeperPermissions($catalogue));
        $this->syncFieldPermissions($stockKeeper);

        // Step 5: drop the roles the Italian names replaced. A model delete, so
        // spatie detaches their permissions and memberships and the field
        // matrix cascades with the row.
        Role::query()->whereIn('name', Catalogue::RETIRED_ROLES)->get()->each->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * The union of the role's building blocks, filtered against the real
     * catalogue so a permission that does not exist is never invented, plus
     * the user form stack when the union opens the Utenti section.
     *
     * @param  array<int, string>  $blocks
     * @param  Collection<int, string>  $catalogue
     * @return Collection<int, string>
     */
    private function permissionsOf(array $blocks, Collection $catalogue): Collection
    {
        $permissions = $catalogue
            ->filter(fn (string $permission): bool => collect($blocks)
                ->contains(fn (string $block): bool => $this->blockGrants($block, $permission)))
            ->values();

        return $this->withUserFormStack($permissions, $catalogue);
    }

    /**
     * The HR modules in full but the denied abilities, the extra grants, and
     * the Task, Segnatempo and Assenze every mansione holds.
     *
     * @param  Collection<int, string>  $catalogue
     * @return Collection<int, string>
     */
    private function hrOfficePermissions(Collection $catalogue): Collection
    {
        $permissions = $catalogue->filter(static fn (string $permission): bool => (
            in_array(Str::beforeLast($permission, '.'), HrOffice::MODULES, true)
                && ! in_array($permission, HrOffice::DENIED_PERMISSIONS, true)
        ) || in_array($permission, HrOffice::EXTRA_PERMISSIONS, true));

        return $this->withUserFormStack(
            $permissions->merge($this->permissionsOf(Catalogue::EVERY_ROLE_BLOCKS, $catalogue))->unique()->values(),
            $catalogue,
        );
    }

    /**
     * The back-office modules in full, the single extra abilities and the
     * read-only support resources. Task, Segnatempo and Assenze come from the
     * mansione it is held on top of.
     *
     * @param  Collection<int, string>  $catalogue
     * @return Collection<int, string>
     */
    private function backOfficePermissions(Collection $catalogue): Collection
    {
        return $catalogue->filter(fn (string $permission): bool => in_array(Str::beforeLast($permission, '.'), BackOffice::MODULES, true)
            || in_array($permission, BackOffice::EXTRA_PERMISSIONS, true)
            || $this->isSelectOnlyGrant(Str::beforeLast($permission, '.'), Str::afterLast($permission, '.'), BackOffice::SELECT_ONLY_RESOURCES))
            ->values();
    }

    /**
     * @param  Collection<int, string>  $catalogue
     * @return Collection<int, string>
     */
    private function stockKeeperPermissions(Collection $catalogue): Collection
    {
        return $catalogue
            ->filter(static fn (string $permission): bool => in_array(Str::beforeLast($permission, '.'), StockKeeper::MODULES, true))
            ->values();
    }

    /**
     * @param  Collection<int, string>  $permissions
     * @param  Collection<int, string>  $catalogue
     * @return Collection<int, string>
     */
    private function withUserFormStack(Collection $permissions, Collection $catalogue): Collection
    {
        return $permissions->contains(Catalogue::USER_FORM_GATE)
            ? $permissions->merge($catalogue->filter($this->isUserFormGrant(...)))->unique()->values()
            : $permissions;
    }

    /**
     * Derived from the role's permissions rather than from a block: whichever
     * block opens the Utenti section, the form must load in full.
     */
    private function isUserFormGrant(string $permission): bool
    {
        return in_array(Str::beforeLast($permission, '.'), Catalogue::USER_FORM_MODULES, true)
            && in_array(Str::afterLast($permission, '.'), Catalogue::USER_FORM_ABILITIES, true);
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
            Catalogue::REQUEST_STATISTICS => $permission === RequestModule::STATISTICS_PERMISSION,
            Catalogue::FIELD_CHANGE_REVIEW => $resource === Catalogue::FIELD_CHANGE_MODULE,
            Catalogue::ALL_ENROLLEES => $resource === Catalogue::ENROLLEE_MODULE,
            Catalogue::ENROLLEES_READ => $resource === Catalogue::ENROLLEE_MODULE
                && in_array($ability, Catalogue::ENROLLEES_READ_ABILITIES, true),
            Catalogue::SITE_ENROLLEES => $permission === Catalogue::ENROLLEE_MODULE.'.viewSite',
            Catalogue::PRIMARY_SITE_ENROLLEES => $permission === Catalogue::ENROLLEE_MODULE.'.'.RequestModule::PRIMARY_SITE_ABILITY,
            Catalogue::OWN_TASKS => ($resource === Catalogue::TASK_MODULE
                    && ! in_array($ability, Catalogue::OWN_TASKS_DENIED_ABILITIES, true))
                || in_array($permission, Catalogue::COLLABORATION_PERMISSIONS, true),
            Catalogue::OWN_TIME_ENTRIES => $resource === Catalogue::TIME_ENTRY_MODULE
                && ! in_array($ability, Catalogue::OWN_TIME_ENTRIES_DENIED_ABILITIES, true),
            OwnAbsences::BLOCK => in_array($permission, OwnAbsences::PERMISSIONS, true),
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
     * Replaces the role's WHOLE field-permission matrix with the three lists,
     * plus EVERY_ROLE_HIDDEN_FIELDS — a full sync, exactly like
     * RoleService::syncFieldPermissions() on the admin UI path. ONE writer for
     * every degree, because the delete-then-create is what makes the sync
     * convergent.
     *
     * A granted row restricts nothing (the matrix intersects the code
     * ceiling, spec 0006), but it is not the same as no row: the matrix is the
     * union of the actor's roles that HAVE a row, so only an explicit grant
     * outweighs the hidden row of another role the same person holds.
     *
     * @param  array<string, array<int, string>>  $hiddenFields  resource => field keys the role never sees
     * @param  array<string, array<int, string>>  $readonlyFields  resource => field keys the role sees but cannot write
     * @param  array<string, array<int, string>>  $grantedFields  resource => field keys the role sees and writes
     */
    private function syncFieldPermissions(Role $role, array $hiddenFields = [], array $readonlyFields = [], array $grantedFields = []): void
    {
        $role->fieldPermissions()->delete();

        $hiddenFields = array_merge_recursive(self::EVERY_ROLE_HIDDEN_FIELDS, $hiddenFields);
        $this->createFieldPermissions($role, $hiddenFields, visible: false, editable: false);
        $this->createFieldPermissions($role, $readonlyFields, visible: true, editable: false);
        $this->createFieldPermissions($role, $grantedFields, visible: true, editable: true);
    }

    /**
     * @param  array<string, array<int, string>>  $fieldsByResource
     */
    private function createFieldPermissions(Role $role, array $fieldsByResource, bool $visible, bool $editable): void
    {
        foreach ($fieldsByResource as $resource => $fields) {
            foreach ($fields as $field) {
                $role->fieldPermissions()->create([
                    'resource' => $resource,
                    'field' => $field,
                    'visible' => $visible,
                    'editable' => $editable,
                    'required' => false,
                ]);
            }
        }
    }
}
