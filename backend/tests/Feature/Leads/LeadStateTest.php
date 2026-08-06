<?php

use App\Models\Campaign;
use App\Models\City;
use App\Models\Lead;
use App\Models\OperationalSite;
use App\Models\Registry;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('leadStateUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function leadStateUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("leads.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("leads.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('siteWithState')) {
    function siteWithState(State $state): OperationalSite
    {
        $city = City::factory()->forState($state)->create();

        return OperationalSite::factory()->withAddress($city)->create();
    }
}

// ---------------------------------------------------------------------------
// AC-001 — create: leads.state_id derived from the sede
// ---------------------------------------------------------------------------

it('create: a lead whose sede has a Regione derives state_id from it (AC-001)', function () {
    $actor = leadStateUserWith(['create']);
    $registry = Registry::factory()->create();
    $campaign = Campaign::factory()->create();
    $state = State::factory()->create();
    $site = siteWithState($state);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/leads', [
        'registry_id' => $registry->id,
        'campaign_id' => $campaign->id,
        'operational_site_id' => $site->id,
    ])->assertCreated();

    expect($response->json('data.state_id'))->toBe($state->id)
        ->and($response->json('data.state'))->toBe(['id' => $state->id, 'name' => $state->name]);

    $this->assertDatabaseHas('leads', [
        'id' => $response->json('data.id'),
        'operational_site_id' => $site->id,
        'state_id' => $state->id,
    ]);
});

it('create: a sede with no Regione on its address derives a null state_id (AC-001)', function () {
    $actor = leadStateUserWith(['create']);
    $registry = Registry::factory()->create();
    $campaign = Campaign::factory()->create();
    $site = OperationalSite::factory()->create();
    $site->addresses()->create(['line1' => 'Via Roma 1', 'is_primary' => true, 'state_id' => null]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/leads', [
        'registry_id' => $registry->id,
        'campaign_id' => $campaign->id,
        'operational_site_id' => $site->id,
    ])->assertCreated();

    expect($response->json('data.state_id'))->toBeNull()
        ->and($response->json('data.state'))->toBeNull();
});

it('create: no operational_site_id -> state_id stays null (AC-001)', function () {
    $actor = leadStateUserWith(['create']);
    $registry = Registry::factory()->create();
    $campaign = Campaign::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/leads', [
        'registry_id' => $registry->id,
        'campaign_id' => $campaign->id,
    ])->assertCreated();

    expect($response->json('data.state_id'))->toBeNull();
});

// ---------------------------------------------------------------------------
// AC-001 — update: re-derivation only when operational_site_id is submitted
// ---------------------------------------------------------------------------

it('update: changing operational_site_id re-derives state_id (AC-001)', function () {
    $actor = leadStateUserWith(['update']);
    $firstState = State::factory()->create();
    $secondState = State::factory()->create();
    $firstSite = siteWithState($firstState);
    $secondSite = siteWithState($secondState);
    $lead = Lead::factory()->create(['operational_site_id' => $firstSite->id, 'state_id' => $firstState->id]);
    Sanctum::actingAs($actor);

    $response = $this->patchJson("/api/leads/{$lead->id}", [
        'operational_site_id' => $secondSite->id,
    ])->assertOk();

    expect($response->json('data.state_id'))->toBe($secondState->id);
    $this->assertDatabaseHas('leads', ['id' => $lead->id, 'state_id' => $secondState->id]);
});

it('update: a PATCH that does not touch operational_site_id leaves state_id untouched (AC-001)', function () {
    $actor = leadStateUserWith(['update']);
    $state = State::factory()->create();
    $site = siteWithState($state);
    $lead = Lead::factory()->create(['operational_site_id' => $site->id, 'state_id' => $state->id]);
    Sanctum::actingAs($actor);

    $response = $this->patchJson("/api/leads/{$lead->id}", ['notes' => 'Follow up'])
        ->assertOk();

    expect($response->json('data.state_id'))->toBe($state->id);
    $this->assertDatabaseHas('leads', ['id' => $lead->id, 'state_id' => $state->id]);
});

it('update: clearing operational_site_id to null clears state_id too (AC-001)', function () {
    $actor = leadStateUserWith(['update']);
    $state = State::factory()->create();
    $site = siteWithState($state);
    $lead = Lead::factory()->create(['operational_site_id' => $site->id, 'state_id' => $state->id]);
    Sanctum::actingAs($actor);

    $response = $this->patchJson("/api/leads/{$lead->id}", ['operational_site_id' => null])
        ->assertOk();

    expect($response->json('data.state_id'))->toBeNull();
});

// ---------------------------------------------------------------------------
// Regione as a user input (directive 2026-07-21): a submitted state_id wins
// over the Sede-derived fallback; the derivation only fills the gap.
// ---------------------------------------------------------------------------

it('create: a submitted state_id overrides the Sede-derived Regione', function () {
    $actor = leadStateUserWith(['create']);
    $registry = Registry::factory()->create();
    $campaign = Campaign::factory()->create();
    $siteState = State::factory()->create();
    $chosenState = State::factory()->create();
    $site = siteWithState($siteState);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/leads', [
        'registry_id' => $registry->id,
        'campaign_id' => $campaign->id,
        'operational_site_id' => $site->id,
        'state_id' => $chosenState->id,
    ])->assertCreated();

    expect($response->json('data.state_id'))->toBe($chosenState->id);
    $this->assertDatabaseHas('leads', ['id' => $response->json('data.id'), 'state_id' => $chosenState->id]);
});

it('create: a submitted state_id is stored even with no Sede', function () {
    $actor = leadStateUserWith(['create']);
    $registry = Registry::factory()->create();
    $campaign = Campaign::factory()->create();
    $chosenState = State::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/leads', [
        'registry_id' => $registry->id,
        'campaign_id' => $campaign->id,
        'state_id' => $chosenState->id,
    ])->assertCreated();

    expect($response->json('data.state_id'))->toBe($chosenState->id);
});

it('update: a submitted state_id wins even when the Sede also changes', function () {
    $actor = leadStateUserWith(['update']);
    $firstState = State::factory()->create();
    $secondState = State::factory()->create();
    $chosenState = State::factory()->create();
    $firstSite = siteWithState($firstState);
    $secondSite = siteWithState($secondState);
    $lead = Lead::factory()->create(['operational_site_id' => $firstSite->id, 'state_id' => $firstState->id]);
    Sanctum::actingAs($actor);

    $response = $this->patchJson("/api/leads/{$lead->id}", [
        'operational_site_id' => $secondSite->id,
        'state_id' => $chosenState->id,
    ])->assertOk();

    expect($response->json('data.state_id'))->toBe($chosenState->id);
    $this->assertDatabaseHas('leads', ['id' => $lead->id, 'state_id' => $chosenState->id]);
});

it('update: submitting state_id alone changes the Regione without touching the Sede', function () {
    $actor = leadStateUserWith(['update']);
    $state = State::factory()->create();
    $newState = State::factory()->create();
    $site = siteWithState($state);
    $lead = Lead::factory()->create(['operational_site_id' => $site->id, 'state_id' => $state->id]);
    Sanctum::actingAs($actor);

    $response = $this->patchJson("/api/leads/{$lead->id}", ['state_id' => $newState->id])
        ->assertOk();

    expect($response->json('data.state_id'))->toBe($newState->id);
    $this->assertDatabaseHas('leads', ['id' => $lead->id, 'operational_site_id' => $site->id, 'state_id' => $newState->id]);
});
