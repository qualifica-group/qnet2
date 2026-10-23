<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Seeders\QualificaCatalog\StockKeeperRoleCatalogue;
use Illuminate\Database\Seeder;

/**
 * The equipment keeper (spec 0307 D-21): each listed person receives the
 * `gestione-dotazioni` role ON TOP of the role they already hold, which is
 * never removed. A re-run converges (spatie never assigns a role twice).
 *
 * It seeds the roles itself (QualificaRoleSeeder) so it stays runnable on its
 * own.
 */
class QualificaStockKeeperSeeder extends Seeder
{
    public function run(): void
    {
        // Step 1: the role the keeper receives.
        $this->call(QualificaRoleSeeder::class);

        // Step 2: added to each listed person that has an account.
        foreach (StockKeeperRoleCatalogue::USERS as $email) {
            $user = User::query()->where('email', $email)->first();

            if ($user === null) {
                $this->command?->warn(sprintf('%s: no account, equipment keeper role skipped.', $email));

                continue;
            }

            $user->assignRole(StockKeeperRoleCatalogue::ROLE);
        }
    }
}
