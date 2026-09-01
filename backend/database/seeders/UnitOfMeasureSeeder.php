<?php

namespace Database\Seeders;

use App\Models\UnitOfMeasure;
use Illuminate\Database\Seeder;

/**
 * Seed the conventional unit-of-measure catalogue (spec 0088, D-3). Unlike
 * every other lookup seeder (DemoVatRateSeeder, DemoPaymentMethodSeeder,
 * ...), this one is CLEAN reference data — no `Demo` prefix, called from
 * DatabaseSeeder::run() itself, not from DemoDataSeeder — because a
 * production installation needs these rows without ever running the demo
 * fixtures (decisione utente 2026-09-01).
 *
 * `code='unit'` ("Unita", symbol "pz") already exists by the time this runs:
 * 2026_09_01_100000_create_units_of_measure_table.php inserts it directly so
 * the very next migration (products backfill) has a default row to point
 * every existing product at. `firstOrCreate(['code' => ...])` finds that row
 * without duplicating it, exactly like every other row here — idempotent on
 * re-run, never overwriting a manual edit made through the `units-of-measure`
 * CRUD module.
 *
 * `symbol` is UNIQUE: "pz" is already taken by the default "Unita" row, so a
 * separate generic "Pezzi"/piece unit is deliberately NOT added here — it
 * would duplicate "Unita"'s own meaning with no distinct symbol available.
 */
class UnitOfMeasureSeeder extends Seeder
{
    /**
     * @var array<int, array{0: string, 1: string, 2: string}>
     */
    private const array UNITS = [
        ['unit', 'Unita', 'pz'],
        ['kilogram', 'Chilogrammi', 'kg'],
        ['gram', 'Grammi', 'g'],
        ['litre', 'Litri', 'l'],
        ['metre', 'Metri', 'm'],
        ['square_metre', 'Metri quadri', 'mq'],
        ['hour', 'Ore', 'h'],
        ['day', 'Giorni', 'gg'],
    ];

    public function run(): void
    {
        foreach (self::UNITS as [$code, $name, $symbol]) {
            UnitOfMeasure::firstOrCreate(['code' => $code], ['name' => $name, 'symbol' => $symbol]);
        }
    }
}
