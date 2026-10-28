<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Seeders\QualificaCatalog\OperatorRoleCatalogue;
use Database\Seeders\QualificaCatalog\OperatorRoster;
use Database\Seeders\QualificaCatalog\StaffRoster;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Wipes EVERY role and permission (assignments, direct grants and field
 * matrices included) and rebuilds them as QualificaProductionDataSeeder does:
 * `php artisan db:seed --class=QualificaRoleResetSeeder`. Then resets the
 * assignment scopes too (QualificaAssignmentScopeResetSeeder).
 *
 * No account is created, and no password or Sede is touched, so the steps of
 * the production seed that also write those
 * (TestUsersSeeder, QualificaOperatorSeeder, QualificaStaffSeeder) are
 * replaced here by their role assignment alone. A roster email with no
 * account is skipped with a warning. Whoever holds no role in any roster is
 * left without one, exactly as after the production seed.
 *
 * One transaction: a failure halfway never leaves the installation without
 * roles.
 */
class QualificaRoleResetSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            // Step 1: nothing of the old catalogue survives.
            $this->wipeRolesAndPermissions();

            // Step 2: the permission catalogue, super-admin and the mansioni,
            // `admin` and office roles.
            Artisan::call('permissions:sync');
            Artisan::call('roles:create-super-admin');
            $this->call(QualificaRoleSeeder::class);

            // Step 3: the roles of the production seed's steps 4, 6 and 7.
            foreach (TestUsersSeeder::TEST_USERS as $account) {
                $this->syncRoleOf($account['email'], $account['role']);
            }

            foreach (OperatorRoster::OPERATORS as [, , $email, , $role]) {
                $this->syncRoleOf($email, $role);
            }

            $this->assignBaseRoleToStaff();

            // Step 4: the office roles on top, then the legacy-derived roles
            // for whoever holds the base role only.
            $this->call(QualificaHrOfficeSeeder::class);
            $this->call(QualificaCommercialBackOfficeSeeder::class);
            $this->call(QualificaStockKeeperSeeder::class);
            $this->call(QualificaLegacyRoleSeeder::class);

            // Step 5: the assignment scopes.
            $this->call(QualificaAssignmentScopeResetSeeder::class);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->reportUsersWithoutRole();
    }

    /**
     * Pivots first, then the field matrix, then the rows they point at.
     */
    private function wipeRolesAndPermissions(): void
    {
        $tables = config('permission.table_names');

        foreach (['model_has_permissions', 'model_has_roles', 'role_has_permissions'] as $pivot) {
            DB::table($tables[$pivot])->delete();
        }

        DB::table('role_field_permissions')->delete();
        DB::table($tables['roles'])->delete();
        DB::table($tables['permissions'])->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function syncRoleOf(string $email, string $role): void
    {
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->command?->warn(sprintf('%s: no account, role "%s" skipped.', $email, $role));

            return;
        }

        $user->syncRoles([$role]);
    }

    /**
     * Only to whoever has no role yet: a staff email the mansionario or the
     * privileged accounts already covered keeps that role.
     */
    private function assignBaseRoleToStaff(): void
    {
        foreach (StaffRoster::USERS as [, , $email]) {
            $user = User::query()->with('roles')->where('email', $email)->first();

            if ($user !== null && $user->roles->isEmpty()) {
                $user->assignRole(OperatorRoleCatalogue::BASE_ROLE);
            }
        }
    }

    private function reportUsersWithoutRole(): void
    {
        $emails = User::query()->where('is_active', true)->doesntHave('roles')->orderBy('email')->pluck('email');

        if ($emails->isNotEmpty()) {
            $this->command?->warn(sprintf('%d active accounts hold no role: %s', $emails->count(), $emails->implode(', ')));
        }
    }
}
