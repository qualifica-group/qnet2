<?php

use App\Models\Note;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\Quote;
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

if (! function_exists('createNoteOn')) {
    /**
     * A `Note` attached to $opportunity, written directly (not through
     * `POST /api/notes`/NoteService — out of this lane's scope): `user_id`/
     * `parent_id`/`quote_id` are set as plain properties since `Note` is
     * `#[Fillable(['body'])]` only (mirrors how RequestManagementService
     * itself writes columns outside $fillable). `$quoteId` is the Offerta the
     * note is scoped to (spec 0085 D-1), null for a general note.
     */
    function createNoteOn(Opportunity $opportunity, User $author, ?int $parentId = null, ?int $quoteId = null): Note
    {
        $note = new Note(['body' => 'note body']);
        $note->notable()->associate($opportunity);
        $note->user_id = $author->id;
        $note->parent_id = $parentId;
        $note->quote_id = $quoteId;
        $note->save();

        return $note;
    }
}

// ---------------------------------------------------------------------------
// AC-010 — scoped to the actor's own OPERATED offers for a viewAny-but-not-
// viewAll user (spec 0087, D-9: `quotes.operator_id`, no longer
// `quotes.supervisor_id` and no longer the Opportunity's own GA2 pivot slot
// — being ANY opportunity manager, GA1 or GA2, is not enough on its own).
// ---------------------------------------------------------------------------

it('rows: a user with viewAny but without viewAll sees only offers they operate (AC-010)', function () {
    $actor = requestManagementUserWith(['viewAny']);
    $operated = Quote::factory()->create(['operator_id' => $actor->id]);
    // Being a manager of the underlying opportunity (any slot) is NOT the
    // scoping rule any more (D-9) — only the offer's own operator_id is.
    $managedButNotOperator = Quote::factory()->create();
    $managedButNotOperator->opportunity->managers()->attach($actor->id, ['position' => 1]);
    $unrelated = Quote::factory()->create();

    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/request-management/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $ids = collect($response->json('items'))->pluck('id');

    expect($ids->all())->toBe([$operated->id])
        ->and($ids->all())->not->toContain($managedButNotOperator->id)
        ->and($ids->all())->not->toContain($unrelated->id);
});

// ---------------------------------------------------------------------------
// AC-011 — viewAll sees every offer, no scope filter
// ---------------------------------------------------------------------------

it('rows: a user with viewAll sees every offer, operated or not (AC-011)', function () {
    $actor = requestManagementUserWith(['viewAny', 'viewAll']);
    $operated = Quote::factory()->create(['operator_id' => $actor->id]);
    $notOperated = Quote::factory()->create();

    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/request-management/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $ids = collect($response->json('items'))->pluck('id');

    expect($ids->all())->toContain($operated->id, $notOperated->id);
});

// ---------------------------------------------------------------------------
// AC-012 — 403 without viewAny; no edit/delete action in the catalogue;
// view gated by request-management.view
// ---------------------------------------------------------------------------

it('403 without request-management.viewAny on rows/columns/values (AC-012)', function () {
    $actor = requestManagementUserWith([]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/request-management/rows', ['startRow' => 0, 'endRow' => 25])->assertForbidden();
    $this->getJson('/api/tables/request-management/columns')->assertForbidden();
    $this->postJson('/api/tables/request-management/values', ['columnId' => 'source'])->assertForbidden();
});

it('the action catalogue never declares edit, and hides delete from an actor without request-management.delete (AC-012)', function () {
    // Requirement changed three times: spec 0052 B4b added `notes` (gated by
    // request-management.view, D-6), the D-7 amendment added `activity`
    // (gated by request-management.viewActivity), and the user directive
    // 2026-07-23 added `delete` (gated by request-management.delete — this
    // actor holds none of it, hence still absent here; the permitted case is
    // covered in RequestManagementBulkActionsTest). `edit` stays out for
    // good: create/edit remain on `opportunities.*`.
    $actor = requestManagementUserWith(['viewAny', 'view', 'viewActivity']);
    Sanctum::actingAs($actor);

    $actions = $this->getJson('/api/tables/request-management/columns')
        ->assertOk()
        ->json('data.actions');

    $keys = collect($actions)->pluck('key');
    // `activity` LAST: with INLINE_ACTION_LIMIT it lands in the overflow menu.
    expect($keys->all())->toBe(['view', 'notes', 'activity'])
        ->and($keys)->not->toContain('edit')
        ->and($keys)->not->toContain('delete');
});

it('the activity action is gated by request-management.viewActivity (D-7 amended)', function () {
    $withoutActivity = requestManagementUserWith(['viewAny', 'view']);
    Sanctum::actingAs($withoutActivity);
    $actions = $this->getJson('/api/tables/request-management/columns')->assertOk()->json('data.actions');
    expect(collect($actions)->pluck('key'))->not->toContain('activity');

    $withActivity = requestManagementUserWith(['viewAny', 'view', 'viewActivity']);
    Sanctum::actingAs($withActivity);
    $actions = $this->getJson('/api/tables/request-management/columns')->assertOk()->json('data.actions');
    expect(collect($actions)->pluck('key'))->toContain('activity');
});

it('the notes action is gated by request-management.view (spec 0052 B4b, D-6)', function () {
    $withoutView = requestManagementUserWith(['viewAny']);
    Sanctum::actingAs($withoutView);
    $actions = $this->getJson('/api/tables/request-management/columns')->assertOk()->json('data.actions');
    expect(collect($actions)->pluck('key'))->not->toContain('notes');

    $withView = requestManagementUserWith(['viewAny', 'view']);
    Sanctum::actingAs($withView);
    $actions = $this->getJson('/api/tables/request-management/columns')->assertOk()->json('data.actions');
    // count_field expectation REVERSED by spec 0052 B4c: the user asked for a
    // per-row note count on the icon, mirroring `documents`/`documents_count`
    // — updated here rather than left asserting the earlier "out of scope" call.
    $notes = collect($actions)->firstWhere('key', 'notes');
    expect($notes)->not->toBeNull()
        ->and($notes['count_field'])->toBe('notes_count')
        ->and($notes)->not->toHaveKey('permission'); // stripped server-side after the gate check
});

it('rows: the per-row notes action is gated by request-management.view, same as the row itself (spec 0052 B4b)', function () {
    // Dedicated coverage for the catalogue/row distinction found during
    // review: `notes` living in RequestColumnCatalog::actions() does NOT by
    // itself make it appear in a row's `actions` — that is a SEPARATE
    // allow-list (RequestManagementTableDefinition::actionsFor()), asserted
    // here independently of the catalogue-level test above.
    $quote = Quote::factory()->create();
    $actorWithoutView = requestManagementUserWith(['viewAny', 'viewAll']);
    Sanctum::actingAs($actorWithoutView);

    $items = collect($this->postJson('/api/tables/request-management/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()->json('items'));
    expect($items->firstWhere('id', $quote->id)['actions'])->not->toContain('notes');

    $actorWithView = requestManagementUserWith(['viewAny', 'viewAll', 'view']);
    Sanctum::actingAs($actorWithView);

    $items = collect($this->postJson('/api/tables/request-management/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()->json('items'));
    expect($items->firstWhere('id', $quote->id)['actions'])->toContain('notes');
});

it('row.actions contains view + notes with request-management.view (AC-012)', function () {
    // Requirement changed by spec 0052 B4b: `notes` shares the SAME gate as
    // `view` (D-6), so it rides along on every row `view` is granted on —
    // updated from the former `['view']`-only expectation.
    $quote = Quote::factory()->create();

    // viewAll makes the row visible regardless of D-3 scope; this test is
    // about the row action being gated by request-management.view, not
    // scoping.
    $actor = requestManagementUserWith(['viewAny', 'viewAll']);
    Sanctum::actingAs($actor);
    $items = collect($this->postJson('/api/tables/request-management/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()->json('items'));
    expect($items->firstWhere('id', $quote->id)['actions'])->toBe([]);

    $actorWithView = requestManagementUserWith(['viewAny', 'viewAll', 'view']);
    Sanctum::actingAs($actorWithView);
    $items = collect($this->postJson('/api/tables/request-management/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()->json('items'));
    expect($items->firstWhere('id', $quote->id)['actions'])->toBe(['view', 'notes']);
});

// ---------------------------------------------------------------------------
// AC-013 — zero supervised offers, no viewAll: empty rows, never 500
// ---------------------------------------------------------------------------

it('rows: a user supervising nothing and without viewAll gets empty rows, not a 500 (AC-013)', function () {
    $actor = requestManagementUserWith(['viewAny']);
    Quote::factory()->count(3)->create();

    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/request-management/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();

    expect($response->json('items'))->toBe([]);
});

// ---------------------------------------------------------------------------
// Row mapping: the operative columns surface with the expected shapes — the
// offer's own Supervisore (D-3), and the client's anagraphic fields
// (nome/cognome/codice fiscale/telefono) read from the Registry's
// PersonalData card. Spec 0083, D-2: `workflow_status` is REMOVED from this
// grid entirely — the Opportunity resolves no working state of its own any
// more.
// ---------------------------------------------------------------------------

it('rows: operator_ga2 + client anagraphic columns surface, no workflow_status key', function () {
    $actor = requestManagementUserWith(['viewAny', 'viewAll']);

    $registry = Registry::factory()->create();
    $card = $registry->personalData()->create([
        'type' => 'individual',
        'first_name' => 'Mario',
        'last_name' => 'Rossi',
        'tax_code' => 'RSSMRA80A01H501U',
    ]);
    $card->contacts()->create(['type' => 'phone', 'value' => '+39 02 1234567', 'is_primary' => true]);

    $operator = User::factory()->create(['name' => 'Giulia Bianchi']);
    $opportunity = Opportunity::factory()->create(['registry_id' => $registry->id]);
    $quote = Quote::factory()->for($opportunity)->create(['operator_id' => $operator->id]);

    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/request-management/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('id', $quote->id);

    expect($row)->not->toHaveKey('workflow_status')
        ->and($row['operator_ga2'])->toMatchArray(['id' => $operator->id, 'name' => 'Giulia Bianchi'])
        ->and($row['first_name'])->toBe('Mario')
        ->and($row['last_name'])->toBe('Rossi')
        ->and($row['tax_code'])->toBe('RSSMRA80A01H501U')
        ->and($row['phone'])->toBe('+39 02 1234567');
});

it('AC-038: rows carry opportunity_id, the identifier documents/notes/activity row actions key off', function () {
    $actor = requestManagementUserWith(['viewAny', 'viewAll']);
    // Decoy first, so the quote id can never coincide with the opportunity id.
    Opportunity::factory()->create();
    $quote = Quote::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/request-management/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('id', $quote->id);

    expect($row)->toHaveKey('opportunity_id')
        ->and($row['opportunity_id'])->toBe($quote->opportunity_id)
        ->and($row['opportunity_id'])->not->toBe($quote->id);
});

// ---------------------------------------------------------------------------
// AC-012 (spec 0056) — operational_site sorts/filters (set + advanced) by
// the site's PRIMARY address line1, "allo stesso modo" as the opportunities
// grid (AC-009/010). Spec 0086, D-6: the FK is now on `quotes` itself.
// ---------------------------------------------------------------------------

it('rows: sorting by operational_site orders requests by the site\'s primary address line1 (AC-012)', function () {
    $actor = requestManagementUserWith(['viewAny', 'viewAll']);
    $siteA = OperationalSite::factory()->create();
    $siteA->addresses()->create(['line1' => 'Alpha Street', 'is_primary' => true]);
    $siteB = OperationalSite::factory()->create();
    $siteB->addresses()->create(['line1' => 'Zulu Street', 'is_primary' => true]);
    $quoteZulu = Quote::factory()->create(['operational_site_id' => $siteB->id]);
    $quoteAlpha = Quote::factory()->create(['operational_site_id' => $siteA->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/request-management/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'sortModel' => [['colId' => 'operational_site', 'sort' => 'asc']],
    ])->assertOk();

    $ids = collect($response->json('items'))->pluck('id');
    expect($ids->all())->toBe([$quoteAlpha->id, $quoteZulu->id]);
});

it('rows: a set filter on operational_site matches the site\'s primary address line1, bound (AC-012)', function () {
    $actor = requestManagementUserWith(['viewAny', 'viewAll']);
    $site = OperationalSite::factory()->create();
    $site->addresses()->create(['line1' => 'Via Roma 1', 'is_primary' => true]);
    $matching = Quote::factory()->create(['operational_site_id' => $site->id]);
    Quote::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/request-management/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'filterModel' => ['operational_site' => ['filterType' => 'set', 'values' => ['Via Roma 1']]],
    ])->assertOk();

    expect(collect($response->json('items'))->pluck('id')->all())->toBe([$matching->id]);
});

// The requirement changed (user directive 2026-07-31): the advanced filter is
// a PICKER over `operational-sites/for-select`, not a free-text address
// search, so it matches by site id.
it('rows: the operational_site advanced filter matches the picked site ids', function () {
    $actor = requestManagementUserWith(['viewAny', 'viewAll']);
    $site = OperationalSite::factory()->create();
    $site->addresses()->create(['line1' => 'Corso Milano 10', 'is_primary' => true]);
    $matching = Quote::factory()->create(['operational_site_id' => $site->id]);
    Quote::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/request-management/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'advancedFilters' => ['operational_site' => [$site->id]],
    ])->assertOk();

    expect(collect($response->json('items'))->pluck('id')->all())->toBe([$matching->id]);
});

it('rows: the operational_site advanced filter rejects free text -> 422', function () {
    Sanctum::actingAs(requestManagementUserWith(['viewAny', 'viewAll']));

    $this->postJson('/api/tables/request-management/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'advancedFilters' => ['operational_site' => 'Milano'],
    ])->assertStatus(422);
});

it('rows: operational_site is the composed "{line1} - {city}" label (AC-015), no "[object Object]"', function () {
    $actor = requestManagementUserWith(['viewAny', 'viewAll']);
    $site = OperationalSite::factory()->withAddress()->create();
    $site->addresses()->first()->update(['line1' => 'Via Roma 1']);
    $quote = Quote::factory()->create(['operational_site_id' => $site->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/request-management/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('id', $quote->id);

    expect($row['operational_site'])->toBeArray()
        ->and($row['operational_site']['id'])->toBe($site->id)
        ->and($row['operational_site']['label'])->toStartWith('Via Roma 1 - ');
});

// ---------------------------------------------------------------------------
// notes_count (spec 0052 B4c): the `notes` action badge — every message in
// the discussion (roots + replies), soft-deleted excluded, single aggregated
// query. Direttiva utente 2026-08-07: contate sulla SINGOLA Offerta della riga
// (`notes.quote_id`), non sull'intero thread dell'Opportunita' — il thread che
// l'azione apre e' gia' filtrato su di essa, come per le Offerte.
// ---------------------------------------------------------------------------

it('rows: notes_count counts roots AND replies together', function () {
    $actor = requestManagementUserWith(['viewAny', 'viewAll']);
    $quote = Quote::factory()->create();
    $root = createNoteOn($quote->opportunity, $actor, null, $quote->id);
    createNoteOn($quote->opportunity, $actor, $root->id, $quote->id);
    createNoteOn($quote->opportunity, $actor, null, $quote->id);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/request-management/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('id', $quote->id);

    expect($row['notes_count'])->toBe(3);
});

it('rows: notes_count counts only the notes scoped to that Offerta, not the parent thread', function () {
    $actor = requestManagementUserWith(['viewAny', 'viewAll']);
    $opportunity = Opportunity::factory()->create();
    $quote = Quote::factory()->create(['opportunity_id' => $opportunity->id]);
    $sibling = Quote::factory()->create(['opportunity_id' => $opportunity->id]);

    createNoteOn($opportunity, $actor, null, $quote->id);
    createNoteOn($opportunity, $actor, null, $quote->id);
    // Una nota generale dell'Opportunita' e una dell'altra Offerta: nessuna
    // delle due appartiene al thread che l'azione di questa riga apre.
    createNoteOn($opportunity, $actor);
    createNoteOn($opportunity, $actor, null, $sibling->id);
    Sanctum::actingAs($actor);

    $items = collect($this->postJson('/api/tables/request-management/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()->json('items'));

    expect($items->firstWhere('id', $quote->id)['notes_count'])->toBe(2)
        ->and($items->firstWhere('id', $sibling->id)['notes_count'])->toBe(1);
});

it('rows: a soft-deleted note is NOT counted in notes_count', function () {
    $actor = requestManagementUserWith(['viewAny', 'viewAll']);
    $quote = Quote::factory()->create();
    createNoteOn($quote->opportunity, $actor, null, $quote->id);
    createNoteOn($quote->opportunity, $actor, null, $quote->id)->delete();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/request-management/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('id', $quote->id);

    expect($row['notes_count'])->toBe(1);
});

it('rows: a record with no notes exposes notes_count as 0, not null or absent', function () {
    $actor = requestManagementUserWith(['viewAny', 'viewAll']);
    $quote = Quote::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/request-management/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('id', $quote->id);

    expect($row)->toHaveKey('notes_count')
        ->and($row['notes_count'])->toBe(0);
});

// ---------------------------------------------------------------------------
// Allow-list: an unknown sort column is rejected (no raw SQL escape hatch)
// ---------------------------------------------------------------------------

it('rows: an unknown sort column is rejected (allow-list, no raw SQL escape hatch)', function () {
    $actor = requestManagementUserWith(['viewAny', 'viewAll']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/request-management/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'sortModel' => [['colId' => 'attribute_values', 'sort' => 'asc']],
    ])->assertStatus(422);
});

it('rows: product_categories is not sortable (AGGREGATED to-many column)', function () {
    $actor = requestManagementUserWith(['viewAny', 'viewAll']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/request-management/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'sortModel' => [['colId' => 'product_categories', 'sort' => 'asc']],
    ])->assertStatus(422);
});
