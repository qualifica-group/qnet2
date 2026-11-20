<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\RoleAssignmentGuard;
use Database\Seeders\Concerns\SyncsRoleMatrix;
use Database\Seeders\QualificaCatalog\DepartmentPermissionGrants as Grants;
use Database\Seeders\QualificaCatalog\DepartmentRoleCatalogue as Department;
use Database\Seeders\QualificaCatalog\DepartmentRoster;
use Database\Seeders\QualificaCatalog\HrOfficeRoleCatalogue as HrOffice;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * The department roles of the questionnaires "permessi per reparti" (spec
 * 0408): syncs `reparto-<key>` and `reparto-<key>-senior` from
 * DepartmentRoleCatalogue, then moves every person of DepartmentRoster onto
 * them: they keep only the roles of the questionnaires (D-12), every earlier
 * role and direct permission goes (D-9). Whoever is not listed is untouched.
 *
 * `php artisan db:seed --class=QualificaDepartmentRoleSeeder`. One
 * transaction; a re-run converges.
 */
class QualificaDepartmentRoleSeeder extends Seeder
{
    use SyncsRoleMatrix;

    /** @var Collection<int, string> */
    private Collection $catalogue;

    public function run(): void
    {
        // Step 1: the permission catalogue the grants resolve against.
        Artisan::call('permissions:sync');
        $this->catalogue = Permission::query()->pluck('name');

        DB::transaction(function (): void {
            // Step 2: the two roles of every department.
            foreach (Department::DEPARTMENTS as $role => $department) {
                $requestSightFromQuotes = $department['request_sight_from_quotes'] ?? true;

                $this->syncDepartmentRole($role, $department['description'], $department['grants'], requestSightFromQuotes: $requestSightFromQuotes);
                $this->syncDepartmentRole(
                    Department::senior($role),
                    $department['description'].Department::SENIOR_DESCRIPTION_SUFFIX,
                    Department::seniorGrants($department),
                    requestSightFromQuotes: $requestSightFromQuotes,
                );
            }

            // Step 3: the add-on roles of single people, without the baseline.
            foreach (Department::ADD_ON_ROLES as $role => $addOn) {
                $this->syncDepartmentRole($role, $addOn['description'], $addOn['grants'], baseline: [], assignmentNotifications: false);
            }

            // Step 4: the listed people onto their roles.
            foreach (DepartmentRoster::USERS as $email => $entry) {
                $this->assignPerson($email, $entry);
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @param  array<int, string>  $grants
     * @param  array<int, string>  $baseline
     */
    private function syncDepartmentRole(
        string $name,
        string $description,
        array $grants,
        array $baseline = Grants::BASELINE,
        bool $requestSightFromQuotes = true,
        bool $assignmentNotifications = true,
    ): void {
        $permissions = $this->withUserFormStack($this->resolvePermissions([...$baseline, ...$this->grantPermissions($grants)]), $this->catalogue);
        $role = $this->syncRole($name, $description, $permissions, $requestSightFromQuotes, $assignmentNotifications);

        // The HR department reads the user's contractual data like the HR
        // office (D-7); every other department has it hidden like a mansione.
        if (in_array(Department::HR_OFFICE_GRANT, $grants, true)) {
            $this->syncFieldPermissions($role, readonlyFields: HrOffice::READONLY_FIELDS, grantedFields: HrOffice::SENSITIVE_FIELDS);

            return;
        }

        $this->syncFieldPermissions($role, hiddenFields: HrOffice::SENSITIVE_FIELDS);
    }

    /**
     * @param  array{roles: array<int, string>, other_roles?: array<int, string>}  $entry
     */
    private function assignPerson(string $email, array $entry): void
    {
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->command?->warn(sprintf('%s: no account, skipped.', $email));

            return;
        }

        // Only the questionnaire's roles stay (D-12); a seeder never takes
        // super-admin away.
        $privileged = $user->hasRole(RoleAssignmentGuard::PRIVILEGED_ROLE) ? [RoleAssignmentGuard::PRIVILEGED_ROLE] : [];

        $user->syncRoles([...$privileged, ...($entry['other_roles'] ?? []), ...$entry['roles']]);
        // Everything comes from roles (D-9): earlier direct grants go.
        $user->syncPermissions([]);
    }

    /**
     * @param  array<int, string>  $grants
     * @return array<int, string>
     */
    private function grantPermissions(array $grants): array
    {
        return collect($grants)->flatMap(static fn (string $grant): array => Grants::GRANTS[$grant]
            ?? throw new RuntimeException(sprintf('Unknown department grant "%s".', $grant)))->all();
    }

    /**
     * Expands `<resource>.*` against the catalogue and fails loudly on a
     * permission the catalogue does not have: a typo must never become a
     * silently missing grant.
     *
     * @param  array<int, string>  $permissions
     * @return Collection<int, string>
     */
    private function resolvePermissions(array $permissions): Collection
    {
        return collect($permissions)
            ->flatMap(function (string $permission): Collection {
                $matches = Str::endsWith($permission, '.*')
                    ? $this->catalogue->filter(static fn (string $name): bool => Str::beforeLast($name, '.') === Str::beforeLast($permission, '.'))
                    : $this->catalogue->filter(static fn (string $name): bool => $name === $permission);

                return $matches->isNotEmpty()
                    ? $matches
                    : throw new RuntimeException(sprintf('Permission "%s" is not in the catalogue.', $permission));
            })
            ->unique()
            ->values();
    }
}
