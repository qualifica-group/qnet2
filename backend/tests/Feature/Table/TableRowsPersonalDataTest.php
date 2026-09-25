<?php

use App\Models\Address;
use App\Models\City;
use App\Models\Contact;
use App\Models\Country;
use App\Models\PersonalData;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * Split out of TableRowsTest (file-size split, engineering.md §6): the
 * personal-data-derived row coverage (user_type/primary_address/
 * primary_contact/geo columns) for POST /api/tables/users/rows.
 */

/**
 * Standard users.* permissions + a user granted the requested subset. Mirror
 * of the helper in TableRowsTest/UserTableConfigTest (guarded for redeclare
 * safety).
 *
 * @param  array<int, string>  $abilities
 */
if (! function_exists('rowsPayload')) {
    function rowsPayload(array $overrides = []): array
    {
        return array_merge([
            'startRow' => 0,
            'endRow' => 25,
        ], $overrides);
    }
}

/**
 * Create a user owning a PersonalData card, optionally with a primary address
 * (geo names create the reference rows) and a primary contact. Returns the user.
 *
 * @param  array{type?: 'individual'|'company', line1?: string, city?: string, region?: string, country?: string, contact_value?: string}  $opts
 */
if (! function_exists('userWithPersonalCard')) {
    function userWithPersonalCard(array $opts = []): User
    {
        $user = User::factory()->create();
        $type = $opts['type'] ?? 'individual';
        $card = PersonalData::factory()->{$type}()->for($user, 'personable')->create();

        $geo = ['country_id' => null, 'state_id' => null, 'city_id' => null, 'province_id' => null];

        if (isset($opts['country'])) {
            $geo['country_id'] = Country::factory()->create(['name' => $opts['country']])->id;
        }
        if (isset($opts['region'])) {
            $country = $geo['country_id'] ?? Country::factory()->create()->id;
            $geo['country_id'] = $country;
            $geo['state_id'] = State::factory()->create(['name' => $opts['region'], 'country_id' => $country])->id;
        }
        if (isset($opts['city'])) {
            $state = State::find($geo['state_id']) ?? State::factory()->create();
            $geo['state_id'] = $state->id;
            $geo['country_id'] = $state->country_id;
            $geo['city_id'] = City::factory()->forState($state)->create(['name' => $opts['city']])->id;
        }

        if (isset($opts['line1']) || $geo['country_id'] !== null || isset($opts['city'])) {
            Address::factory()->primary()->for($card, 'addressable')->create(array_merge($geo, [
                'line1' => $opts['line1'] ?? 'Via Roma 1',
            ]));
        }

        if (isset($opts['contact_value'])) {
            Contact::factory()->primary()->for($card, 'contactable')->create([
                'type' => 'email',
                'value' => $opts['contact_value'],
            ]);
        }

        return $user;
    }
}

it('maps the personal-data columns and never leaks the underlying sensitive fields', function () {
    $actor = userWithUserAbilities(['viewAny', 'view']);
    $owner = userWithPersonalCard([
        'type' => 'company',
        'line1' => 'Via Verdi 9',
        'country' => 'Italy',
        'region' => 'Lazio',
        'city' => 'Rome',
        'contact_value' => 'info@acme.example',
    ]);
    Sanctum::actingAs($actor);

    $rows = collect($this->postJson('/api/tables/users/rows', rowsPayload(['endRow' => 100]))
        ->assertOk()->json('items'))->keyBy('id');

    $row = $rows[$owner->id];

    expect($row)->toHaveKeys([
        'user_type', 'primary_address', 'country', 'region', 'province', 'city', 'primary_contact',
    ])
        ->and($row['user_type'])->toBe('company')
        ->and($row['country'])->toBe('Italia')
        ->and($row['region'])->toBe('Lazio')
        ->and($row['city'])->toBe('Roma')
        ->and($row['province'])->toBeNull()
        ->and($row['primary_address'])->toContain('Via Verdi 9')
        ->and($row['primary_address'])->toContain('Roma')
        // primary_contact is the array of ALL primary contacts (one per type),
        // each a structured {type, icon, label, value} for icon+label rendering.
        ->and($row['primary_contact'])->toBeArray()->toHaveCount(1);

    expect($row['primary_contact'][0])->toHaveKeys(['type', 'icon', 'label', 'value'])
        ->and($row['primary_contact'][0]['type'])->toBe('email')
        ->and($row['primary_contact'][0]['icon'])->toBe('mail')
        ->and($row['primary_contact'][0]['value'])->toBe('info@acme.example')
        ->and($row['primary_contact'][0]['label'])->toBeString();

    // The raw sensitive source fields must never appear as TOP-LEVEL row keys.
    expect($row)->not->toHaveKey('line1')
        ->and($row)->not->toHaveKey('line2')
        ->and($row)->not->toHaveKey('value')
        ->and($row)->not->toHaveKey('tax_code')
        ->and($row)->not->toHaveKey('vat_number');
});

it('returns null personal-data columns for a user without a card (em-dash on the frontend)', function () {
    $actor = userWithUserAbilities(['viewAny']); // actor has no personalData card
    Sanctum::actingAs($actor);

    $row = collect($this->postJson('/api/tables/users/rows', rowsPayload())
        ->assertOk()->json('items'))->firstWhere('id', $actor->id);

    expect($row['user_type'])->toBeNull()
        ->and($row['primary_address'])->toBeNull()
        ->and($row['country'])->toBeNull()
        ->and($row['region'])->toBeNull()
        ->and($row['province'])->toBeNull()
        ->and($row['city'])->toBeNull()
        // No card → no primary contacts: an empty array (em-dash on the frontend).
        ->and($row['primary_contact'])->toBe([]);
});

it('filters by user_type (set) and excludes users without a card', function () {
    $actor = userWithUserAbilities(['viewAny']); // no card
    $company = userWithPersonalCard(['type' => 'company']);
    userWithPersonalCard(['type' => 'individual']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/users/rows', rowsPayload([
        'filterModel' => [
            'user_type' => ['filterType' => 'set', 'values' => ['company']],
        ],
    ]))->assertOk();

    expect($response->json('pagination.total'))->toBe(1)
        ->and($response->json('items.0.id'))->toBe($company->id)
        ->and($response->json('items.0.user_type'))->toBe('company');
});

it('filters by a geo set column (city) on the primary address', function () {
    $actor = userWithUserAbilities(['viewAny']);
    $rome = userWithPersonalCard(['city' => 'Rome']);
    userWithPersonalCard(['city' => 'Paris']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/users/rows', rowsPayload([
        'filterModel' => [
            // Client sends the Italian option; the filter matches the English DB name.
            'city' => ['filterType' => 'set', 'values' => ['Roma']],
        ],
    ]))->assertOk();

    expect($response->json('pagination.total'))->toBe(1)
        ->and($response->json('items.0.id'))->toBe($rome->id)
        ->and($response->json('items.0.city'))->toBe('Roma');
});

it('filters by primary_address (text contains) via the relation', function () {
    $actor = userWithUserAbilities(['viewAny']);
    $needle = userWithPersonalCard(['line1' => 'Via Garibaldi 42']);
    userWithPersonalCard(['line1' => 'Main Street 1']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/users/rows', rowsPayload([
        'filterModel' => [
            'primary_address' => ['filterType' => 'text', 'type' => 'contains', 'filter' => 'Garibaldi'],
        ],
    ]))->assertOk();

    expect($response->json('pagination.total'))->toBe(1)
        ->and($response->json('items.0.id'))->toBe($needle->id);
});

it('filters by primary_contact (text contains) via the relation', function () {
    $actor = userWithUserAbilities(['viewAny']);
    $needle = userWithPersonalCard(['contact_value' => 'needle@example.com']);
    userWithPersonalCard(['contact_value' => 'other@example.com']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/users/rows', rowsPayload([
        'filterModel' => [
            'primary_contact' => ['filterType' => 'text', 'type' => 'contains', 'filter' => 'needle'],
        ],
    ]))->assertOk();

    expect($response->json('pagination.total'))->toBe(1)
        ->and($response->json('items.0.id'))->toBe($needle->id);
});

it('shows every primary contact (one per type) and filters across all of them', function () {
    $actor = userWithUserAbilities(['viewAny']);

    $owner = User::factory()->create();
    $card = PersonalData::factory()->individual()->for($owner, 'personable')->create();
    // Two PRIMARY contacts of different types + one NON-primary that must not show.
    Contact::factory()->primary()->for($card, 'contactable')->create([
        'type' => 'email', 'value' => 'multi@example.com',
    ]);
    Contact::factory()->primary()->for($card, 'contactable')->create([
        'type' => 'phone', 'value' => '+39 02 9999',
    ]);
    Contact::factory()->for($card, 'contactable')->create([
        'type' => 'website', 'value' => 'https://hidden.example', 'is_primary' => false,
    ]);

    Sanctum::actingAs($actor);

    $row = collect($this->postJson('/api/tables/users/rows', rowsPayload(['endRow' => 100]))
        ->assertOk()->json('items'))->firstWhere('id', $owner->id);

    // ALL primary contacts present; the non-primary one is excluded.
    $values = array_column($row['primary_contact'], 'value');
    $icons = array_column($row['primary_contact'], 'icon');

    expect($row['primary_contact'])->toBeArray()->toHaveCount(2)
        ->and($values)->toContain('multi@example.com')
        ->and($values)->toContain('+39 02 9999')
        ->and($values)->not->toContain('https://hidden.example')
        // Each contact carries its type icon (from the enum) for the badge.
        ->and($icons)->toContain('mail')
        ->and($icons)->toContain('phone');

    // The text filter matches on the phone primary contact…
    $byPhone = $this->postJson('/api/tables/users/rows', rowsPayload([
        'filterModel' => ['primary_contact' => ['filterType' => 'text', 'type' => 'contains', 'filter' => '9999']],
    ]))->assertOk();
    expect($byPhone->json('pagination.total'))->toBe(1)
        ->and($byPhone->json('items.0.id'))->toBe($owner->id);

    // …and on the email primary contact (filtering works on every type).
    $byEmail = $this->postJson('/api/tables/users/rows', rowsPayload([
        'filterModel' => ['primary_contact' => ['filterType' => 'text', 'type' => 'contains', 'filter' => 'multi@']],
    ]))->assertOk();
    expect($byEmail->json('pagination.total'))->toBe(1)
        ->and($byEmail->json('items.0.id'))->toBe($owner->id);

    // A non-primary contact's value never matches (filter is scoped to primary).
    $byHidden = $this->postJson('/api/tables/users/rows', rowsPayload([
        'filterModel' => ['primary_contact' => ['filterType' => 'text', 'type' => 'contains', 'filter' => 'hidden']],
    ]))->assertOk();
    expect($byHidden->json('pagination.total'))->toBe(0);
});

it('accepts a filter AND a sort on every new column (all whitelisted)', function () {
    $actor = userWithUserAbilities(['viewAny']);
    Sanctum::actingAs($actor);

    foreach (['user_type', 'primary_address', 'country', 'region', 'province', 'city', 'primary_contact'] as $columnId) {
        // Filterable: each new column key is whitelisted (no 422).
        $filter = in_array($columnId, ['primary_address', 'primary_contact'], true)
            ? [$columnId => ['filterType' => 'text', 'type' => 'contains', 'filter' => 'x']]
            : [$columnId => ['filterType' => 'set', 'values' => ['individual']]];

        $this->postJson('/api/tables/users/rows', rowsPayload(['filterModel' => $filter]))->assertOk();

        // Sortable: each new column is now in the sort whitelist (no 422), and
        // the derived ORDER BY subquery runs without error.
        $this->postJson('/api/tables/users/rows', rowsPayload([
            'sortModel' => [['colId' => $columnId, 'sort' => 'desc']],
        ]))->assertOk();
    }
});

it('sorts by user_type via the derived subquery', function () {
    $actor = userWithUserAbilities(['viewAny']); // no card → user_type null
    $company = userWithPersonalCard(['type' => 'company']);
    $individual = userWithPersonalCard(['type' => 'individual']);
    Sanctum::actingAs($actor);

    // asc on the raw type value: 'company' < 'individual'. NULLs (the actor)
    // sort first on SQLite asc, so filter the assertion to the two card users.
    $ordered = collect($this->postJson('/api/tables/users/rows', rowsPayload([
        'sortModel' => [['colId' => 'user_type', 'sort' => 'asc']],
    ]))->assertOk()->json('items'))
        ->pluck('user_type', 'id')
        ->filter()         // drop the null (no-card) actor row
        ->keys()
        ->all();

    expect($ordered)->toBe([$company->id, $individual->id]);
});

it('sorts by a geo column (city) via the derived subquery', function () {
    $actor = userWithUserAbilities(['viewAny']);
    $paris = userWithPersonalCard(['city' => 'Paris']);
    $rome = userWithPersonalCard(['city' => 'Rome']);
    Sanctum::actingAs($actor);

    $orderedCities = collect($this->postJson('/api/tables/users/rows', rowsPayload([
        'sortModel' => [['colId' => 'city', 'sort' => 'desc']],
    ]))->assertOk()->json('items'))
        ->pluck('city')
        ->filter()
        ->values()
        ->all();

    // desc → Rome before Paris (sort keys on the English DB name; display is Italian).
    expect($orderedCities)->toBe(['Roma', 'Paris'])
        ->and($rome->id)->not->toBe($paris->id);
});

it('sorts by primary_address via the derived subquery', function () {
    $actor = userWithUserAbilities(['viewAny']);
    userWithPersonalCard(['line1' => 'Zzz Last Street']);
    userWithPersonalCard(['line1' => 'Aaa First Street']);
    Sanctum::actingAs($actor);

    $addresses = collect($this->postJson('/api/tables/users/rows', rowsPayload([
        'sortModel' => [['colId' => 'primary_address', 'sort' => 'asc']],
    ]))->assertOk()->json('items'))
        ->pluck('primary_address')
        ->filter()
        ->values()
        ->all();

    expect($addresses[0])->toContain('Aaa First Street')
        ->and($addresses[1])->toContain('Zzz Last Street');
});

it('does not issue N+1 queries when mapping the personal-data columns', function () {
    $actor = userWithUserAbilities(['viewAny']);

    // Many card-owning users, each with a primary address (+ geo) and contact.
    for ($i = 0; $i < 15; $i++) {
        userWithPersonalCard(['city' => "City{$i}", 'contact_value' => "u{$i}@example.com"]);
    }

    Sanctum::actingAs($actor);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->postJson('/api/tables/users/rows', rowsPayload(['endRow' => 100]))->assertOk();
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    // Eager loading bounds the query count by a small constant (count + users +
    // one query per eager-loaded relation), independent of the 16 rows. An N+1
    // regression would issue several queries PER row (personalData, addresses,
    // each geo relation, contacts), far exceeding this bound.
    expect($queries)->toBeLessThanOrEqual(20);
});
