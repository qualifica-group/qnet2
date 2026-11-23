<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0340: the `notes` row affordances follow the competence tier of
// Gestione Richieste (AC-017), and the catalogue exposes viewCompetence on
// the six modules (AC-018).

uses(RefreshDatabase::class);

/**
 * @param  array<int, string>  $requestManagementAbilities
 * @param  array<int, string>  $gridAbilities
 */
function competenceNotesActor(string $grid, array $requestManagementAbilities, array $gridAbilities = ['viewAny', 'view', 'viewAll']): User
{
    $actor = competenceVisibilityActor($grid, $gridAbilities);

    foreach ($requestManagementAbilities as $ability) {
        $actor->givePermissionTo(Permission::findOrCreate("request-management.{$ability}", 'web'));
    }

    return $actor;
}

/**
 * @return array<int, string>
 */
function competenceRowActions(mixed $test, string $grid, int $id): array
{
    return collect($test->postJson("/api/tables/{$grid}/rows", ['startRow' => 0, 'endRow' => 50])->assertOk()->json('items'))
        ->firstWhere('id', $id)['actions'];
}

// REQUIREMENT CHANGED (spec 0408 D-17, 2026-11-23): on the Opportunita grid
// the notes action follows the row's visibility (OpportunityTableActionsTest).
// The Gestione Richieste competence tier still opens the thread to an actor
// who does not see the opportunity by themselves.
it('opens the thread of an opportunity whose offer is in my competence, not of one outside it (AC-017)', function (): void {
    [$function, $mine] = competenceVisibilityCategory();
    [, $other] = competenceVisibilityCategory();
    $reachable = categoryTabRequestQuote([$mine]);
    $unreachable = categoryTabRequestQuote([$other]);
    $actor = competenceNotesActor('opportunities', ['view', 'viewCompetence'], ['viewAny', 'view']);
    grantCompetence($actor, $function, $mine);
    Sanctum::actingAs($actor);

    Permission::findOrCreate('notes.create', 'web');
    Permission::findOrCreate('request-management.viewAll', 'web');
    $this->getJson('/api/notes/mentionable-users?entity_type=request-management&entity_id='.$reachable->opportunity_id)->assertOk();
    $this->getJson('/api/notes/mentionable-users?entity_type=request-management&entity_id='.$unreachable->opportunity_id)->assertForbidden();
});

it('lists a competence colleague among the mentionable users of a thread they can read, not of another (AC-017)', function (): void {
    [$function, $mine] = competenceVisibilityCategory();
    [, $other] = competenceVisibilityCategory();
    $reachable = categoryTabRequestQuote([$mine]);
    $unreachable = categoryTabRequestQuote([$other]);
    // Spec 0408 D-17: a colleague who sees every opportunity is mentionable
    // everywhere, so this one reaches the thread through competence only.
    $colleague = competenceNotesActor('opportunities', ['view', 'viewCompetence'], ['viewAny', 'view']);
    grantCompetence($colleague, $function, $mine);
    $reader = competenceNotesActor('opportunities', ['view', 'viewAll']);
    Sanctum::actingAs($reader);

    $mentionable = fn (int $opportunityId): array => collect(
        $this->getJson('/api/notes/mentionable-users?entity_type=request-management&entity_id='.$opportunityId)->assertOk()->json('items')
    )->pluck('id')->all();

    expect($mentionable($reachable->opportunity_id))->toContain($colleague->id)
        ->and($mentionable($unreachable->opportunity_id))->not->toContain($colleague->id);
});

it('does not open the thread by competence without request-management.viewCompetence (AC-017)', function (): void {
    [$function, $mine] = competenceVisibilityCategory();
    $reachable = categoryTabRequestQuote([$mine]);
    $actor = competenceNotesActor('opportunities', ['view'], ['viewAny', 'view']);
    grantCompetence($actor, $function, $mine);
    Permission::findOrCreate('request-management.viewAll', 'web');
    Sanctum::actingAs($actor);

    $this->getJson('/api/notes/mentionable-users?entity_type=request-management&entity_id='.$reachable->opportunity_id)->assertForbidden();
});

it('offers notes on an offer grid row in my competence, not on one outside it (AC-017)', function (): void {
    [$function, $mine] = competenceVisibilityCategory();
    [, $other] = competenceVisibilityCategory();
    $reachable = categoryTabRequestQuote([$mine]);
    $unreachable = categoryTabRequestQuote([$other]);
    $actor = competenceNotesActor('quotes', ['view', 'viewCompetence']);
    grantCompetence($actor, $function, $mine);
    Sanctum::actingAs($actor);

    expect(competenceRowActions($this, 'quotes', $reachable->id))->toContain('notes')
        ->and(competenceRowActions($this, 'quotes', $unreachable->id))->not->toContain('notes');
});

it('exposes viewCompetence on the six modules of the permission catalogue (AC-018)', function (): void {
    $this->artisan('permissions:sync')->assertSuccessful();
    $actor = User::factory()->create();
    $actor->givePermissionTo(Permission::findOrCreate('roles.viewAny', 'web'));
    Sanctum::actingAs($actor);

    $names = collect($this->getJson('/api/authorization/permission-catalogue')->assertOk()->json('data.areas'))
        ->flatMap(fn (array $area): array => $area['resources'])
        ->flatMap(fn (array $resource): array => $resource['permissions'])
        ->pluck('name');

    foreach (['opportunities', 'quotes', 'contracts', 'work-orders', 'request-management', 'enrollee-management'] as $resource) {
        expect($names)->toContain("{$resource}.viewCompetence");
    }
});
