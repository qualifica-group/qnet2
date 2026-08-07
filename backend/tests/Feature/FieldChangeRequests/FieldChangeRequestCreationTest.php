<?php

use App\Models\FieldChangeRequest;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// POST /api/field-change-requests (spec 0078): AC-013..023. Spec 0086, D-10
// (corrected in execution): the request-management subject is now the QUOTE
// (`FieldChangeRequestValueResolver::record()` resolves through
// `RequestManagementTableDefinition::baseQuery()`, whose `modelClass()` is
// `Quote`), never the Opportunity — `subject_type` is `quote`
// (`Relation::morphMap`), `current_value` reads through `Quote::sourceId()`
// (D-10's virtual read-through accessor), and the D-3 scope guard is now the
// Offerta's own `quotes.supervisor_id`.

uses(RefreshDatabase::class);

if (! function_exists('fcrEnsurePermissions')) {
    function fcrEnsurePermissions(): void
    {
        foreach (['viewAny', 'view', 'create', 'manage', 'export', 'viewActivity'] as $ability) {
            Permission::findOrCreate("field-change-requests.{$ability}");
        }
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'viewActivity', 'viewAll', 'updateSource'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }
    }
}

if (! function_exists('fcrActorWith')) {
    /**
     * @param  array<int, string>  $fieldChangeAbilities
     * @param  array<int, string>  $requestManagementAbilities
     */
    function fcrActorWith(array $fieldChangeAbilities, array $requestManagementAbilities = ['view', 'viewAll']): User
    {
        fcrEnsurePermissions();

        $user = User::factory()->create();

        foreach ($fieldChangeAbilities as $ability) {
            $user->givePermissionTo("field-change-requests.{$ability}");
        }

        foreach ($requestManagementAbilities as $ability) {
            $user->givePermissionTo("request-management.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('fcrQuoteWithSource')) {
    function fcrQuoteWithSource(): Quote
    {
        $opportunity = Opportunity::factory()->create(['source_id' => Source::factory()->create()->id]);

        return Quote::factory()->for($opportunity)->create();
    }
}

// ---------------------------------------------------------------------------
// AC-013 / AC-014 — happy path
// ---------------------------------------------------------------------------

it('AC-013: POST creates a pending request with a server-computed snapshot', function () {
    $actor = fcrActorWith(['create']);
    $quote = fcrQuoteWithSource();
    $newSource = Source::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/field-change-requests', [
        'resource' => 'request-management',
        'subject_id' => $quote->id,
        'field' => 'source_id',
        'requested_value' => $newSource->id,
        'reason' => 'Il cliente ha confermato di arrivare da un referral.',
    ])->assertCreated();

    $response->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.current_value', $quote->opportunity->source_id)
        ->assertJsonPath('data.requested_value', $newSource->id)
        ->assertJsonPath('data.requested_by.id', $actor->id);

    $row = FieldChangeRequest::sole();
    expect($row->status->value)->toBe('pending')
        ->and($row->requested_by_id)->toBe($actor->id)
        ->and($row->current_value)->toBe($quote->opportunity->source_id)
        ->and($row->pending_key)->toBe("quote:{$quote->id}:source_id");
});

it('AC-014: reason omitted -> 201, reason is null', function () {
    $actor = fcrActorWith(['create']);
    $quote = fcrQuoteWithSource();
    $newSource = Source::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/field-change-requests', [
        'resource' => 'request-management',
        'subject_id' => $quote->id,
        'field' => 'source_id',
        'requested_value' => $newSource->id,
    ])->assertCreated()
        ->assertJsonPath('data.reason', null);

    expect(FieldChangeRequest::sole()->reason)->toBeNull();
});

// ---------------------------------------------------------------------------
// AC-015 — server-computed fields cannot be overridden by the client
// ---------------------------------------------------------------------------

it('AC-015: client-supplied current_value/status/requested_by_id/handled_by_id are ignored', function () {
    $actor = fcrActorWith(['create']);
    $quote = fcrQuoteWithSource();
    $newSource = Source::factory()->create();
    $otherUser = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/field-change-requests', [
        'resource' => 'request-management',
        'subject_id' => $quote->id,
        'field' => 'source_id',
        'requested_value' => $newSource->id,
        'current_value' => 999999,
        'status' => 'approved',
        'requested_by_id' => $otherUser->id,
        'handled_by_id' => $otherUser->id,
    ])->assertCreated();

    $row = FieldChangeRequest::sole();
    expect($row->status->value)->toBe('pending')
        ->and($row->current_value)->toBe($quote->opportunity->source_id)
        ->and($row->requested_by_id)->toBe($actor->id)
        ->and($row->handled_by_id)->toBeNull();
});

// ---------------------------------------------------------------------------
// AC-016 — an actor who can already write the field must edit it directly
// ---------------------------------------------------------------------------

it('AC-016: actor WITH request-management.updateSource -> 422, no row written', function () {
    $actor = fcrActorWith(['create'], ['view', 'viewAll', 'update', 'updateSource']);
    $quote = fcrQuoteWithSource();
    $newSource = Source::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/field-change-requests', [
        'resource' => 'request-management',
        'subject_id' => $quote->id,
        'field' => 'source_id',
        'requested_value' => $newSource->id,
    ])->assertStatus(422);

    expect(FieldChangeRequest::query()->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// AC-017 — unregistered protected field
// ---------------------------------------------------------------------------

it('AC-017: a field not protected for this resource -> 422, no row written', function () {
    $actor = fcrActorWith(['create']);
    $quote = fcrQuoteWithSource();
    Sanctum::actingAs($actor);

    $this->postJson('/api/field-change-requests', [
        'resource' => 'request-management',
        'subject_id' => $quote->id,
        'field' => 'general_notes',
        'requested_value' => 'anything',
    ])->assertStatus(422);

    expect(FieldChangeRequest::query()->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// AC-018 — invalid requested_value for the column's type
// ---------------------------------------------------------------------------

it('AC-018: a nonexistent Fonte id -> 422, no row written', function () {
    $actor = fcrActorWith(['create']);
    $quote = fcrQuoteWithSource();
    Sanctum::actingAs($actor);

    $this->postJson('/api/field-change-requests', [
        'resource' => 'request-management',
        'subject_id' => $quote->id,
        'field' => 'source_id',
        'requested_value' => 999999,
    ])->assertStatus(422);

    expect(FieldChangeRequest::query()->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// AC-019 — no-op request
// ---------------------------------------------------------------------------

it('AC-019: requested_value equal to the current one -> 422, no row written', function () {
    $actor = fcrActorWith(['create']);
    $quote = fcrQuoteWithSource();
    Sanctum::actingAs($actor);

    $this->postJson('/api/field-change-requests', [
        'resource' => 'request-management',
        'subject_id' => $quote->id,
        'field' => 'source_id',
        'requested_value' => $quote->opportunity->source_id,
    ])->assertStatus(422);

    expect(FieldChangeRequest::query()->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// AC-020 / AC-021 — one pending per (record, field); a handled one does not block
// ---------------------------------------------------------------------------

it('AC-020: a second pending request on the same (record, field) -> 422', function () {
    $actor = fcrActorWith(['create']);
    $quote = fcrQuoteWithSource();
    $newSource = Source::factory()->create();
    $anotherSource = Source::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/field-change-requests', [
        'resource' => 'request-management',
        'subject_id' => $quote->id,
        'field' => 'source_id',
        'requested_value' => $newSource->id,
    ])->assertCreated();

    $this->postJson('/api/field-change-requests', [
        'resource' => 'request-management',
        'subject_id' => $quote->id,
        'field' => 'source_id',
        'requested_value' => $anotherSource->id,
    ])->assertStatus(422);

    expect(FieldChangeRequest::query()->count())->toBe(1);
});

it('AC-021: a new request after a previous one was handled -> 201 (pending_key null does not block UNIQUE)', function () {
    $actor = fcrActorWith(['create']);
    $quote = fcrQuoteWithSource();
    $newSource = Source::factory()->create();
    FieldChangeRequest::factory()->approved()->create([
        'resource' => 'request-management',
        'subject_type' => 'quote',
        'subject_id' => $quote->id,
        'field' => 'source_id',
    ]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/field-change-requests', [
        'resource' => 'request-management',
        'subject_id' => $quote->id,
        'field' => 'source_id',
        'requested_value' => $newSource->id,
    ])->assertCreated();

    expect(FieldChangeRequest::query()->count())->toBe(2);
});

// ---------------------------------------------------------------------------
// AC-022 — record out of the domain's own scope -> 404
// ---------------------------------------------------------------------------

it('AC-022: a record out of the actor\'s own baseQuery() scope -> 404, no row written', function () {
    $actor = fcrActorWith(['create'], ['view']); // no viewAll, not the offer's supervisor
    $quote = fcrQuoteWithSource();
    $newSource = Source::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/field-change-requests', [
        'resource' => 'request-management',
        'subject_id' => $quote->id,
        'field' => 'source_id',
        'requested_value' => $newSource->id,
    ])->assertNotFound();

    expect(FieldChangeRequest::query()->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// AC-023 — missing field-change-requests.create -> 403
// ---------------------------------------------------------------------------

it('AC-023: actor without field-change-requests.create -> 403', function () {
    $actor = fcrActorWith([]);
    $quote = fcrQuoteWithSource();
    $newSource = Source::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/field-change-requests', [
        'resource' => 'request-management',
        'subject_id' => $quote->id,
        'field' => 'source_id',
        'requested_value' => $newSource->id,
    ])->assertForbidden();

    expect(FieldChangeRequest::query()->count())->toBe(0);
});
