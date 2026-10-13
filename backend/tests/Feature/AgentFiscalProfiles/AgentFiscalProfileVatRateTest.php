<?php

use App\Models\AgentFiscalProfile;
use App\Models\VatRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('AC-018: a profile is created with a VAT rate and the Resource returns it', function () {
    Sanctum::actingAs(agentFiscalProfileUserWith(['create']));
    $vat = VatRate::factory()->create(['name' => 'IVA 22', 'rate' => '22.00']);

    $this->postJson('/api/agent-fiscal-profiles', agentFiscalProfilePayload(['vat_rate_id' => $vat->id]))
        ->assertCreated()
        ->assertJsonPath('data.vat_rate_id', $vat->id)
        ->assertJsonPath('data.vat_rate', ['id' => $vat->id, 'name' => 'IVA 22', 'rate' => '22.00']);

    $this->assertDatabaseHas('agent_fiscal_profiles', ['vat_rate_id' => $vat->id]);
});

it('AC-018: without a VAT rate the operation is VAT-free: vat_rate_id and vat_rate are null', function () {
    Sanctum::actingAs(agentFiscalProfileUserWith(['create']));

    $this->postJson('/api/agent-fiscal-profiles', agentFiscalProfilePayload())
        ->assertCreated()
        ->assertJsonPath('data.vat_rate_id', null)
        ->assertJsonPath('data.vat_rate', null);
});

it('AC-018: update sets and clears the VAT rate; show returns it', function () {
    Sanctum::actingAs(agentFiscalProfileUserWith(['update', 'view']));
    $vat = VatRate::factory()->create(['rate' => '10.00']);
    $profile = AgentFiscalProfile::factory()->create();
    $payload = agentFiscalProfilePayload(['referent_id' => $profile->referent_id]);

    $this->putJson("/api/agent-fiscal-profiles/{$profile->id}", [...$payload, 'vat_rate_id' => $vat->id])
        ->assertOk()->assertJsonPath('data.vat_rate.rate', '10.00');
    $this->getJson("/api/agent-fiscal-profiles/{$profile->id}")->assertJsonPath('data.vat_rate_id', $vat->id);

    $this->putJson("/api/agent-fiscal-profiles/{$profile->id}", [...$payload, 'vat_rate_id' => null])
        ->assertOk()->assertJsonPath('data.vat_rate', null);
    expect($profile->fresh()->vat_rate_id)->toBeNull();
});

it('AC-018: an unknown VAT rate is a 422 on vat_rate_id', function () {
    Sanctum::actingAs(agentFiscalProfileUserWith(['create']));

    $this->postJson('/api/agent-fiscal-profiles', agentFiscalProfilePayload(['vat_rate_id' => 999999]))
        ->assertUnprocessable()->assertJsonValidationErrors('vat_rate_id');
});

it('AC-017: vat_rate_id follows the field permission matrix', function () {
    $user = agentFiscalProfileUserWith(['view', 'update']);
    restrictAgentFiscalProfileField($user, 'vat_rate_id', visible: true, editable: false);
    Sanctum::actingAs($user);
    $vat = VatRate::factory()->create();
    $profile = AgentFiscalProfile::factory()->create(['vat_rate_id' => null]);
    $payload = agentFiscalProfilePayload(['referent_id' => $profile->referent_id]);

    $this->getJson("/api/agent-fiscal-profiles/{$profile->id}")
        ->assertJsonPath('permissions.fields.vat_rate_id.editable', false);
    $this->putJson("/api/agent-fiscal-profiles/{$profile->id}", [...$payload, 'vat_rate_id' => $vat->id])
        ->assertUnprocessable()->assertJsonValidationErrors('vat_rate_id');
});
