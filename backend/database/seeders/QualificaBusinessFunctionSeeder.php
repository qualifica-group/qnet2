<?php

namespace Database\Seeders;

use App\Models\BusinessFunction;
use Database\Seeders\QualificaCatalog\BusinessFunctionCatalogue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds the business functions of the client's organisation chart
 * (BusinessFunctionCatalogue, user decision 2026-10-19), before the legacy
 * import: the import adopts each one by name and gives it the legacy id, and
 * creates the legacy functions without a match as "[OLD]" and not active.
 *
 * A function already there under the same name, compared without case, is
 * kept as it is; only a missing BU/BS type is filled in, so a type set by
 * hand survives the re-run.
 */
class QualificaBusinessFunctionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (BusinessFunctionCatalogue::FUNCTIONS as $name => $type) {
            $flags = [
                'is_business_unit' => $type === BusinessFunctionCatalogue::BUSINESS_UNIT,
                'is_business_service' => $type === BusinessFunctionCatalogue::BUSINESS_SERVICE,
            ];
            $function = $this->functionNamed($name);

            if ($function === null) {
                // A per-model create, so the activity log records it.
                BusinessFunction::query()->create(['name' => $name, ...$flags]);
                $this->command?->info(sprintf('Business function "%s" created.', $name));

                continue;
            }

            if (! $function->is_business_unit && ! $function->is_business_service) {
                $function->update($flags);
            }
        }
    }

    /**
     * `name` is not unique: the lowest id wins, as in
     * QualificaBusinessFunctionLinkSeeder.
     */
    private function functionNamed(string $name): ?BusinessFunction
    {
        return BusinessFunction::query()
            ->whereRaw('LOWER(name) = ?', [Str::lower($name)])
            ->orderBy('id')
            ->first();
    }
}
