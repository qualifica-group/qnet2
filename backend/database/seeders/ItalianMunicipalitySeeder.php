<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Province;
use Illuminate\Database\Seeder;

/**
 * Italian comuni MISSING from the reference geo dataset (`dev/DatabaseWorld/
 * world.sql`, a GeoNames "populated places" extract loaded by `locations:add`).
 *
 * Clean reference data — no `Demo` prefix, called from DatabaseSeeder::run()
 * right after `locations:add`, for the same reason as UnitOfMeasureSeeder: a
 * production install needs these rows without ever running the demo fixtures.
 *
 * The extract lists populated places, not the ISTAT comuni register, so a
 * comune born from a merge/split AFTER the snapshot is absent while its former
 * frazioni are present. Every import resolving a `comune` string against
 * `cities` (MigrationGeoResolver) then leaves `city_id` null on a perfectly
 * valid address. Each row below is a comune an import actually failed to
 * resolve; add here, do not patch world.sql.
 *
 * Idempotent (firstOrCreate on name+province): re-running never duplicates a
 * row, and never overwrites a manual edit.
 */
class ItalianMunicipalitySeeder extends Seeder
{
    private const string COUNTRY_CODE = 'IT';

    /**
     * [comune name, dataset province name]. The province is matched by the
     * dataset's own (English) spelling, the same one MigrationGeoResolver
     * resolves a plate code to.
     *
     * - Fonte Nuova (RM): comune since 1997, born from the Mentana/Guidonia
     *   split; the extract only carries its frazioni Tor Lupara and Santa Lucia.
     * - Montoro (AV): comune since 2013, born from the merger of Montoro
     *   Inferiore and Montoro Superiore.
     * - The others (spec 0189 G-28): comuni of the legacy QNet addresses the
     *   extract lacks, or files only under another province (Carpi under
     *   Reggio Emilia, Marcon under Treviso, Fontanafredda under Padua...).
     *
     * @var array<int, array{0: string, 1: string}>
     */
    private const array MUNICIPALITIES = [
        ['Fonte Nuova', 'Rome'],
        ['Buggiano', 'Pistoia'],
        ['Buja', 'Udine'],
        ['Campolongo Maggiore', 'Venice'],
        ['Carpi', 'Modena'],
        ["Castel d'Azzano", 'Verona'],
        ['Castellabate', 'Salerno'],
        ['Colfelice', 'Frosinone'],
        ['Comezzano-Cizzago', 'Brescia'],
        ['Conza della Campania', 'Avellino'],
        ['Fagnano Alto', "L'Aquila"],
        ['Fontana Liri', 'Frosinone'],
        ['Fontanafredda', 'Pordenone'],
        ['Gatteo', 'Forlì-Cesena'],
        ['Gibellina', 'Trapani'],
        ['Giffoni Sei Casali', 'Salerno'],
        ['Greccio', 'Rieti'],
        ['Ionadi', 'Vibo Valentia'],
        ['Jesolo', 'Venice'],
        ['Marcon', 'Venice'],
        ['Martello', 'South Tyrol'],
        ['Massa e Cozzile', 'Pistoia'],
        ['Montopoli di Sabina', 'Rieti'],
        ['Montoro', 'Avellino'],
        ['Ne', 'Genoa'],
        ['Ocre', "L'Aquila"],
        ['Peschiera Borromeo', 'Milan'],
        ['Piancogno', 'Brescia'],
        ["Rocca d'Evandro", 'Caserta'],
        ['Roccafluvione', 'Ascoli Piceno'],
        ['Roccamontepiano', 'Chieti'],
        ['Romagnano al Monte', 'Salerno'],
        ['San Dorligo della Valle - Dolina', 'Trieste'],
        ['San Giovanni Teatino', 'Chieti'],
        ["Sant'Olcese", 'Genoa'],
        ['Santarcangelo di Romagna', 'Rimini'],
        ['Spigno Saturnia', 'Latina'],
        ['Villorba', 'Treviso'],
        ['Zimella', 'Verona'],
    ];

    public function run(): void
    {
        foreach (self::MUNICIPALITIES as [$name, $provinceName]) {
            $province = Province::query()
                ->where('country_code', self::COUNTRY_CODE)
                ->where('name', $provinceName)
                ->first();

            // No province means the geo dataset was never loaded: nothing to
            // attach the comune to, and re-running once it is will add it.
            if ($province === null) {
                continue;
            }

            City::firstOrCreate(
                ['name' => $name, 'province_id' => $province->id],
                [
                    'country_id' => $province->country_id,
                    'state_id' => $province->state_id,
                    'country_code' => self::COUNTRY_CODE,
                ],
            );
        }
    }
}
