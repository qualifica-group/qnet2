<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Seeders\QualificaCatalog\CommercialBackOfficeRoleCatalogue;
use Illuminate\Database\Seeder;

/**
 * The commercial back office (spec 0299): each listed person receives the
 * `back-office-commerciale` role ON TOP of the role they already hold, which
 * is never removed. A re-run converges (spatie never assigns a role twice).
 *
 * It seeds the roles itself (QualificaRoleSeeder) so it stays runnable on its
 * own.
 */
class QualificaCommercialBackOfficeSeeder extends Seeder
{
    public function run(): void
    {
        // Step 1: the role the back office receives.
        $this->call(QualificaRoleSeeder::class);

        // Step 2: added to each listed person that has an account.
        foreach (CommercialBackOfficeRoleCatalogue::USERS as $email) {
            $user = User::query()->where('email', $email)->first();

            if ($user === null) {
                $this->command?->warn(sprintf('%s: no account, commercial back office role skipped.', $email));

                continue;
            }

            $user->assignRole(CommercialBackOfficeRoleCatalogue::ROLE);
        }
    }
}
