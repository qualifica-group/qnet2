<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Province;
use App\Models\State;
use Database\Seeders\ItalianMunicipalitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| ItalianMunicipalitySeeder — comuni missing from the world.sql extract
|--------------------------------------------------------------------------
*/

/**
 * The province the seeded comuni hang off, in the reference dataset's own
 * (English) spelling — the same one world.sql carries.
 */
function seedRomeProvince(): Province
{
    $country = Country::factory()->create(['name' => 'Italy', 'iso2' => 'IT']);
    $state = State::factory()->create(['name' => 'Lazio', 'country_id' => $country->id]);

    return Province::factory()->create([
        'name' => 'Rome',
        'state_id' => $state->id,
        'country_id' => $country->id,
        'country_code' => 'IT',
    ]);
}

it('adds the missing comune under its province, carrying the whole geo ancestry', function (): void {
    $province = seedRomeProvince();

    test()->seed(ItalianMunicipalitySeeder::class);

    $city = City::query()->where('name', 'Fonte Nuova')->first();

    expect($city)->not->toBeNull()
        ->and($city->province_id)->toBe($province->id)
        ->and($city->state_id)->toBe($province->state_id)
        ->and($city->country_id)->toBe($province->country_id)
        ->and($city->country_code)->toBe('IT');
});

it('is idempotent: a second run never duplicates the comune', function (): void {
    seedRomeProvince();

    test()->seed(ItalianMunicipalitySeeder::class);
    test()->seed(ItalianMunicipalitySeeder::class);

    expect(City::query()->where('name', 'Fonte Nuova')->count())->toBe(1);
});

it('is a no-op when the geo dataset was never loaded', function (): void {
    test()->seed(ItalianMunicipalitySeeder::class);

    expect(City::query()->count())->toBe(0);
});
