<?php

use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0408 D-17 (decisione utente 2026-11-23): whoever sees the Opportunity
// reads its notes thread, even when none of its Offerte is in their Gestione
// Richieste scope (RequestManagementNotable::authorizeRead), and can be
// mentioned in it (mentionableUsersQuery, the same rule from the other end).

uses(RefreshDatabase::class);

if (! function_exists('opportunityViewerWith')) {
    /**
     * @param  array<int, string>  $permissions
     */
    function opportunityViewerWith(array $permissions): User
    {
        foreach (['opportunities.view', 'opportunities.viewAll', 'request-management.view', 'request-management.viewAll', 'notes.create'] as $permission) {
            Permission::findOrCreate($permission);
        }

        return User::factory()->create()->givePermissionTo($permissions);
    }
}

if (! function_exists('opportunityWithForeignOffer')) {
    /**
     * An Opportunity whose only Offerta has another operator: nobody below
     * reaches it through Gestione Richieste.
     */
    function opportunityWithForeignOffer(array $attributes = []): Opportunity
    {
        $opportunity = Opportunity::factory()->create($attributes);
        Quote::factory()->for($opportunity)->create(['operator_id' => User::factory()->create()->id]);

        return $opportunity;
    }
}

it('reads the notes of an opportunity seen through opportunities.viewAll, outside the Gestione Richieste scope', function () {
    $viewer = opportunityViewerWith(['opportunities.view', 'opportunities.viewAll', 'request-management.view']);
    $opportunity = opportunityWithForeignOffer();
    Sanctum::actingAs($viewer);

    $this->getJson("/api/notes?entity_type=request-management&entity_id={$opportunity->id}")->assertOk();
});

it('reads the notes as Supervisore of the opportunity, without any Gestione Richieste permission', function () {
    $supervisor = opportunityViewerWith(['opportunities.view']);
    $opportunity = opportunityWithForeignOffer(['supervisor_id' => $supervisor->id]);
    Sanctum::actingAs($supervisor);

    $this->getJson("/api/notes?entity_type=request-management&entity_id={$opportunity->id}")->assertOk();
});

it('writes a note on an opportunity it sees, given notes.create', function () {
    $viewer = opportunityViewerWith(['opportunities.view', 'opportunities.viewAll', 'notes.create']);
    $opportunity = opportunityWithForeignOffer();
    Sanctum::actingAs($viewer);

    $this->postJson('/api/notes', [
        'entity_type' => 'request-management',
        'entity_id' => $opportunity->id,
        'body' => 'visible to whoever sees the opportunity',
    ])->assertCreated();
});

it('still refuses a holder of opportunities.view who does not see the opportunity', function () {
    $outsider = opportunityViewerWith(['opportunities.view', 'request-management.view']);
    $opportunity = opportunityWithForeignOffer();
    Sanctum::actingAs($outsider);

    $this->getJson("/api/notes?entity_type=request-management&entity_id={$opportunity->id}")->assertForbidden();
});

it('lists whoever sees the opportunity among the mentionable users, and nobody else', function () {
    $viewAll = opportunityViewerWith(['opportunities.view', 'opportunities.viewAll']);
    $supervisor = opportunityViewerWith(['opportunities.view']);
    $manager = opportunityViewerWith(['opportunities.view']);
    $outsider = opportunityViewerWith(['opportunities.view']);
    $viewAllWithoutView = opportunityViewerWith(['opportunities.viewAll']);
    $opportunity = opportunityWithForeignOffer(['supervisor_id' => $supervisor->id]);
    $opportunity->managers()->attach($manager->id, ['position' => 1]);
    Sanctum::actingAs($viewAll);

    $ids = collect($this->getJson("/api/notes/mentionable-users?entity_type=request-management&entity_id={$opportunity->id}")
        ->assertOk()->json('items'))->pluck('id');

    expect($ids)->toContain($viewAll->id, $supervisor->id, $manager->id)
        ->not->toContain($outsider->id)
        ->not->toContain($viewAllWithoutView->id);
});
