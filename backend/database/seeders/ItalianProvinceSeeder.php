<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\City;
use App\Models\Province;
use App\Models\State;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Italian provinces MISSING from the reference geo dataset (`dev/DatabaseWorld/
 * world.sql`, loaded by `locations:add`).
 *
 * Clean reference data — no `Demo` prefix, called from DatabaseSeeder::run()
 * right after `locations:add` and before ItalianMunicipalitySeeder (which
 * attaches comuni to these provinces): fix here, do not patch world.sql.
 * The extract has the Aosta Valley comuni but no province row, so the plate
 * code AO never resolves. Each listed region holds exactly one province, so
 * its comuni without a province, and the addresses on them, are filed under it.
 *
 * Idempotent: the province is created once, only rows still without a
 * province are updated.
 */
class ItalianProvinceSeeder extends Seeder
{
    private const string COUNTRY_CODE = 'IT';

    /** Province name => its region, in the dataset's own spelling. */
    private const array PROVINCES = [
        'Aosta' => 'Aosta Valley',
    ];

    public function run(): void
    {
        foreach (self::PROVINCES as $provinceName => $regionName) {
            $state = State::query()
                ->where('country_code', self::COUNTRY_CODE)
                ->where('name', $regionName)
                ->first();

            // No region means the geo dataset was never loaded: nothing to attach to yet.
            if ($state === null) {
                continue;
            }

            DB::transaction(function () use ($provinceName, $state): void {
                $province = Province::query()->firstOrCreate(
                    ['name' => $provinceName, 'country_code' => self::COUNTRY_CODE],
                    ['state_id' => $state->id, 'country_id' => $state->country_id],
                );

                City::query()
                    ->where('state_id', $state->id)
                    ->whereNull('province_id')
                    ->update(['province_id' => $province->id]);
                Address::query()
                    ->where('state_id', $state->id)
                    ->whereNull('province_id')
                    ->update(['province_id' => $province->id]);
            });
        }
    }
}
