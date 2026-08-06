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
 * action and its `notes_count` badge. `notes` is gated by
 * `request-management.view`, not an `opportunities.*` ability: the thread is
 * registered under the `request-management` entity_type
 * (config/notes.php → RequestManagementNotable), so that is the permission the
 * note endpoints re-check — together with the GA2 scope, which the per-row
 * gate reproduces in full.
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

it('the notes action is gated by request-management.view and carries the notes_count badge', function () {
    Sanctum::actingAs(opportunityActionsUserWith(['viewAny']));
    $actions = $this->getJson('/api/tables/opportunities/columns')->assertOk()->json('data.actions');
    expect(collect($actions)->pluck('key'))->not->toContain('notes');

    Sanctum::actingAs(opportunityActionsUserWith(['viewAny'], ['view']));
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

    expect($actions)->not->toContain('edit')
        ->and($actions)->not->toContain('notes');
});

it('row.actions contains notes for an actor holding request-management.view + viewAll', function () {
    $actor = opportunityActionsUserWith(['viewAny', 'view'], ['view', 'viewAll']);
    $opportunity = Opportunity::factory()->create();
    Sanctum::actingAs($actor);

    $items = collect($this->postJson('/api/tables/opportunities/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()->json('items'));

    expect($items->firstWhere('id', $opportunity->id)['actions'])->toContain('notes');
});

it('row.actions offers notes only on the rows whose thread the actor can actually read (GA2 scope)', function () {
    // Without `request-management.viewAll` the note endpoints authorize only
    // the opportunities where the actor is the GA2 Operatore
    // (RequestManagementNotable::authorizeRead) — the row action must not
    // promise more than that, or the dialog 403s.
    $actor = opportunityActionsUserWith(['viewAny', 'view'], ['view']);
    $asOperator = Opportunity::factory()->create();
    $asOperator->managers()->attach($actor->id, ['position' => Opportunity::OPERATOR_MANAGER_POSITION]);
    // GA1 (position 1) is NOT the operator slot: out of scope.
    $asOtherManager = Opportunity::factory()->create();
    $asOtherManager->managers()->attach($actor->id, ['position' => 1]);
    $unrelated = Opportunity::factory()->create();

    Sanctum::actingAs($actor);

    $items = collect($this->postJson('/api/tables/opportunities/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()->json('items'));

    expect($items->firstWhere('id', $asOperator->id)['actions'])->toContain('notes')
        ->and($items->firstWhere('id', $asOtherManager->id)['actions'])->not->toContain('notes')
        ->and($items->firstWhere('id', $unrelated->id)['actions'])->not->toContain('notes');
});

it('rows: notes_count counts roots AND replies, per opportunity', function () {
    $actor = opportunityActionsUserWith(['viewAny'], ['view']);
    $withNotes = Opportunity::factory()->create();
    $otherOpportunity = Opportunity::factory()->create();

    $root = createOpportunityTableNote($withNotes, $actor);
    createOpportunityTableNote($withNotes, $actor, $root->id);
    createOpportunityTableNote($otherOpportunity, $actor);

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
    $opportunity = Opportunity::factory()->create();
    $quote = Quote::factory()->create(['opportunity_id' => $opportunity->id]);

    createOpportunityTableNote($opportunity, $actor);
    $quoteNote = createOpportunityTableNote($opportunity, $actor);
    $quoteNote->forceFill(['quote_id' => $quote->id])->save();

    Sanctum::actingAs($actor);

    $items = collect($this->postJson('/api/tables/opportunities/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()->json('items'));

    expect($items->firstWhere('id', $opportunity->id)['notes_count'])->toBe(2);
});
