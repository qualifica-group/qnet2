<?php

namespace Database\Seeders;

use App\Enums\LocaleEnum;
use App\Models\User;
use Database\Seeders\Concerns\SyncsPersonName;
use Database\Seeders\QualificaCatalog\OperatorRoleCatalogue;
use Database\Seeders\QualificaCatalog\StaffRoster;
use Illuminate\Database\Seeder;

/**
 * The client's staff outside the mansionario (StaffRoster, user directive
 * 2026-09-24): one account per row with the base role, which sees only Task
 * and Segnatempo, and its first and last name on the anagrafica.
 *
 * CREATE-ONLY, unlike QualificaOperatorSeeder: an email that already has an
 * account is skipped entirely — its role, name and password were decided
 * elsewhere (the roster, the admin UI) and are never overwritten. A re-run is
 * therefore a no-op for every account it created before.
 *
 * It seeds the roles itself (QualificaRoleSeeder) so it stays runnable on its
 * own.
 */
class QualificaStaffSeeder extends Seeder
{
    use SyncsPersonName;

    public function run(): void
    {
        // Step 1: the base role the accounts point at.
        $this->call(QualificaRoleSeeder::class);

        // Step 2: one account per row the database does not know yet.
        $created = 0;

        foreach (StaffRoster::USERS as [$firstName, $lastName, $email]) {
            if (User::query()->where('email', $email)->exists()) {
                continue;
            }

            $this->createAccount($firstName, $lastName, $email);
            $created++;
        }

        $this->command?->info(sprintf('%d staff accounts created, %d already present.', $created, count(StaffRoster::USERS) - $created));
    }

    private function createAccount(string $firstName, string $lastName, string $email): void
    {
        $user = new User;
        $user->email = $email;
        $user->name = "{$firstName} {$lastName}";
        $user->locale = LocaleEnum::It->value;
        $user->email_verified_at = now();
        $user->password = config('seeding.password');

        $user->save();
        $user->syncRoles([OperatorRoleCatalogue::BASE_ROLE]);
        $this->syncPersonName($user, $firstName, $lastName);
    }
}
