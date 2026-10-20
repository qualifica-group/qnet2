<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Seeders\QualificaCatalog\HrOfficeRoleCatalogue;
use Database\Seeders\QualificaCatalog\OrgChartFunctions;
use Illuminate\Database\Seeder;

/**
 * The HR office (spec 0283): every head and member of the "Risorse Umane"
 * function of the Pianta Organica receives the `risorse-umane` role ON TOP of
 * the role they already hold, which is never removed. A re-run converges
 * (spatie never assigns a role twice).
 *
 * It seeds the roles itself (QualificaRoleSeeder) so it stays runnable on its
 * own.
 */
class QualificaHrOfficeSeeder extends Seeder
{
    public function run(): void
    {
        // Step 1: the role the office receives.
        $this->call(QualificaRoleSeeder::class);

        // Step 2: added to each person of the function that has an account.
        $function = OrgChartFunctions::FUNCTIONS[HrOfficeRoleCatalogue::FUNCTION];

        foreach ([...$function['heads'], ...$function['members']] as $email) {
            $user = User::query()->where('email', $email)->first();

            if ($user === null) {
                $this->command?->warn(sprintf('%s: no account, HR office role skipped.', $email));

                continue;
            }

            $user->assignRole(HrOfficeRoleCatalogue::ROLE);
        }
    }
}
