<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\City;
use App\Models\Province;
use App\Models\State;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Italian provinces the reference geo dataset (`dev/DatabaseWorld/world.sql`,
 * loaded by `locations:add`) files under the wrong region.
 *
 * Clean reference data — no `Demo` prefix, called from DatabaseSeeder::run()
 * right after `locations:add`, like ItalianMunicipalitySeeder: fix here, do
 * not patch world.sql. The extract files eight Tuscan provinces under
 * Friuli–Venezia Giulia, so a Tuscan address (region Toscana + plate code PI)
 * never resolves its province and the forms list them under the wrong region.
 * The province, its comuni and the addresses already stored on it follow the
 * region they belong to.
 *
 * Idempotent: only rows still under another region are updated.
 */
class ItalianProvinceRegionSeeder extends Seeder
{
    private const string COUNTRY_CODE = 'IT';

    /** Dataset region name => its provinces, in the dataset's own spelling. */
    private const array PROVINCES_BY_REGION = [
        'Tuscany' => ['Grosseto', 'Livorno', 'Lucca', 'Massa and Carrara', 'Pisa', 'Pistoia', 'Prato', 'Siena'],
    ];

    public function run(): void
    {
        foreach (self::PROVINCES_BY_REGION as $regionName => $provinceNames) {
            $stateId = State::query()
                ->where('country_code', self::COUNTRY_CODE)
                ->where('name', $regionName)
                ->value('id');

            // No region means the geo dataset was never loaded: nothing to fix yet.
            if ($stateId === null) {
                continue;
            }

            $provinceIds = Province::query()
                ->where('country_code', self::COUNTRY_CODE)
                ->whereIn('name', $provinceNames)
                ->where('state_id', '!=', $stateId)
                ->pluck('id');

            if ($provinceIds->isEmpty()) {
                continue;
            }

            DB::transaction(function () use ($provinceIds, $stateId): void {
                Province::query()->whereIn('id', $provinceIds)->update(['state_id' => $stateId]);
                City::query()->whereIn('province_id', $provinceIds)->update(['state_id' => $stateId]);
                Address::query()->whereIn('province_id', $provinceIds)->update(['state_id' => $stateId]);
            });
        }
    }
}
