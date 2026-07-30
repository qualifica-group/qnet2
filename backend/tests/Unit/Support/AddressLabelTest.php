<?php

declare(strict_types=1);

use App\Models\Address;
use App\Models\City;
use App\Models\Country;
use App\Models\Province;
use App\Models\State;
use App\Support\AddressLabel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// spec 0069, D-5/AC-046 — App\Support\AddressLabel.

it('singleLine() returns an empty string for a null address (AC-046)', function (): void {
    expect(AddressLabel::singleLine(null))->toBe('');
});

it('multiLine() returns an empty array for a null address (AC-046)', function (): void {
    expect(AddressLabel::multiLine(null))->toBe([]);
});

it('singleLine() composes line1, postal code, city and province localized to Italian, with no dangling separators (AC-046)', function (): void {
    $country = Country::factory()->create(['name' => 'Italy']);
    $state = State::factory()->for($country, 'country')->create(['name' => 'Lombardy']);
    $province = Province::factory()->forState($state)->create(['name' => 'Milan']);
    $city = City::factory()->forProvince($province)->create(['name' => 'Milan']);

    $address = Address::factory()->create([
        'line1' => 'Via Roma 10',
        'line2' => null,
        'postal_code' => '20100',
        'city_id' => $city->id,
        'province_id' => $province->id,
        'state_id' => $state->id,
        'country_id' => $country->id,
    ])->load(['city', 'province', 'state', 'country']);

    expect(AddressLabel::singleLine($address))
        ->toBe('Via Roma 10 - 20100 Milano - Milano - Lombardia - Italia');
});

it('singleLine() with only line1 set returns exactly line1, no dangling separators (AC-046)', function (): void {
    $address = Address::factory()->create([
        'line1' => 'Via Roma 10',
        'line2' => null,
        'postal_code' => null,
        'city_id' => null,
        'province_id' => null,
        'state_id' => null,
        'country_id' => null,
    ]);

    expect(AddressLabel::singleLine($address))->toBe('Via Roma 10');
});

it('multiLine() returns only the non-empty parts, in the declared order (AC-046)', function (): void {
    $country = Country::factory()->create(['name' => 'Italy']);
    $city = City::factory()->create(['name' => 'Rome', 'province_id' => null]);

    $address = Address::factory()->create([
        'line1' => 'Via Roma 10',
        'line2' => 'Interno 4',
        'postal_code' => '00100',
        'city_id' => $city->id,
        'province_id' => null,
        'state_id' => null,
        'country_id' => $country->id,
    ])->load(['city', 'province', 'state', 'country']);

    expect(AddressLabel::multiLine($address))->toBe([
        'Via Roma 10, Interno 4',
        '00100 Roma',
        'Italia',
    ]);
});

it('never executes a database query — the caller must eager-load the geo relations (AC-046)', function (): void {
    $country = Country::factory()->create(['name' => 'Italy']);
    $city = City::factory()->create(['name' => 'Naples', 'province_id' => null]);

    $address = Address::factory()->create([
        'line1' => 'Via Toledo 1',
        'postal_code' => '80100',
        'city_id' => $city->id,
        'country_id' => $country->id,
    ])->load(['city', 'province', 'state', 'country']);

    DB::enableQueryLog();
    AddressLabel::singleLine($address);
    AddressLabel::multiLine($address);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($queries)->toBe([]);
});
