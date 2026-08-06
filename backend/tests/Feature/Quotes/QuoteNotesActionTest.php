<?php

use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * The `notes` row action of the `quotes` table (spec 0085): opening it from an
 * Offerta shows that Offerta's own notes, which live on the PARENT
 * Opportunity's thread scoped by `quote_id`. So the gate is the host module's
 * (`request-management.view` + the GA2 scope of
 * RequestManagementNotable::authorizeRead), never a `quotes.*` ability — the
 * same rule the Opportunities grid already applies
 * (OpportunityTableActionsTest), here resolved through `quote->opportunity`.
 *
 * @param  array<int, string>  $abilities  `quotes.*` suffixes
 * @param  array<int, string>  $requestManagementAbilities  `request-management.*` suffixes
 */
uses(RefreshDatabase::class);

if (! function_exists('quoteNotesActionUserWith')) {
    function quoteNotesActionUserWith(array $abilities, array $requestManagementAbilities = []): User
    {
        foreach (['viewAny', 'view', 'update', 'delete', 'viewActivity'] as $ability) {
            Permission::findOrCreate("quotes.{$ability}");
        }
        foreach (['view', 'viewAll'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("quotes.{$ability}");
        }

        foreach ($requestManagementAbilities as $ability) {
            $user->givePermissionTo("request-management.{$ability}");
        }

        return $user;
    }
}

it('declares the notes action in the quotes catalogue only for request-management.view', function () {
    Sanctum::actingAs(quoteNotesActionUserWith(['viewAny']));
    $keys = collect($this->getJson('/api/tables/quotes/columns')->assertOk()->json('data.actions'))->pluck('key');
    expect($keys)->not->toContain('notes');

    Sanctum::actingAs(quoteNotesActionUserWith(['viewAny'], ['view']));
    $actions = $this->getJson('/api/tables/quotes/columns')->assertOk()->json('data.actions');

    $notes = collect($actions)->firstWhere('key', 'notes');
    expect($notes)->not->toBeNull()
        ->and($notes['icon'])->toBe('message-square')
        ->and($notes['label'])->toBe('actions.notes')
        // Spec 0085 <out>: no per-Offerta counter, the badge stays on the Opportunity.
        ->and($notes)->not->toHaveKey('count_field')
        ->and($notes)->not->toHaveKey('permission'); // stripped server-side after the gate check
});

it('row.actions contains notes for an actor holding request-management.view + viewAll', function () {
    $actor = quoteNotesActionUserWith(['viewAny', 'view'], ['view', 'viewAll']);
    $quote = Quote::factory()->create();
    Sanctum::actingAs($actor);

    $items = collect($this->postJson('/api/tables/quotes/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()->json('items'));

    expect($items->firstWhere('id', $quote->id)['actions'])->toContain('notes');
});

it('row.actions omits notes for an actor without request-management.view, however privileged on quotes', function () {
    $actor = quoteNotesActionUserWith(['viewAny', 'view', 'update', 'delete', 'viewActivity']);
    $quote = Quote::factory()->create();
    Sanctum::actingAs($actor);

    $items = collect($this->postJson('/api/tables/quotes/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()->json('items'));

    expect($items->firstWhere('id', $quote->id)['actions'])->not->toContain('notes');
});

it('row.actions offers notes only on the Offerte whose parent thread the actor can read (GA2 scope)', function () {
    // Without `request-management.viewAll` the note endpoints authorize only
    // the opportunities where the actor is the GA2 Operatore — the row action
    // must not promise more than that, or the dialog 403s.
    $actor = quoteNotesActionUserWith(['viewAny', 'view'], ['view']);

    $ownedOpportunity = Opportunity::factory()->create();
    $ownedOpportunity->managers()->attach($actor->id, ['position' => Opportunity::OPERATOR_MANAGER_POSITION]);
    $readable = Quote::factory()->create(['opportunity_id' => $ownedOpportunity->id]);

    // GA1 (position 1) is NOT the operator slot: out of scope.
    $otherRoleOpportunity = Opportunity::factory()->create();
    $otherRoleOpportunity->managers()->attach($actor->id, ['position' => 1]);
    $notReadable = Quote::factory()->create(['opportunity_id' => $otherRoleOpportunity->id]);

    $unrelated = Quote::factory()->create();

    Sanctum::actingAs($actor);

    $items = collect($this->postJson('/api/tables/quotes/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()->json('items'));

    expect($items->firstWhere('id', $readable->id)['actions'])->toContain('notes')
        ->and($items->firstWhere('id', $notReadable->id)['actions'])->not->toContain('notes')
        ->and($items->firstWhere('id', $unrelated->id)['actions'])->not->toContain('notes');
});

it('projects the parent opportunity id on every row, so the dialog knows which thread to open', function () {
    $actor = quoteNotesActionUserWith(['viewAny', 'view'], ['view', 'viewAll']);
    $opportunity = Opportunity::factory()->create();
    $quote = Quote::factory()->create(['opportunity_id' => $opportunity->id]);
    Sanctum::actingAs($actor);

    $items = collect($this->postJson('/api/tables/quotes/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()->json('items'));

    expect($items->firstWhere('id', $quote->id)['opportunity']['id'])->toBe($opportunity->id);
});
