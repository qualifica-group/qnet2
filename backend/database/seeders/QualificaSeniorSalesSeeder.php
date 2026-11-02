<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Seeders\QualificaCatalog\SeniorSalesRoleCatalogue;
use Illuminate\Database\Seeder;

/**
 * The senior sales people (spec 0344): each listed person receives the
 * `commerciale-senior` role ON TOP of the role they already hold, which is
 * never removed. A re-run converges (spatie never assigns a role twice).
 *
 * It seeds the roles itself (QualificaRoleSeeder) so it stays runnable on its
 * own.
 */
class QualificaSeniorSalesSeeder extends Seeder
{
    public function run(): void
    {
        // Step 1: the role the senior sales people receive.
        $this->call(QualificaRoleSeeder::class);

        // Step 2: added to each listed person that has an account.
        foreach (SeniorSalesRoleCatalogue::USERS as $email) {
            $user = User::query()->where('email', $email)->first();

            if ($user === null) {
                $this->command?->warn(sprintf('%s: no account, senior sales role skipped.', $email));

                continue;
            }

            $user->assignRole(SeniorSalesRoleCatalogue::ROLE);
        }
    }
}
