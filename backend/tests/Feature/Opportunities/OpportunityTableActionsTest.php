<?php

use App\Models\Note;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Row actions of the `opportunities` table (user directive 2026-08-05):
 * identical layout to Gestione Richieste — no `edit` (the detail surface owns
 * the Edit button, gated there by `opportunities.update`), plus the `notes`
 * action and its `notes_count` badge. REQUIREMENT CHANGED (spec 0408 D-17,
 * 2026-11-23): `notes` follows `view` — whoever sees the opportunity reads its
 * thread (RequestManagementNotable::authorizeRead), whatever their Gestione
 * Richieste scope.
 *
 * @param  array<int, string>  $abilities  `opportunities.*` suffixes
 * @param  array<int, string>  $requestManagementAbilities  `request-management.*` suffixes
 */
if (! function_exists('opportunityActionsUserWith')) {
    function opportunityActionsUserWith(array $abilities, array $requestManagementAbilities = []): User
    {
        foreach (['viewAny', 'view', 'update', 'delete', 'viewDocuments', 'viewActivity'] as $ability) {
            Permission::findOrCreate("opportunities.{$ability}");
        }
        foreach (['view', 'viewAll'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("opportunities.{$ability}");
        }

        foreach ($requestManagementAbilities as $ability) {
            $user->givePermissionTo("request-management.{$ability}");
        }

        $user->givePermissionTo(Permission::findOrCreate('opportunities.viewAll', 'web'));

        return $user;
    }
}

if (! function_exists('createOpportunityTableNote')) {
    function createOpportunityTableNote(Opportunity $opportunity, User $author, ?int $parentId = null): Note
    {
        $note = new Note(['body' => 'note body']);
        $note->notable()->associate($opportunity);
        $note->user_id = $author->id;
        $note->parent_id = $parentId;
        $note->save();

        return $note;
    }
}

it('the action catalogue never declares edit and keeps the Gestione Richieste order', function () {
    $actor = opportunityActionsUserWith(['viewAny', 'view', 'update', 'delete', 'viewDocuments'], ['view']);
    Sanctum::actingAs($actor);

    $keys = collect($this->getJson('/api/tables/opportunities/columns')->assertOk()->json('data.actions'))
        ->pluck('key');

    // view/documents/notes inline (INLINE_ACTION_LIMIT = 3), the rest in the
    // overflow menu; `activity` absent here since the actor lacks viewActivity.
    expect($keys->all())->toBe(['view', 'documents', 'notes', 'delete'])
        ->and($keys)->not->toContain('edit');
});

it('the notes action is gated by opportunities.view and carries the notes_count badge', function () {
    Sanctum::actingAs(opportunityActionsUserWith(['viewAny'], ['view']));
    $actions = $this->getJson('/api/tables/opportunities/columns')->assertOk()->json('data.actions');
    expect(collect($actions)->pluck('key'))->not->toContain('notes');

    Sanctum::actingAs(opportunityActionsUserWith(['viewAny', 'view']));
    $actions = $this->getJson('/api/tables/opportunities/columns')->assertOk()->json('data.actions');

    $notes = collect($actions)->firstWhere('key', 'notes');
    expect($notes)->not->toBeNull()
        ->and($notes['count_field'])->toBe('notes_count')
        ->and($notes)->not->toHaveKey('permission'); // stripped server-side after the gate check
});

it('row.actions omits edit even for an actor holding opportunities.update', function () {
    $actor = opportunityActionsUserWith(['viewAny', 'view', 'update']);
    $opportunity = Opportunity::factory()->create();
    Sanctum::actingAs($actor);

    $items = collect($this->postJson('/api/tables/opportunities/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()->json('items'));
    $actions = $items->firstWhere('id', $opportunity->id)['actions'];

    expect($actions)->not->toContain('edit');
});

it('row.actions contains notes for an actor holding request-management.view + viewAll', function () {
    $actor = opportunityActionsUserWith(['viewAny', 'view'], ['view', 'viewAll']);
    $opportunity = Opportunity::factory()->create();
    Sanctum::actingAs($actor);

    $items = collect($this->postJson('/api/tables/opportunities/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()->json('items'));

    expect($items->firstWhere('id', $opportunity->id)['actions'])->toContain('notes');
});

it('row.actions offers notes on every row the actor sees, without any Gestione Richieste scope (spec 0408 D-17)', function () {
    $actor = opportunityActionsUserWith(['viewAny', 'view']);
    $opportunity = Opportunity::factory()->create();
    Quote::factory()->for($opportunity)->create();
    Sanctum::actingAs($actor);

    $items = collect($this->postJson('/api/tables/opportunities/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()->json('items'));

    expect($items->firstWhere('id', $opportunity->id)['actions'])->toContain('notes');
});

it('rows: notes_count counts roots AND replies, per opportunity', function () {
    $actor = opportunityActionsUserWith(['viewAny'], ['view']);
    // REQUIREMENT CHANGED (2026-11-18): the badge counts only unread notes, never the actor's own.
    $author = User::factory()->create();
    $withNotes = Opportunity::factory()->create();
    $otherOpportunity = Opportunity::factory()->create();

    $root = createOpportunityTableNote($withNotes, $author);
    createOpportunityTableNote($withNotes, $author, $root->id);
    createOpportunityTableNote($otherOpportunity, $author);

    Sanctum::actingAs($actor);

    $items = collect($this->postJson('/api/tables/opportunities/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()->json('items'));

    expect($items->firstWhere('id', $withNotes->id)['notes_count'])->toBe(2)
        ->and($items->firstWhere('id', $otherOpportunity->id)['notes_count'])->toBe(1);
});

it('rows: notes_count is 0 when the opportunity has no notes', function () {
    $actor = opportunityActionsUserWith(['viewAny'], ['view']);
    $opportunity = Opportunity::factory()->create();
    Sanctum::actingAs($actor);

    $items = collect($this->postJson('/api/tables/opportunities/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()->json('items'));

    expect($items->firstWhere('id', $opportunity->id)['notes_count'])->toBe(0);
});

// spec 0085, AC-031: `notes_count` counts EVERY note on the Opportunity's
// thread, general AND quote-scoped alike — no separate per-Offerta counter.
it('rows: notes_count counts general and quote-scoped notes together', function () {
    $actor = opportunityActionsUserWith(['viewAny'], ['view']);
    // REQUIREMENT CHANGED (2026-11-18): the badge counts only unread notes, never the actor's own.
    $author = User::factory()->create();
    $opportunity = Opportunity::factory()->create();
    $quote = Quote::factory()->create(['opportunity_id' => $opportunity->id]);

    createOpportunityTableNote($opportunity, $author);
    $quoteNote = createOpportunityTableNote($opportunity, $author);
    $quoteNote->forceFill(['quote_id' => $quote->id])->save();

    Sanctum::actingAs($actor);

    $items = collect($this->postJson('/api/tables/opportunities/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()->json('items'));

    expect($items->firstWhere('id', $opportunity->id)['notes_count'])->toBe(2);
});
