<?php

namespace Database\Seeders;

use App\Models\EmploymentProductLine;
use App\Models\EmploymentProfile;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Every member of an active business function gets that function as an
 * "Ambito di assegnazione" on every category (user directive 2026-10-27):
 * one (function, null) row, spec 0129 D-3.
 *
 * ADDITIVE: the rows of the other functions are kept. The function's own
 * category rows are replaced, since the function-wide row already covers them
 * and the user form would refuse them as redundant (spec 0129 D-4). A profile
 * on "Ambito su tutte le categorie" is left alone: it already covers
 * everything and must keep no row (D-2). A member without a profile gets one.
 *
 * Runs after every step that writes memberships or competence rows
 * (QualificaLegacyImportSeeder, QualificaOperatorSeeder, which recreates its
 * operators' rows on each run, QualificaHrCatalogSeeder), so a re-run converges.
 */
class QualificaFunctionCompetenceSeeder extends Seeder
{
    public function run(): void
    {
        $activeFunctions = static fn ($query) => $query->where('is_active', true);

        $users = User::query()
            ->whereHas('businessFunctions', $activeFunctions)
            ->with(['businessFunctions' => $activeFunctions, 'employment.productLines'])
            ->get();
        $added = 0;

        foreach ($users as $user) {
            // A staff account may have no profile yet: the rows hang off one.
            $employment = $user->employment ?? $user->employment()->create();

            if ($employment->covers_all_product_categories) {
                continue;
            }

            foreach ($user->businessFunctions as $function) {
                $added += (int) $this->addFunctionWideLine($employment, $function->id);
            }
        }

        $this->command?->info(sprintf('%d function-wide assignment scopes added.', $added));
    }

    private function addFunctionWideLine(EmploymentProfile $employment, int $functionId): bool
    {
        $lines = $employment->productLines->where('business_function_id', $functionId);

        if ($lines->contains(static fn (EmploymentProductLine $line): bool => $line->product_category_id === null)) {
            return false;
        }

        $employment->productLines()->where('business_function_id', $functionId)->delete();
        $employment->productLines()->create([
            'business_function_id' => $functionId,
            'product_category_id' => null,
        ]);

        return true;
    }
}
