<?php

namespace Database\Seeders;

use App\Enums\LocaleEnum;
use App\Models\User;
use App\Services\RoleAssignmentGuard;
use Database\Seeders\Concerns\SyncsPersonName;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\PermissionRegistrar;

/**
 * The named super-admin account of the installation. The tester roster and the
 * application roles it used to carry moved out (user directive 2026-09-15): the
 * real operators are QualificaOperatorSeeder's, their roles
 * QualificaRoleSeeder's.
 *
 * Kept as its own step because QualificaLegacyImportSeeder acts on behalf of a
 * super-admin, and must run BEFORE the operators (they need the imported
 * sites). Self-sufficient: it re-runs `permissions:sync` and
 * `roles:create-super-admin` first. Idempotent: upserted by email, with the
 * first and last name written onto the account's anagrafica too.
 */
class TestUsersSeeder extends Seeder
{
    use SyncsPersonName;

    /**
     * @var array<int, array{first_name: string, last_name: string, email: string, role: string}>
     */
    public const array TEST_USERS = [
        [
            'first_name' => 'Ciro',
            'last_name' => 'Cacciapuoti',
            'email' => 'ciro.cacciapuoti@qualificagroup.com',
            'role' => RoleAssignmentGuard::PRIVILEGED_ROLE,
        ],
    ];

    public function run(): void
    {
        // Step 1: guarantee the catalogue and the privileged role.
        Artisan::call('permissions:sync');
        Artisan::call('roles:create-super-admin');

        // Step 2: the accounts themselves, upserted by email.
        foreach (self::TEST_USERS as $account) {
            $this->seedAccount($account['first_name'], $account['last_name'], $account['email'], $account['role']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * The password is rewritten on EVERY run, not only on creation: the account
     * is handed out with one documented shared credential, so a re-seed must be
     * able to restore it.
     */
    private function seedAccount(string $firstName, string $lastName, string $email, string $role): void
    {
        $user = User::firstOrNew(['email' => $email]);
        $user->name = "{$firstName} {$lastName}";
        $user->locale = LocaleEnum::It->value;
        $user->email_verified_at ??= now();
        $user->password = config('seeding.password');

        $user->save();
        $user->syncRoles([$role]);
        $this->syncPersonName($user, $firstName, $lastName);
    }
}
