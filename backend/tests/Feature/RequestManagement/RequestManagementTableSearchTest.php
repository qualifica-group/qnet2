<?php

use App\Http\Requests\Table\TableRowsRequest;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\Registry;
use App\Tables\RequestManagement\RequestClientSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * A quote whose client card carries the given anagraphic values plus a
 * primary phone contact — the exact relation path RequestRowMapper reads and
 * RequestClientColumns searches, filters and sorts.
 */
function requestWithClient(string $firstName, string $lastName, string $taxCode, string $phone): Quote
{
    $registry = Registry::factory()->create();
    $card = $registry->personalData()->create([
        'type' => 'individual',
        'first_name' => $firstName,
        'last_name' => $lastName,
        'tax_code' => $taxCode,
    ]);
    $card->contacts()->create(['type' => 'phone', 'value' => $phone, 'is_primary' => true]);

    $opportunity = Opportunity::factory()->create(['registry_id' => $registry->id]);

    return Quote::factory()->for($opportunity)->create();
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
        ->assertJsonPath('data.searchable', ['first_name', 'last_name', 'phone', 'email', 'tax_code', 'vat_number']);
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
    // `operator_id` is deliberately NOT fillable (spec 0087, D-3) — written
    // only by QuoteManagerWriter, so a plain test fixture uses forceFill()
    // rather than a silently-discarded update().
    $mine->forceFill(['operator_id' => $actor->id])->save();
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

// ---------------------------------------------------------------------------
// Spec 0179: word-prefix semantics (FULLTEXT on MySQL/MariaDB, emulated here)
// ---------------------------------------------------------------------------

it('rows: a word must START a card word — "ros" finds Rossi, "ssi" does not (AC-001)', function () {
    Sanctum::actingAs(requestManagementUserWith(['viewAny', 'viewAll']));

    $rossi = requestWithClient('Mario', 'Rossi', 'RSSMRA80A01H501U', '+39 02 1234567');

    expect(searchRequestIds('ros'))->toBe([$rossi->id])
        ->and(searchRequestIds('ssi'))->toBe([]);
});

it('rows: every word must match, across the card columns together (AC-002)', function () {
    Sanctum::actingAs(requestManagementUserWith(['viewAny', 'viewAll']));

    $marioRossi = requestWithClient('Mario', 'Rossi', 'RSSMRA80A01H501U', '+39 02 1234567');
    requestWithClient('Mario', 'Bianchi', 'BNCMRA85B02F205X', '+39 06 7654321');

    expect(searchRequestIds('mario rossi'))->toBe([$marioRossi->id])
        ->and(searchRequestIds('rossi, mario'))->toBe([$marioRossi->id]);
});

it('rows: only the PRIMARY phone/email contact is searched, by word prefix (AC-003)', function () {
    Sanctum::actingAs(requestManagementUserWith(['viewAny', 'viewAll']));

    $match = requestWithClient('Mario', 'Rossi', 'RSSMRA80A01H501U', '+39 02 1234567');
    $match->opportunity->registry->personalData->contacts()->create(['type' => 'email', 'value' => 'mario.rossi@gmail.com', 'is_primary' => true]);
    $secondary = requestWithClient('Giulia', 'Bianchi', 'BNCGLI85B02F205X', '+39 06 7654321');
    $secondary->opportunity->registry->personalData->contacts()->create(['type' => 'phone', 'value' => '+39 02 5550000', 'is_primary' => false]);

    expect(searchRequestIds('gmail.com'))->toBe([$match->id])
        ->and(searchRequestIds('rossi@gmail'))->toBe([$match->id])
        ->and(searchRequestIds('1234'))->toBe([$match->id])
        ->and(searchRequestIds('5550000'))->toBe([]);
});

it('rows: a term made only of short words or stopwords matches nothing (AC-004)', function (string $term) {
    Sanctum::actingAs(requestManagementUserWith(['viewAny', 'viewAll']));

    requestWithClient('Mario', 'De La Rossa', 'DLRMRA80A01H501U', '+39 02 1234567');

    expect(searchRequestIds($term))->toBe([]);
})->with(['short words' => 'de la', 'stopword' => 'com']);

it('rows: the search AND-combines with the category tab (AC-005)', function () {
    Sanctum::actingAs(requestManagementUserWith(['viewAny', 'viewAll']));

    $inTab = requestWithClient('Mario', 'Rossi', 'RSSMRA80A01H501U', '+39 02 1234567');
    requestWithClient('Mario', 'Rosso', 'RSSMRA81A01H501U', '+39 02 7654321');
    $category = ProductCategory::factory()->create();
    OpportunityProductLine::factory()->create(['opportunity_id' => $inTab->opportunity_id, 'product_category_id' => $category->id]);

    $ids = collect($this->postJson('/api/tables/request-management/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'search' => 'mario',
        'productCategoryId' => $category->id,
    ])->assertOk()->json('items'))->pluck('id')->all();

    expect($ids)->toBe([$inTab->id]);
});

it('columns: declares the minimum search length on the request domains only (AC-006)', function () {
    Sanctum::actingAs(requestManagementUserWith(['viewAny', 'viewAll']));

    $this->getJson('/api/tables/request-management/columns')
        ->assertOk()
        ->assertJsonPath('data.searchMinLength', RequestClientSearch::MIN_WORD_LENGTH);
});
