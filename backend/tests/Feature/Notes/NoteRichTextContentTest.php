<?php

use App\Models\Note;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/*
|--------------------------------------------------------------------------
| Rich text content rules on a note's body (spec 0128, D-1/D-2/D-5/D-7)
|--------------------------------------------------------------------------
|
| AC-002: a mention node survives sanitizing (notes-only), a script does not.
| AC-007: the note_text_max cap applies to the VISIBLE text, not the raw HTML.
| AC-008: an HTML fragment with no visible text and no image is "empty" (422).
*/

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
    function noteManagedOpportunity(User $operator): Opportunity
    {
        $opportunity = Opportunity::factory()->create();
        Quote::factory()->for($opportunity)->create(['operator_id' => $operator->id]);

        return $opportunity;
    }
}

// ---------------------------------------------------------------------------
// AC-002 — a mention node survives (notes-only, D-7); a script does not (D-1)
// ---------------------------------------------------------------------------

it('AC-002: a mention span survives sanitizing but a script tag does not', function () {
    $actor = noteActor(['request-management.view', 'notes.create']);
    $opportunity = noteManagedOpportunity($actor);
    $mentioned = User::factory()->create(['name' => 'Tizio Caio']);
    $mentioned->givePermissionTo(['request-management.view', 'request-management.viewAll']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/notes', [
        'entity_type' => 'request-management',
        'entity_id' => $opportunity->id,
        'body' => '<p>Hey <span data-type="mention" data-id="'.$mentioned->id.'" data-label="Tizio Caio">@Tizio Caio</span></p><script>alert(1)</script>',
        'mentions' => [$mentioned->id],
    ])->assertCreated();

    $body = $response->json('data.body');

    expect($body)->toContain('data-type="mention"')
        ->and($body)->toContain('data-id="'.$mentioned->id.'"')
        ->and($body)->not->toContain('<script>')
        ->and($body)->not->toContain('alert(1)');
});

// ---------------------------------------------------------------------------
// AC-007 — note_text_max applies to VISIBLE text, not the raw HTML
// ---------------------------------------------------------------------------

it('AC-007: visible text over note_text_max -> 422 body', function () {
    $actor = noteActor(['request-management.view', 'notes.create']);
    $opportunity = noteManagedOpportunity($actor);
    Sanctum::actingAs($actor);

    $countBefore = Note::count();

    $this->postJson('/api/notes', [
        'entity_type' => 'request-management',
        'entity_id' => $opportunity->id,
        'body' => '<p>'.str_repeat('a', 5001).'</p>',
    ])->assertStatus(422)->assertJsonValidationErrors('body');

    expect(Note::count())->toBe($countBefore);
});

it('AC-007: 4900 characters of text padded with markup past 5000 raw HTML chars -> 201', function () {
    $actor = noteActor(['request-management.view', 'notes.create']);
    $opportunity = noteManagedOpportunity($actor);
    Sanctum::actingAs($actor);

    // Each character wrapped in its own <strong> tag: 4900 visible characters,
    // well over 5000 characters of raw HTML.
    $body = str_repeat('<strong>a</strong>', 4900);
    expect(strlen($body))->toBeGreaterThan(5000);

    $this->postJson('/api/notes', [
        'entity_type' => 'request-management',
        'entity_id' => $opportunity->id,
        'body' => $body,
    ])->assertCreated();
});

// ---------------------------------------------------------------------------
// AC-008 — no visible text AND no image is "empty" (D-2)
// ---------------------------------------------------------------------------

it('AC-008: an empty paragraph -> 422 body', function () {
    $actor = noteActor(['request-management.view', 'notes.create']);
    $opportunity = noteManagedOpportunity($actor);
    Sanctum::actingAs($actor);

    $countBefore = Note::count();

    $this->postJson('/api/notes', [
        'entity_type' => 'request-management',
        'entity_id' => $opportunity->id,
        'body' => '<p></p>',
    ])->assertStatus(422)->assertJsonValidationErrors('body');

    expect(Note::count())->toBe($countBefore);
});
