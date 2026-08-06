<?php

use App\Models\Note;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0085, D-1/D-3/D-4: `quote_id` on POST/PATCH /api/notes — the write
// path (AC-003..007). Schema in NoteQuoteScopeSchemaTest, read/filter in
// NoteQuoteScopeFilterTest.

uses(RefreshDatabase::class);

if (! function_exists('noteActor')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function noteActor(array $abilities = []): User
    {
        foreach (['request-management.view', 'request-management.viewAll', 'notes.create'] as $permission) {
            Permission::findOrCreate($permission);
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo($ability);
        }

        return $user;
    }
}

if (! function_exists('noteManagedOpportunity')) {
    function noteManagedOpportunity(User $manager): Opportunity
    {
        $opportunity = Opportunity::factory()->create();
        $opportunity->managers()->sync([$manager->id => ['position' => Opportunity::OPERATOR_MANAGER_POSITION]]);

        return $opportunity;
    }
}

it('POST without quote_id -> the note is general: quote_id and quote both null (AC-003)', function () {
    $actor = noteActor(['request-management.view', 'notes.create']);
    $opportunity = noteManagedOpportunity($actor);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/notes', [
        'entity_type' => 'request-management', 'entity_id' => $opportunity->id, 'body' => 'general note',
    ])->assertCreated();

    expect($response->json('data.quote_id'))->toBeNull()
        ->and($response->json('data.quote'))->toBeNull();
    expect(Note::findOrFail($response->json('data.id'))->quote_id)->toBeNull();
});

it('POST with a quote_id belonging to the Opportunity -> created, response carries quote {id, code, title} (AC-004)', function () {
    $actor = noteActor(['request-management.view', 'notes.create']);
    $opportunity = noteManagedOpportunity($actor);
    $quote = Quote::factory()->create(['opportunity_id' => $opportunity->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/notes', [
        'entity_type' => 'request-management', 'entity_id' => $opportunity->id, 'body' => 'quote note', 'quote_id' => $quote->id,
    ])->assertCreated();

    expect($response->json('data.quote_id'))->toBe($quote->id)
        ->and($response->json('data.quote'))->toBe(['id' => $quote->id, 'code' => $quote->code, 'title' => $quote->title]);
    expect(Note::findOrFail($response->json('data.id'))->quote_id)->toBe($quote->id);
});

it('POST with a quote_id of an offer belonging to a DIFFERENT opportunity -> 422 quote_id, no note created (AC-005)', function () {
    $actor = noteActor(['request-management.view', 'notes.create']);
    $opportunity = noteManagedOpportunity($actor);
    $otherOpportunity = Opportunity::factory()->create();
    $foreignQuote = Quote::factory()->create(['opportunity_id' => $otherOpportunity->id]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/notes', [
        'entity_type' => 'request-management', 'entity_id' => $opportunity->id, 'body' => 'bad', 'quote_id' => $foreignQuote->id,
    ])->assertStatus(422)->assertJsonValidationErrors('quote_id');

    expect(Note::query()->count())->toBe(0);
});

it('a reply inherits the ROOT\'s quote_id, ignoring a different quote_id sent in its own payload (AC-006, D-4)', function () {
    $actor = noteActor(['request-management.view', 'notes.create']);
    $opportunity = noteManagedOpportunity($actor);
    $quoteA = Quote::factory()->create(['opportunity_id' => $opportunity->id]);
    $quoteB = Quote::factory()->create(['opportunity_id' => $opportunity->id]);
    Sanctum::actingAs($actor);

    $rootId = $this->postJson('/api/notes', [
        'entity_type' => 'request-management', 'entity_id' => $opportunity->id, 'body' => 'root', 'quote_id' => $quoteA->id,
    ])->assertCreated()->json('data.id');

    $reply = $this->postJson('/api/notes', [
        'entity_type' => 'request-management', 'entity_id' => $opportunity->id, 'body' => 'reply',
        'parent_id' => $rootId, 'quote_id' => $quoteB->id,
    ])->assertCreated()->json('data');

    expect($reply['quote_id'])->toBe($quoteA->id);
    expect(Note::findOrFail($reply['id'])->quote_id)->toBe($quoteA->id);
});

it('PATCH with a different quote_id is IGNORED: quote_id stays put, body still updates (AC-007, D-3)', function () {
    $actor = noteActor(['request-management.view', 'notes.create']);
    $opportunity = noteManagedOpportunity($actor);
    $quoteA = Quote::factory()->create(['opportunity_id' => $opportunity->id]);
    $quoteB = Quote::factory()->create(['opportunity_id' => $opportunity->id]);
    Sanctum::actingAs($actor);

    $noteId = $this->postJson('/api/notes', [
        'entity_type' => 'request-management', 'entity_id' => $opportunity->id, 'body' => 'original', 'quote_id' => $quoteA->id,
    ])->assertCreated()->json('data.id');

    $response = $this->patchJson("/api/notes/{$noteId}", [
        'body' => 'updated', 'quote_id' => $quoteB->id,
    ])->assertOk();

    expect($response->json('data.body'))->toBe('updated')
        ->and($response->json('data.quote_id'))->toBe($quoteA->id);
    expect(Note::findOrFail($noteId)->quote_id)->toBe($quoteA->id);
});
