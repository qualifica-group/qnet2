<?php

namespace Database\Seeders;

use App\Models\EmploymentProductLine;
use App\Models\EmploymentProfile;
use App\Models\User;
use Database\Seeders\Concerns\SyncsRosterCompetence;
use Database\Seeders\QualificaCatalog\OperatorRoster;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Wipes EVERY "Ambito di assegnazione" (the competence rows and the "Ambito
 * su tutte le categorie" switch) and rebuilds them as
 * QualificaProductionDataSeeder does:
 * `php artisan db:seed --class=QualificaAssignmentScopeResetSeeder`.
 * QualificaRoleResetSeeder runs it as its last step.
 *
 * Scopes only: no account is created, and roles, Sedi and the "Assegnabile"
 * switch are left alone, so the production seed's QualificaOperatorSeeder is
 * replaced here by its competence write alone. A roster email with no account
 * is skipped with a warning.
 *
 * One transaction: a failure halfway never leaves the installation without
 * scopes.
 */
class QualificaAssignmentScopeResetSeeder extends Seeder
{
    use SyncsRosterCompetence;

    public function run(): void
    {
        DB::transaction(function (): void {
            // Step 1: nothing of the old scopes survives.
            EmploymentProductLine::query()->delete();
            EmploymentProfile::query()->where('covers_all_product_categories', true)->update(['covers_all_product_categories' => false]);

            // Step 2: the mansionario's competence (production step 6).
            $this->loadCompetenceLookups();

            foreach (OperatorRoster::OPERATORS as [, , $email, , , , , $categories]) {
                $user = User::query()->where('email', $email)->first();

                if ($user === null) {
                    $this->command?->warn(sprintf('%s: no account, assignment scope skipped.', $email));

                    continue;
                }

                $this->syncRosterCompetence($user->employment()->firstOrCreate([]), $email, $categories);
            }

            // Step 3: each business function member's function on every
            // category (production step 11).
            $this->call(QualificaFunctionCompetenceSeeder::class);
        });
    }
}
