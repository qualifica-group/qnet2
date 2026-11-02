<?php

use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * User directive 2026-07-22: `GET /api/leads/{lead}/opportunity-defaults`
 * prefills the SECOND "Gestore Account" slot (`manager_slots`/`manager_refs`)
 * from `lead->operator` — G.A. 1 stays an empty slot — and NO LONGER the
 * Supervisor (which stays empty).
 * Reuses `completeLead()`/`opportunityFromLeadActor()`/
 * `nonDerivableOpportunityFks()` (globally declared, guarded with
 * `function_exists`, in OpportunityFromLeadTest.php — file-size split,
 * engineering.md §6).
 */

// ---------------------------------------------------------------------------
// manager_slots / manager_refs derived from the lead's Operator
// ---------------------------------------------------------------------------

it('opportunity-defaults: a lead with an operator prefills the second manager slot, leaving the first empty, never the supervisor', function () {
    $actor = opportunityFromLeadActor(['create'], ['view']);
    $lead = completeLead();
    $operator = User::factory()->create();
    $lead->update(['operator_id' => $operator->id]);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/leads/{$lead->id}/opportunity-defaults")->assertOk();

    expect($response->json('data.manager_slots'))->toBe([null, $operator->id]);
    expect($response->json('data.manager_refs'))->toBe([[
        'id' => $operator->id,
        'name' => $operator->name,
    ]]);
    // The Supervisor is no longer derived from the lead's Operator.
    expect($response->json('data.values'))->not->toHaveKey('supervisor_id');
    expect($response->json('data.references'))->not->toHaveKey('supervisor');
});

it('opportunity-defaults: a lead without an operator prefills empty manager slots', function () {
    $actor = opportunityFromLeadActor(['create'], ['view']);
    $lead = completeLead();
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/leads/{$lead->id}/opportunity-defaults")->assertOk();

    expect($lead->operator_id)->toBeNull();
    expect($response->json('data.manager_slots'))->toBe([]);
    expect($response->json('data.manager_refs'))->toBe([]);
});

// ---------------------------------------------------------------------------
// The manager prefill is never a lock: locked_fields stays untouched
// ---------------------------------------------------------------------------

it('opportunity-defaults: the operator prefill never enters locked_fields, which stays exactly [registry_id]', function () {
    $actor = opportunityFromLeadActor(['create'], ['view']);
    $lead = completeLead();
    $lead->update(['operator_id' => User::factory()->create()->id]);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/leads/{$lead->id}/opportunity-defaults")->assertOk();

    expect($response->json('data.locked_fields'))->not->toContain('manager_slots');
    // REQUIREMENT CHANGED: spec 0332 — source_id is no longer derived.
    expect($response->json('data.locked_fields'))->toEqualCanonicalizing(['registry_id']);
});

// ---------------------------------------------------------------------------
// The prefill is a suggestion: an explicitly chosen supervisor still wins
// ---------------------------------------------------------------------------

it('create with lead_id: an explicit supervisor_id is accepted as-is (the manager prefill never blocks it)', function () {
    $actor = opportunityFromLeadActor(['create'], ['view']);
    $lead = completeLead();
    $operator = User::factory()->create();
    $lead->update(['operator_id' => $operator->id]);
    $chosenSupervisor = User::factory()->create();
    Sanctum::actingAs($actor);

    $fks = array_merge(nonDerivableOpportunityFks(), ['supervisor_id' => $chosenSupervisor->id]);

    $response = $this->postJson('/api/opportunities', array_merge([
        'name' => 'Explicit supervisor',
        'lead_id' => $lead->id,
    ], $fks))->assertCreated();

    $this->assertDatabaseHas('opportunities', [
        'id' => $response->json('data.id'),
        'lead_id' => $lead->id,
        'supervisor_id' => $chosenSupervisor->id,
    ]);
});
