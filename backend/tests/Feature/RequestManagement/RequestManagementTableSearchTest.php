<?php

use App\Http\Requests\Table\TableRowsRequest;
use App\Models\Opportunity;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

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
 * An Opportunity whose client card carries the given anagraphic values plus a
 * primary phone contact — the exact relation path RequestRowMapper reads and
 * RequestClientColumns searches, filters and sorts.
 */
function requestWithClient(string $firstName, string $lastName, string $taxCode, string $phone): Opportunity
{
    $registry = Registry::factory()->create();
    $card = $registry->personalData()->create([
        'type' => 'individual',
        'first_name' => $firstName,
        'last_name' => $lastName,
        'tax_code' => $taxCode,
    ]);
    $card->contacts()->create(['type' => 'phone', 'value' => $phone, 'is_primary' => true]);

    return Opportunity::factory()->create(['registry_id' => $registry->id]);
}

/**
 * @return array<int, int>
 */
function searchRequestIds(string $term): array
{
    return collect(
        test()->postJson('/api/tables/request-management/rows', [
            'startRow' => 0,
            'endRow' => 25,
            'search' => $term,
        ])->assertOk()->json('items')
    )->pluck('id')->all();
}

// ---------------------------------------------------------------------------
// Config: the client anagraphic columns are the domain's search allow-list, so
// the frontend renders the quick-search box (spec 0009)
// ---------------------------------------------------------------------------

it('columns: exposes the client anagraphic columns as searchable', function () {
    Sanctum::actingAs(requestManagementUserWith(['viewAny', 'viewAll']));

    $this->getJson('/api/tables/request-management/columns')
        ->assertOk()
        ->assertJsonPath('data.searchable', ['first_name', 'last_name', 'tax_code', 'phone']);
});

// ---------------------------------------------------------------------------
// Search: each derived client column matches server-side over ALL rows
// ---------------------------------------------------------------------------

it('rows: the global search matches the client first/last name, tax code and primary phone', function (string $term) {
    Sanctum::actingAs(requestManagementUserWith(['viewAny', 'viewAll']));

    $match = requestWithClient('Mario', 'Rossi', 'RSSMRA80A01H501U', '+39 02 1234567');
    $other = requestWithClient('Giulia', 'Bianchi', 'BNCGLI85B02F205X', '+39 06 7654321');

    expect(searchRequestIds($term))->toBe([$match->id])
        ->and(searchRequestIds($term))->not->toContain($other->id);
})->with([
    'first name' => 'mari',
    'last name' => 'ross',
    'tax code' => 'RSSMRA80',
    'phone' => '1234567',
]);

it('rows: a blank search term is a no-op', function () {
    Sanctum::actingAs(requestManagementUserWith(['viewAny', 'viewAll']));

    $first = requestWithClient('Mario', 'Rossi', 'RSSMRA80A01H501U', '+39 02 1234567');
    $second = requestWithClient('Giulia', 'Bianchi', 'BNCGLI85B02F205X', '+39 06 7654321');

    expect(searchRequestIds('   '))->toHaveCount(2)
        ->and(searchRequestIds('   '))->toContain($first->id, $second->id);
});

// ---------------------------------------------------------------------------
// Search AND-combines with the D-3 GA2 scope: it never widens visibility
// ---------------------------------------------------------------------------

it('rows: the search never escapes the GA2 scope of a viewAny-only actor', function () {
    $actor = requestManagementUserWith(['viewAny']);
    $mine = requestWithClient('Mario', 'Rossi', 'RSSMRA80A01H501U', '+39 02 1234567');
    $mine->managers()->attach($actor->id, ['position' => 2]);
    $foreign = requestWithClient('Mario', 'Verdi', 'VRDMRA70A01H501U', '+39 02 9999999');

    Sanctum::actingAs($actor);

    expect(searchRequestIds('mario'))->toBe([$mine->id])
        ->and(searchRequestIds('mario'))->not->toContain($foreign->id);
});

// ---------------------------------------------------------------------------
// Over-length term is rejected by the shared FormRequest rule
// ---------------------------------------------------------------------------

it('rows: an over-length search term is rejected', function () {
    Sanctum::actingAs(requestManagementUserWith(['viewAny', 'viewAll']));

    $this->postJson('/api/tables/request-management/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'search' => str_repeat('a', TableRowsRequest::SEARCH_MAX_LENGTH + 1),
    ])->assertStatus(422);
});
