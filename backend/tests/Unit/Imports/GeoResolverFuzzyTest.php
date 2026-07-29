<?php

use App\Imports\Support\GeoResolver;
use App\Models\City;
use App\Models\Country;
use App\Models\Province;
use App\Models\State;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * @return array{country: Country, state: State, province: Province, city: City}
 */
function fuzzyGeoChain(): array
{
    $country = Country::factory()->create(['name' => 'Italy']);
    $state = State::factory()->create(['name' => 'Lombardy', 'country_id' => $country->id]);
    $province = Province::factory()->create(['name' => 'Milan', 'state_id' => $state->id, 'country_id' => $country->id]);
    $city = City::factory()->create(['name' => 'Milan', 'province_id' => $province->id, 'state_id' => $state->id, 'country_id' => $country->id]);

    return compact('country', 'state', 'province', 'city');
}

// ---------------------------------------------------------------------------
// AC-005 — GeoResolver::resolveFuzzy() — single-assign, 0/multi -> ambiguous
// ---------------------------------------------------------------------------

it('assigns via the exact path when the name matches exactly (identical to resolve())', function () {
    $geo = fuzzyGeoChain();

    $result = app(GeoResolver::class)->resolveFuzzy('Italy', 'Lombardy', null, 'Milan');

    expect($result->isResolved())->toBeTrue()
        ->and($result->ambiguous)->toBeFalse()
        ->and($result->countryId)->toBe($geo['country']->id)
        ->and($result->stateId)->toBe($geo['state']->id)
        ->and($result->cityId)->toBe($geo['city']->id);
});

it('assigns via a single close fuzzy match (typo/near-miss) above the threshold', function () {
    $geo = fuzzyGeoChain();

    // "Milano" vs "Milan" is a single-character near miss, well above the
    // acceptance threshold, and no other province in scope competes with it.
    $result = app(GeoResolver::class)->resolveFuzzy('Italy', 'Lombardy', 'Milano', null);

    expect($result->isResolved())->toBeTrue()
        ->and($result->ambiguous)->toBeFalse()
        ->and($result->provinceId)->toBe($geo['province']->id);
});

it('returns an ambiguous result with candidates when NO name is close enough', function () {
    fuzzyGeoChain();

    $result = app(GeoResolver::class)->resolveFuzzy('Ruritania', null, null, null);

    expect($result->isResolved())->toBeFalse()
        ->and($result->ambiguous)->toBeTrue()
        ->and($result->error)->toContain('Ruritania')
        ->and($result->countryId)->toBeNull();
});

it('returns an ambiguous result with candidates when MULTIPLE names are close enough', function () {
    $geo = fuzzyGeoChain();
    // A second province name within the same state scope, close enough to
    // "Milann" (a typo query) that BOTH "Milan" and "Milano" clear the
    // similarity threshold (90.9% and 83.3% respectively).
    Province::factory()->create(['name' => 'Milano', 'state_id' => $geo['state']->id, 'country_id' => $geo['country']->id]);

    $result = app(GeoResolver::class)->resolveFuzzy('Italy', 'Lombardy', 'Milann', null);

    expect($result->isResolved())->toBeFalse()
        ->and($result->ambiguous)->toBeTrue()
        ->and($result->candidates)->toHaveCount(2)
        ->and(collect($result->candidates)->pluck('name')->all())->toEqualCanonicalizing(['Milan', 'Milano']);
});

it('is a non-blocking result (never ::failed()) on ambiguity, distinct from a hard exact failure', function () {
    fuzzyGeoChain();

    $result = app(GeoResolver::class)->resolveFuzzy(null, null, null, 'Nonexistentville');

    expect($result->ambiguous)->toBeTrue()
        ->and($result->error)->not->toBeNull();
});

// ---------------------------------------------------------------------------
// Home-country tiebreak — a bare homonym (no country/region in the row) that
// matches several countries resolves to the configured home country.
// ---------------------------------------------------------------------------

/**
 * @return array{italy: Country, us: Country}
 */
function homonymRomeChain(): array
{
    $italy = Country::factory()->create(['name' => 'Italy']);
    $lazio = State::factory()->create(['name' => 'Lazio', 'country_id' => $italy->id]);
    City::factory()->create(['name' => 'Rome', 'state_id' => $lazio->id, 'country_id' => $italy->id]);

    $us = Country::factory()->create(['name' => 'United States']);
    $georgia = State::factory()->create(['name' => 'Georgia', 'country_id' => $us->id]);
    City::factory()->create(['name' => 'Rome', 'state_id' => $georgia->id, 'country_id' => $us->id]);

    return compact('italy', 'us');
}

it('breaks a cross-country city homonym in favour of the configured home country', function () {
    config()->set('imports.default_country', 'Italy');
    $geo = homonymRomeChain();

    $result = app(GeoResolver::class)->resolveFuzzy(null, null, null, 'Roma');

    expect($result->isResolved())->toBeTrue()
        ->and($result->ambiguous)->toBeFalse()
        ->and($result->countryId)->toBe($geo['italy']->id);
});

it('leaves the homonym ambiguous when no home country is configured', function () {
    config()->set('imports.default_country', '');
    homonymRomeChain();

    $result = app(GeoResolver::class)->resolveFuzzy(null, null, null, 'Rome');

    expect($result->isResolved())->toBeFalse()
        ->and($result->ambiguous)->toBeTrue()
        ->and($result->cityId)->toBeNull();
});

it('stays ambiguous when the home country itself holds more than one homonym', function () {
    config()->set('imports.default_country', 'Italy');
    $italy = Country::factory()->create(['name' => 'Italy']);
    $lazio = State::factory()->create(['name' => 'Lazio', 'country_id' => $italy->id]);
    $piedmont = State::factory()->create(['name' => 'Piedmont', 'country_id' => $italy->id]);
    City::factory()->create(['name' => 'Rome', 'state_id' => $lazio->id, 'country_id' => $italy->id]);
    City::factory()->create(['name' => 'Rome', 'state_id' => $piedmont->id, 'country_id' => $italy->id]);

    $result = app(GeoResolver::class)->resolveFuzzy(null, null, null, 'Rome');

    expect($result->isResolved())->toBeFalse()
        ->and($result->ambiguous)->toBeTrue();
});

// ---------------------------------------------------------------------------
// AC-005 — retro-compat: resolve() (exact) behavior is unchanged by the
// fuzzy addition — same inputs, same outputs as before.
// ---------------------------------------------------------------------------

it('leaves resolve() exact behavior unchanged: a near-miss name still fails (no fuzzy fallback)', function () {
    fuzzyGeoChain();

    // "Milano" only fuzzy-matches "Milan" — the EXACT resolver must still
    // reject it, exactly as it did before resolveFuzzy() existed.
    $result = app(GeoResolver::class)->resolve('Italy', 'Lombardy', 'Milano', null);

    expect($result->isResolved())->toBeFalse()
        ->and($result->error)->toContain('Milano');
});

// ---------------------------------------------------------------------------
// Production memory guard: an unscoped level (a row mapping only `city`)
// searches the whole `cities` table — 156k rows in the world dataset shipped
// by `locations:add`. Hydrating that per staged row exhausted PHP's memory
// limit in production and left the run stuck in `staging`, so no read of a
// level may select every column of the unfiltered table.
// ---------------------------------------------------------------------------

it('never hydrates a whole unscoped level, on either the exact or the fuzzy path', function (string $cityName) {
    fuzzyGeoChain();
    City::factory()->count(3)->create();

    $statements = [];
    DB::listen(function ($query) use (&$statements): void {
        $statements[] = $query->sql;
    });

    app(GeoResolver::class)->resolveFuzzy(null, null, null, $cityName);

    $fullTableReads = array_filter(
        $statements,
        static fn (string $sql): bool => str_contains($sql, 'select * from "cities"') && ! str_contains($sql, 'where'),
    );

    expect($statements)->not->toBeEmpty()
        ->and($fullTableReads)->toBeEmpty();
})->with([
    'exact name' => 'Milan',
    'near miss falling back to similarity' => 'Milanoo',
]);

it('leaves resolve() exact behavior unchanged: an exact hierarchical match still resolves', function () {
    $geo = fuzzyGeoChain();

    $result = app(GeoResolver::class)->resolve('Italy', 'Lombardy', 'Milan', 'Milan');

    expect($result->isResolved())->toBeTrue()
        ->and($result->countryId)->toBe($geo['country']->id)
        ->and($result->stateId)->toBe($geo['state']->id)
        ->and($result->provinceId)->toBe($geo['province']->id)
        ->and($result->cityId)->toBe($geo['city']->id);
});
