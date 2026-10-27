<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Seeders\Concerns\SyncsRoleMatrix;
use Database\Seeders\QualificaCatalog\LegacyRoleCatalogue;
use Database\Seeders\QualificaCatalog\LegacyRoleRoster;
use Database\Seeders\QualificaCatalog\OperatorRoleCatalogue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * The QNet legacy roles of the active people (user directive 2026-10-27):
 * the roles of LegacyRoleCatalogue, then each person of LegacyRoleRoster gets
 * the base role plus the qnet-2 roles their legacy roles become.
 *
 * Whoever already holds a role of their own (the mansionario, an office, the
 * admin UI) is left alone: only the base role and the roles of this seeder
 * count as "no role yet". So a re-run converges on the roster for the people
 * it assigned, and never touches anyone else. An inactive or missing account
 * is skipped with a warning.
 *
 * It seeds the base and admin roles itself (QualificaRoleSeeder) so it stays
 * runnable on its own.
 */
class QualificaLegacyRoleSeeder extends Seeder
{
    use SyncsRoleMatrix;

    public function run(): void
    {
        // Step 1: the permission catalogue and the base and admin roles.
        $this->call(QualificaRoleSeeder::class);

        // Step 2: the roles derived from the legacy ones.
        $catalogue = Permission::query()->pluck('name');

        foreach (LegacyRoleCatalogue::ROLES as $name => $role) {
            $synced = $this->syncRole($name, $role['description'], $this->permissionsOf($role['permissions'], $catalogue));
            $this->syncFieldPermissions($synced);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Step 3: assigned to each listed person with no role of their own.
        $assigned = 0;

        foreach (LegacyRoleRoster::USERS as $email => $legacyRoles) {
            $user = User::query()->with('roles')->where('email', $email)->first();

            if ($this->isAssignable($email, $user)) {
                $user->syncRoles([OperatorRoleCatalogue::BASE_ROLE, ...$this->rolesOf($legacyRoles)]);
                $assigned++;
            }
        }

        $this->command?->info(sprintf('%d people received their legacy roles, %d left alone.', $assigned, count(LegacyRoleRoster::USERS) - $assigned));
    }

    /**
     * @param  array<string, string|array<int, string>>  $matrix
     * @param  Collection<int, string>  $catalogue
     * @return Collection<int, string>
     */
    private function permissionsOf(array $matrix, Collection $catalogue): Collection
    {
        $permissions = $catalogue->filter(static function (string $permission) use ($matrix): bool {
            $abilities = $matrix[Str::beforeLast($permission, '.')] ?? [];

            return $abilities === LegacyRoleCatalogue::ALL
                || in_array(Str::afterLast($permission, '.'), (array) $abilities, true);
        })->values();

        return $this->withUserFormStack($permissions, $catalogue);
    }

    /**
     * @phpstan-assert-if-true User $user
     */
    private function isAssignable(string $email, ?User $user): bool
    {
        if ($user === null || ! $user->is_active) {
            $this->command?->warn(sprintf('%s: no active account, legacy roles skipped.', $email));

            return false;
        }

        $managed = [OperatorRoleCatalogue::BASE_ROLE, ...array_keys(LegacyRoleCatalogue::ROLES)];

        return $user->roles->pluck('name')->diff($managed)->isEmpty();
    }

    /**
     * @param  array<int, string>  $legacyRoles
     * @return array<int, string>
     */
    private function rolesOf(array $legacyRoles): array
    {
        return collect($legacyRoles)
            ->flatMap(static fn (string $legacyRole): array => LegacyRoleCatalogue::LEGACY_ROLES[$legacyRole])
            ->unique()
            ->values()
            ->all();
    }
}
