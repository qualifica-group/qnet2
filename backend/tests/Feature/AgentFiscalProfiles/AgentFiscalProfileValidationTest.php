<?php

use App\Models\AgentFiscalProfile;
use App\Models\Referent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Sanctum::actingAs(agentFiscalProfileUserWith(['create', 'update']));
});

it('AC-014: requires the referent, the switches and valid_from', function () {
    $this->postJson('/api/agent-fiscal-profiles', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['referent_id', 'withholding_enabled', 'enasarco_enabled', 'valid_from']);

    $this->postJson('/api/agent-fiscal-profiles', agentFiscalProfilePayload(['referent_id' => 999999]))
        ->assertUnprocessable()->assertJsonValidationErrors('referent_id');
});

it('AC-014: the rates are required with the withholding on and refused with it off', function () {
    $this->postJson('/api/agent-fiscal-profiles', agentFiscalProfilePayload(['withholding_rate' => null, 'withholding_base_percent' => null]))
        ->assertUnprocessable()->assertJsonValidationErrors(['withholding_rate', 'withholding_base_percent']);

    $this->postJson('/api/agent-fiscal-profiles', agentFiscalProfilePayload(['withholding_enabled' => false]))
        ->assertUnprocessable()->assertJsonValidationErrors(['withholding_rate', 'withholding_base_percent']);

    $this->postJson('/api/agent-fiscal-profiles', agentFiscalProfilePayload(['withholding_enabled' => false, 'withholding_rate' => null, 'withholding_base_percent' => null]))
        ->assertCreated()->assertJsonPath('data.withholding_rate', null);
});

it('AC-014: the mandate type is required with ENASARCO on, refused with it off, and limited to mono|pluri', function () {
    $this->postJson('/api/agent-fiscal-profiles', agentFiscalProfilePayload(['enasarco_enabled' => true]))
        ->assertUnprocessable()->assertJsonValidationErrors('enasarco_mandate_type');

    $this->postJson('/api/agent-fiscal-profiles', agentFiscalProfilePayload(['enasarco_enabled' => true, 'enasarco_mandate_type' => 'tris']))
        ->assertUnprocessable()->assertJsonValidationErrors('enasarco_mandate_type');

    $this->postJson('/api/agent-fiscal-profiles', agentFiscalProfilePayload(['enasarco_mandate_type' => 'mono']))
        ->assertUnprocessable()->assertJsonValidationErrors('enasarco_mandate_type');

    $this->postJson('/api/agent-fiscal-profiles', agentFiscalProfilePayload(['enasarco_enabled' => true, 'enasarco_mandate_type' => 'pluri']))
        ->assertCreated()->assertJsonPath('data.enasarco_mandate_type', 'pluri');
});

it('AC-014: percentages stay within 0..100 with at most two decimals', function (mixed $value, bool $valid) {
    $response = $this->postJson('/api/agent-fiscal-profiles', agentFiscalProfilePayload(['withholding_rate' => $value]));

    $valid ? $response->assertCreated() : $response->assertUnprocessable()->assertJsonValidationErrors('withholding_rate');
})->with([
    'negative' => ['-0.01', false],
    'above 100' => ['100.01', false],
    'three decimals' => ['23.001', false],
    'not a number' => ['abc', false],
    'zero' => ['0', true],
    'one hundred' => ['100', true],
    'two decimals' => ['23.45', true],
]);

it('AC-014: valid_until cannot precede valid_from', function () {
    $this->postJson('/api/agent-fiscal-profiles', agentFiscalProfilePayload(['valid_from' => '2026-05-01', 'valid_until' => '2026-04-30']))
        ->assertUnprocessable()->assertJsonValidationErrors('valid_until');

    $this->postJson('/api/agent-fiscal-profiles', agentFiscalProfilePayload(['valid_from' => '2026-05-01', 'valid_until' => '2026-05-01']))
        ->assertCreated();
});

it('AC-014: notes are capped at 2000 characters', function () {
    $this->postJson('/api/agent-fiscal-profiles', agentFiscalProfilePayload(['notes' => str_repeat('a', 2001)]))
        ->assertUnprocessable()->assertJsonValidationErrors('notes');
});

// ---------------------------------------------------------------------------
// Overlapping validity (D-8) — NonOverlappingFiscalProfile
// ---------------------------------------------------------------------------

it('AC-014: overlapping validity for the same referent is a 422 on valid_from', function (array $existing, array $incoming) {
    $referent = Referent::factory()->create();
    AgentFiscalProfile::factory()->create([...$existing, 'referent_id' => $referent->id]);

    $this->postJson('/api/agent-fiscal-profiles', agentFiscalProfilePayload([...$incoming, 'referent_id' => $referent->id]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('valid_from')
        ->assertJsonPath('errors.valid_from.0', __('agent_fiscal_profiles.overlapping_validity'));

    expect(AgentFiscalProfile::count())->toBe(1);
})->with([
    'open-ended existing, later start' => [['valid_from' => '2026-01-01', 'valid_until' => null], ['valid_from' => '2027-01-01', 'valid_until' => null]],
    'open-ended incoming covers a bounded one' => [['valid_from' => '2026-03-01', 'valid_until' => '2026-03-31'], ['valid_from' => '2026-01-01', 'valid_until' => null]],
    'partial overlap at the end' => [['valid_from' => '2026-01-01', 'valid_until' => '2026-06-30'], ['valid_from' => '2026-06-30', 'valid_until' => '2026-12-31']],
    'partial overlap at the start' => [['valid_from' => '2026-06-01', 'valid_until' => '2026-12-31'], ['valid_from' => '2026-01-01', 'valid_until' => '2026-06-01']],
    'incoming inside existing' => [['valid_from' => '2026-01-01', 'valid_until' => '2026-12-31'], ['valid_from' => '2026-04-01', 'valid_until' => '2026-04-30']],
    'incoming contains existing' => [['valid_from' => '2026-04-01', 'valid_until' => '2026-04-30'], ['valid_from' => '2026-01-01', 'valid_until' => '2026-12-31']],
]);

it('AC-014: adjacent, earlier-closed and other-referent profiles do not collide', function () {
    $referent = Referent::factory()->create();
    AgentFiscalProfile::factory()->create(['referent_id' => $referent->id, 'valid_from' => '2026-01-01', 'valid_until' => '2026-06-30']);

    $this->postJson('/api/agent-fiscal-profiles', agentFiscalProfilePayload(['referent_id' => $referent->id, 'valid_from' => '2026-07-01', 'valid_until' => '2026-12-31']))
        ->assertCreated();
    $this->postJson('/api/agent-fiscal-profiles', agentFiscalProfilePayload(['referent_id' => $referent->id, 'valid_from' => '2025-01-01', 'valid_until' => '2025-12-31']))
        ->assertCreated();
    $this->postJson('/api/agent-fiscal-profiles', agentFiscalProfilePayload(['referent_id' => $referent->id, 'valid_from' => '2027-01-01', 'valid_until' => null]))
        ->assertCreated();
    $this->postJson('/api/agent-fiscal-profiles', agentFiscalProfilePayload(['valid_from' => '2026-01-01', 'valid_until' => '2026-06-30']))
        ->assertCreated();
});

it('AC-014: updating a profile never collides with itself, but does with a sibling', function () {
    $referent = Referent::factory()->create();
    $own = AgentFiscalProfile::factory()->create(['referent_id' => $referent->id, 'valid_from' => '2026-01-01', 'valid_until' => '2026-06-30']);
    AgentFiscalProfile::factory()->create(['referent_id' => $referent->id, 'valid_from' => '2026-07-01', 'valid_until' => null]);

    $this->putJson("/api/agent-fiscal-profiles/{$own->id}", agentFiscalProfilePayload([
        'referent_id' => $referent->id, 'valid_from' => '2026-02-01', 'valid_until' => '2026-06-30',
    ]))->assertOk()->assertJsonPath('data.valid_from', '2026-02-01');

    $this->putJson("/api/agent-fiscal-profiles/{$own->id}", agentFiscalProfilePayload([
        'referent_id' => $referent->id, 'valid_from' => '2026-02-01', 'valid_until' => '2026-07-01',
    ]))->assertUnprocessable()->assertJsonValidationErrors('valid_from');

    expect($own->fresh()->valid_until->toDateString())->toBe('2026-06-30');
});

it('AC-014: moving a profile to another referent checks that referent', function () {
    $busy = Referent::factory()->create();
    AgentFiscalProfile::factory()->create(['referent_id' => $busy->id, 'valid_from' => '2026-01-01', 'valid_until' => null]);
    $own = AgentFiscalProfile::factory()->create(['valid_from' => '2026-01-01', 'valid_until' => null]);

    $this->putJson("/api/agent-fiscal-profiles/{$own->id}", agentFiscalProfilePayload(['referent_id' => $busy->id]))
        ->assertUnprocessable()->assertJsonValidationErrors('valid_from');
});
