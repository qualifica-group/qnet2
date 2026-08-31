<?php

use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * User directive 2026-08-03: Nome / Cognome / Codice fiscale / Telefono were
 * the last columns of the worklist an operator could not narrow from the grid
 * header. They are DERIVED (the client Registry's PersonalData card, phone =
 * its primary phone/mobile contact), so filter, sort and value list are all
 * resolved by RequestClientColumns — this file is that contract.
 */
if (! function_exists('requestManagementUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function requestManagementUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity', 'viewAll'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("request-management.{$ability}");
        }

        return $user;
    }
}

/**
 * A Quote (spec 0086, D-1: the grid row) whose Opportunity's client card
 * carries the given anagraphic values plus a primary phone contact — the
 * exact relation path the four columns read (`quote.opportunity.registry`).
 */
function clientColumnRequest(string $firstName, string $lastName, string $taxCode, ?string $phone = null): Quote
{
    $registry = Registry::factory()->create();
    $card = $registry->personalData()->create([
        'type' => 'individual',
        'first_name' => $firstName,
        'last_name' => $lastName,
        'tax_code' => $taxCode,
    ]);

    if ($phone !== null) {
        $card->contacts()->create(['type' => 'phone', 'value' => $phone, 'is_primary' => true]);
    }

    $opportunity = Opportunity::factory()->create(['registry_id' => $registry->id]);

    return Quote::factory()->for($opportunity)->create();
}

/**
 * @param  array<string, mixed>  $payload
 * @return array<int, int>
 */
function clientColumnRowIds(array $payload): array
{
    return collect(
        test()->postJson('/api/tables/request-management/rows', [
            'startRow' => 0,
            'endRow' => 25,
            ...$payload,
        ])->assertOk()->json('items')
    )->pluck('id')->all();
}

// ---------------------------------------------------------------------------
// Contract: the four columns advertise the same sortable/filterable shape as
// every other text column of the grid
// ---------------------------------------------------------------------------

it('columns: the client anagraphic columns are sortable and text-filterable', function (string $columnId) {
    Sanctum::actingAs(requestManagementUserWith(['viewAny', 'viewAll', 'update']));

    $column = collect($this->getJson('/api/tables/request-management/columns')->assertOk()->json('data.columns'))
        ->keyBy('id')[$columnId];

    expect($column['sortable'])->toBeTrue()
        ->and($column['filterable'])->toBeTrue()
        ->and($column['filterType'])->toBe('text')
        // Still inline-editable and still in the quick-search allow-list.
        ->and($column['editable'])->toBeTrue();
})->with(['first_name', 'last_name', 'tax_code', 'phone']);

// ---------------------------------------------------------------------------
// Sorting: by the card value, and by the primary phone for `phone`
// ---------------------------------------------------------------------------

it('rows: sorting by a client column orders by the card value', function (string $columnId) {
    Sanctum::actingAs(requestManagementUserWith(['viewAny', 'viewAll']));

    $alpha = clientColumnRequest('Anna', 'Alberti', 'ALBNNA80A41H501U', '+39 02 1111111');
    $zulu = clientColumnRequest('Zeno', 'Zurlo', 'ZRLZNE80A01H501U', '+39 02 9999999');

    expect(clientColumnRowIds(['sortModel' => [['colId' => $columnId, 'sort' => 'asc']]]))
        ->toBe([$alpha->id, $zulu->id])
        ->and(clientColumnRowIds(['sortModel' => [['colId' => $columnId, 'sort' => 'desc']]]))
        ->toBe([$zulu->id, $alpha->id]);
})->with(['first_name', 'last_name', 'tax_code', 'phone']);

// ---------------------------------------------------------------------------
// Column filter: the generic typed conditions and the Set checklist both work
// against the derived value
// ---------------------------------------------------------------------------

it('rows: a text filter on a client column narrows to the matching card', function (string $columnId, string $needle) {
    Sanctum::actingAs(requestManagementUserWith(['viewAny', 'viewAll']));

    $match = clientColumnRequest('Mario', 'Rossi', 'RSSMRA80A01H501U', '+39 02 1234567');
    $other = clientColumnRequest('Giulia', 'Bianchi', 'BNCGLI85B02F205X', '+39 06 7654321');

    $ids = clientColumnRowIds([
        'filterModel' => [$columnId => ['filterType' => 'text', 'type' => 'contains', 'filter' => $needle]],
    ]);

    expect($ids)->toBe([$match->id])
        ->and($ids)->not->toContain($other->id);
})->with([
    'first name' => ['first_name', 'mari'],
    'last name' => ['last_name', 'ross'],
    'tax code' => ['tax_code', 'RSSMRA80'],
    'phone' => ['phone', '1234567'],
]);

it('rows: a set filter on phone matches the primary phone value', function () {
    Sanctum::actingAs(requestManagementUserWith(['viewAny', 'viewAll']));

    $match = clientColumnRequest('Mario', 'Rossi', 'RSSMRA80A01H501U', '+39 02 1234567');
    $other = clientColumnRequest('Giulia', 'Bianchi', 'BNCGLI85B02F205X', '+39 06 7654321');

    $ids = clientColumnRowIds([
        'filterModel' => ['phone' => ['filterType' => 'set', 'values' => ['+39 02 1234567']]],
    ]);

    expect($ids)->toBe([$match->id])
        ->and($ids)->not->toContain($other->id);
});

it('rows: the multi-filter envelope (Set + typed condition) is honoured, not silently dropped', function () {
    Sanctum::actingAs(requestManagementUserWith(['viewAny', 'viewAll']));

    $match = clientColumnRequest('Mario', 'Rossi', 'RSSMRA80A01H501U');
    $other = clientColumnRequest('Mario', 'Verdi', 'VRDMRA70A01H501U');

    $ids = clientColumnRowIds([
        'filterModel' => [
            'last_name' => [
                'filterType' => 'multi',
                'filterModels' => [
                    ['filterType' => 'set', 'values' => ['Rossi', 'Verdi']],
                    ['filterType' => 'text', 'type' => 'startsWith', 'filter' => 'Ros'],
                ],
            ],
        ],
    ]);

    expect($ids)->toBe([$match->id])
        ->and($ids)->not->toContain($other->id);
});

it('rows: a LIKE wildcard in a client filter is escaped, never a match-everything', function () {
    Sanctum::actingAs(requestManagementUserWith(['viewAny', 'viewAll']));

    $rossi = clientColumnRequest('Mario', 'Rossi', 'RSSMRA80A01H501U');
    $bianchi = clientColumnRequest('Giulia', 'Bianchi', 'BNCGLI85B02F205X');

    // The escaped needle matches neither card; what matters is that `%` is a
    // literal, so it never widens the filter to every row (the exact
    // escape-character semantics belong to the engine and differ per driver).
    expect(clientColumnRowIds([
        'filterModel' => ['last_name' => ['filterType' => 'text', 'type' => 'contains', 'filter' => '%']],
    ]))->not->toContain($rossi->id, $bianchi->id);
});

// ---------------------------------------------------------------------------
// The filter never widens the D-3 GA2 scope
// ---------------------------------------------------------------------------

it('rows: a client filter never escapes the GA2 scope of a viewAny-only actor', function () {
    $actor = requestManagementUserWith(['viewAny']);
    $mine = clientColumnRequest('Mario', 'Rossi', 'RSSMRA80A01H501U');
    // `operator_id` is deliberately NOT fillable (Quote model docblock,
    // spec 0087 D-3) — written only by QuoteManagerWriter, so a plain test
    // fixture uses forceFill() rather than a silently-discarded update().
    $mine->forceFill(['operator_id' => $actor->id])->save();
    $foreign = clientColumnRequest('Mario', 'Verdi', 'VRDMRA70A01H501U');

    Sanctum::actingAs($actor);

    $ids = clientColumnRowIds([
        'filterModel' => ['first_name' => ['filterType' => 'text', 'type' => 'contains', 'filter' => 'Mario']],
    ]);

    expect($ids)->toBe([$mine->id])
        ->and($ids)->not->toContain($foreign->id);
});

// ---------------------------------------------------------------------------
// Excel-like value list, scoped by the filters active on the OTHER columns
// ---------------------------------------------------------------------------

it('values: a client column lists the distinct card values of the visible rows', function (string $columnId, string $present) {
    Sanctum::actingAs(requestManagementUserWith(['viewAny', 'viewAll']));

    clientColumnRequest('Mario', 'Rossi', 'RSSMRA80A01H501U', '+39 02 1234567');

    $values = $this->postJson('/api/tables/request-management/values', ['columnId' => $columnId])
        ->assertOk()->json('data.values');

    expect($values)->toContain($present);
})->with([
    'first name' => ['first_name', 'Mario'],
    'last name' => ['last_name', 'Rossi'],
    'tax code' => ['tax_code', 'RSSMRA80A01H501U'],
    'phone' => ['phone', '+39 02 1234567'],
]);

it('values: the list is narrowed by the filters active on the OTHER columns', function () {
    Sanctum::actingAs(requestManagementUserWith(['viewAny', 'viewAll']));

    clientColumnRequest('Mario', 'Rossi', 'RSSMRA80A01H501U');
    clientColumnRequest('Giulia', 'Bianchi', 'BNCGLI85B02F205X');

    $values = $this->postJson('/api/tables/request-management/values', [
        'columnId' => 'last_name',
        'filterModel' => ['first_name' => ['filterType' => 'text', 'type' => 'contains', 'filter' => 'Mario']],
    ])->assertOk()->json('data.values');

    expect($values)->toBe(['Rossi']);
});
