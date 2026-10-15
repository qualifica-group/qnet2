<?php

use App\Models\Address;
use App\Models\City;
use App\Models\Country;
use App\Models\Province;
use App\Models\State;
use Database\Seeders\ItalianProvinceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| ItalianProvinceSeeder — provinces missing from the world.sql extract
|--------------------------------------------------------------------------
*/

/**
 * The world.sql layout the seeder completes: Aosta Valley with its comuni
 * but no province row, so every comune there has a null province.
 */
function seedAostaValleyWithoutProvince(): State
{
    $country = Country::factory()->create(['name' => 'Italy', 'iso2' => 'IT']);

    return State::factory()->create(['name' => 'Aosta Valley', 'country_id' => $country->id, 'country_code' => 'IT']);
}

function aostaComune(State $state, string $name): City
{
    return City::factory()->create([
        'name' => $name, 'province_id' => null, 'state_id' => $state->id,
        'country_id' => $state->country_id, 'country_code' => 'IT',
    ]);
}

it('adds the missing province and files its comuni and their addresses under it', function (): void {
    $state = seedAostaValleyWithoutProvince();
    $city = aostaComune($state, 'Courmayeur');
    $address = Address::factory()->create(['city_id' => $city->id, 'state_id' => $state->id, 'country_id' => $state->country_id]);

    test()->seed(ItalianProvinceSeeder::class);

    $province = Province::query()->where('name', 'Aosta')->first();

    expect($province)->not->toBeNull()
        ->and($province->state_id)->toBe($state->id)
        ->and($province->country_code)->toBe('IT')
        ->and($city->fresh()->province_id)->toBe($province->id)
        ->and($address->fresh()->province_id)->toBe($province->id);
});

it('is idempotent and a no-op when the geo dataset was never loaded', function (): void {
    test()->seed(ItalianProvinceSeeder::class);
    expect(Province::query()->count())->toBe(0);

    seedAostaValleyWithoutProvince();
    test()->seed(ItalianProvinceSeeder::class);
    test()->seed(ItalianProvinceSeeder::class);

    expect(Province::query()->where('name', 'Aosta')->count())->toBe(1);
});
