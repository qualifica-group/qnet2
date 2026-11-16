<?php

namespace Database\Seeders;

use App\Models\Attribute;
use Illuminate\Database\Seeder;

/**
 * One-shot (user request 2026-11-16):
 * `php artisan db:seed --class=InspectionDateGridColumnSeeder --force`.
 *
 * The "Verifiche ispettive" tables of the commessa show in the grid only the
 * date of the active row, under "Data verifica ispettiva": sets
 * `summary.grid_label` on the legacy-migrated `orderiso_verificas` and the
 * catalogue's `iso_inspections`, every other key left as it is. The legacy
 * export and the ISO catalogue carry the same label, so a fresh import or
 * seed already has it. Re-running it changes nothing.
 */
class InspectionDateGridColumnSeeder extends Seeder
{
    public const string GRID_LABEL = 'Data verifica ispettiva';

    /** @var list<string> */
    public const array ATTRIBUTE_CODES = ['orderiso_verificas', 'iso_inspections'];

    public function run(): void
    {
        $updated = 0;

        foreach (Attribute::query()->whereIn('code', self::ATTRIBUTE_CODES)->get() as $attribute) {
            $config = $attribute->config ?? [];

            if (! is_array($config['summary'] ?? null)) {
                $this->command?->warn("{$attribute->code}: no summary, skipped.");

                continue;
            }

            $config['summary']['grid_label'] = self::GRID_LABEL;
            $attribute->update(['config' => $config]);
            $updated++;
        }

        $this->command?->info("Colonne aggiornate: {$updated}.");
    }
}
