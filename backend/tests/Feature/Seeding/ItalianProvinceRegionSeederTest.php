<?php

use App\Models\Address;
use App\Models\City;
use App\Models\Country;
use App\Models\Province;
use App\Models\State;
use Database\Seeders\ItalianProvinceRegionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| ItalianProvinceRegionSeeder — Tuscan provinces filed under the wrong region
|--------------------------------------------------------------------------
*/

/**
 * The world.sql layout the seeder corrects: Pisa filed under Friuli–Venezia
 * Giulia next to Udine, which really belongs there.
 *
 * @return array{tuscany: State, friuli: State, pisa: Province, udine: Province}
 */
function seedMisfiledTuscanProvince(): array
{
    $country = Country::factory()->create(['name' => 'Italy', 'iso2' => 'IT']);
    $tuscany = State::factory()->create(['name' => 'Tuscany', 'country_id' => $country->id, 'country_code' => 'IT']);
    $friuli = State::factory()->create(['name' => 'Friuli–Venezia Giulia', 'country_id' => $country->id, 'country_code' => 'IT']);
    $province = fn (string $name): Province => Province::factory()->create([
        'name' => $name, 'state_id' => $friuli->id, 'country_id' => $country->id, 'country_code' => 'IT',
    ]);

    return ['tuscany' => $tuscany, 'friuli' => $friuli, 'pisa' => $province('Pisa'), 'udine' => $province('Udine')];
}

function cityAndAddressIn(Province $province, string $city): Address
{
    $row = City::factory()->create([
        'name' => $city, 'province_id' => $province->id, 'state_id' => $province->state_id,
        'country_id' => $province->country_id, 'country_code' => 'IT',
    ]);

    return Address::factory()->create([
        'city_id' => $row->id, 'province_id' => $province->id,
        'state_id' => $province->state_id, 'country_id' => $province->country_id,
    ]);
}

it('moves a Tuscan province, its comuni and the addresses on them to Tuscany', function (): void {
    $geo = seedMisfiledTuscanProvince();
    $address = cityAndAddressIn($geo['pisa'], 'Pontedera');

    test()->seed(ItalianProvinceRegionSeeder::class);

    expect($geo['pisa']->fresh()->state_id)->toBe($geo['tuscany']->id)
        ->and(City::query()->where('name', 'Pontedera')->value('state_id'))->toBe($geo['tuscany']->id)
        ->and($address->fresh()->state_id)->toBe($geo['tuscany']->id);
});

it('leaves the provinces that really belong to the region untouched', function (): void {
    $geo = seedMisfiledTuscanProvince();
    $address = cityAndAddressIn($geo['udine'], 'Gemona');

    test()->seed(ItalianProvinceRegionSeeder::class);

    expect($geo['udine']->fresh()->state_id)->toBe($geo['friuli']->id)
        ->and($address->fresh()->state_id)->toBe($geo['friuli']->id);
});

it('is idempotent and a no-op when the geo dataset was never loaded', function (): void {
    test()->seed(ItalianProvinceRegionSeeder::class);
    expect(Province::query()->count())->toBe(0);

    $geo = seedMisfiledTuscanProvince();
    test()->seed(ItalianProvinceRegionSeeder::class);
    test()->seed(ItalianProvinceRegionSeeder::class);

    expect($geo['pisa']->fresh()->state_id)->toBe($geo['tuscany']->id);
});
