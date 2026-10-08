<?php

namespace Database\Seeders;

use App\Enums\LocaleEnum;
use App\Models\User;
use Database\Seeders\Concerns\AssignsSeedPassword;
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
 * account keeps its role, name and password — they were decided elsewhere
 * (the roster, the admin UI) and are never overwritten. The one write on an
 * existing account is a MISSING employment profile, created with the
 * "Assegnabile" switch off (spec 0194 D-5); a profile that exists is never
 * touched. A re-run is therefore a no-op for every account it created before.
 *
 * It seeds the roles itself (QualificaRoleSeeder) so it stays runnable on its
 * own.
 */
class QualificaStaffSeeder extends Seeder
{
    use AssignsSeedPassword, SyncsPersonName;

    public function run(): void
    {
        // Step 1: the base role the accounts point at.
        $this->call(QualificaRoleSeeder::class);

        // Step 2: one account per row the database does not know yet.
        $created = 0;

        foreach (StaffRoster::USERS as [$firstName, $lastName, $email]) {
            $user = User::query()->where('email', $email)->first();

            if ($user === null) {
                $user = $this->createAccount($firstName, $lastName, $email);
                $created++;
            }

            // Staff is never assignable; an existing profile is left alone.
            $user->employment()->firstOrCreate([], ['is_assignable' => false]);
        }

        $this->command?->info(sprintf('%d staff accounts created, %d already present.', $created, count(StaffRoster::USERS) - $created));
    }

    private function createAccount(string $firstName, string $lastName, string $email): User
    {
        $user = new User;
        $user->email = $email;
        $user->name = "{$firstName} {$lastName}";
        $user->locale = LocaleEnum::It->value;
        $user->email_verified_at = now();
        $this->assignSeedPassword($user);

        $user->save();
        $user->syncRoles([OperatorRoleCatalogue::BASE_ROLE]);
        $this->syncPersonName($user, $firstName, $lastName);

        return $user;
    }
}
