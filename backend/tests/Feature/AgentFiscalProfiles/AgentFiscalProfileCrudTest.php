<?php

use App\Models\AgentFiscalProfile;
use App\Models\Referent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('AC-013: creates a profile, stamps the creator and returns the Resource with permissions', function () {
    $actor = agentFiscalProfileUserWith(['create']);
    Sanctum::actingAs($actor);
    $referent = Referent::factory()->create(['name' => 'Mario Agent']);

    $this->postJson('/api/agent-fiscal-profiles', agentFiscalProfilePayload([
        'referent_id' => $referent->id,
        'enasarco_enabled' => true,
        'enasarco_mandate_type' => 'mono',
        'valid_until' => '2026-12-31',
        'notes' => 'Primo profilo',
    ]))
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Created')
        ->assertJsonPath('data.referent_id', $referent->id)
        ->assertJsonPath('data.referent', ['id' => $referent->id, 'name' => 'Mario Agent'])
        ->assertJsonPath('data.withholding_enabled', true)
        ->assertJsonPath('data.withholding_rate', '23.00')
        ->assertJsonPath('data.withholding_base_percent', '50.00')
        ->assertJsonPath('data.enasarco_mandate_type', 'mono')
        ->assertJsonPath('data.valid_from', '2026-01-01')
        ->assertJsonPath('data.valid_until', '2026-12-31')
        ->assertJsonPath('data.notes', 'Primo profilo')
        ->assertJsonStructure([
            'permissions' => ['resource', 'fields', 'actions'],
            'data' => [
                'id', 'referent_id', 'referent', 'withholding_enabled', 'withholding_rate', 'withholding_base_percent',
                'enasarco_enabled', 'enasarco_mandate_type', 'valid_from', 'valid_until', 'notes', 'created_at', 'updated_at',
            ],
        ]);

    $this->assertDatabaseHas('agent_fiscal_profiles', ['referent_id' => $referent->id, 'created_by' => $actor->id]);
});

it('AC-013: shows a profile with the field and action permissions', function () {
    Sanctum::actingAs(agentFiscalProfileUserWith(['view', 'delete']));
    $profile = AgentFiscalProfile::factory()->withEnasarco('pluri')->create(['valid_until' => '2026-06-30']);

    $this->getJson("/api/agent-fiscal-profiles/{$profile->id}")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $profile->id)
        ->assertJsonPath('data.referent.id', $profile->referent_id)
        ->assertJsonPath('data.enasarco_mandate_type', 'pluri')
        ->assertJsonPath('data.valid_until', '2026-06-30')
        ->assertJsonPath('permissions.actions.delete', true)
        ->assertJsonPath('permissions.actions.view_activity', false)
        ->assertJsonPath('permissions.fields.valid_from.editable', false);
});

it('AC-013: updates a profile (PUT and PATCH take the same body)', function () {
    Sanctum::actingAs(agentFiscalProfileUserWith(['update']));
    $profile = AgentFiscalProfile::factory()->create();
    $payload = agentFiscalProfilePayload(['referent_id' => $profile->referent_id, 'withholding_rate' => '20.00', 'notes' => 'Aggiornato']);

    $this->putJson("/api/agent-fiscal-profiles/{$profile->id}", $payload)
        ->assertOk()
        ->assertJsonPath('data.withholding_rate', '20.00')
        ->assertJsonPath('data.notes', 'Aggiornato');

    $this->patchJson("/api/agent-fiscal-profiles/{$profile->id}", [...$payload, 'withholding_rate' => '15.50'])
        ->assertOk()
        ->assertJsonPath('data.withholding_rate', '15.50');

    expect($profile->fresh()->withholding_rate)->toBe('15.50');
});

it('AC-013: clears the rate fields when the switches are off', function () {
    Sanctum::actingAs(agentFiscalProfileUserWith(['update']));
    $profile = AgentFiscalProfile::factory()->withEnasarco()->create();

    $this->putJson("/api/agent-fiscal-profiles/{$profile->id}", agentFiscalProfilePayload([
        'referent_id' => $profile->referent_id,
        'withholding_enabled' => false,
        'withholding_rate' => null,
        'withholding_base_percent' => null,
    ]))->assertOk()
        ->assertJsonPath('data.withholding_rate', null)
        ->assertJsonPath('data.enasarco_enabled', false)
        ->assertJsonPath('data.enasarco_mandate_type', null);
});

it('AC-013: deletes a profile', function () {
    Sanctum::actingAs(agentFiscalProfileUserWith(['delete']));
    $profile = AgentFiscalProfile::factory()->create();

    $this->deleteJson("/api/agent-fiscal-profiles/{$profile->id}")->assertNoContent();

    $this->assertDatabaseMissing('agent_fiscal_profiles', ['id' => $profile->id]);
});

it('AC-013: 403 without the matching permission on every endpoint, nothing written', function () {
    Sanctum::actingAs(agentFiscalProfileUserWith([]));
    $profile = AgentFiscalProfile::factory()->create(['notes' => 'keep']);

    $this->getJson("/api/agent-fiscal-profiles/{$profile->id}")->assertForbidden();
    $this->postJson('/api/agent-fiscal-profiles', agentFiscalProfilePayload())->assertForbidden();
    $this->putJson("/api/agent-fiscal-profiles/{$profile->id}", agentFiscalProfilePayload(['referent_id' => $profile->referent_id]))->assertForbidden();
    $this->patchJson("/api/agent-fiscal-profiles/{$profile->id}", agentFiscalProfilePayload(['referent_id' => $profile->referent_id]))->assertForbidden();
    $this->deleteJson("/api/agent-fiscal-profiles/{$profile->id}")->assertForbidden();

    expect(AgentFiscalProfile::count())->toBe(1)
        ->and($profile->fresh()->notes)->toBe('keep');
});

it('AC-013: each ability grants only its own endpoint', function () {
    $profile = AgentFiscalProfile::factory()->create();

    Sanctum::actingAs(agentFiscalProfileUserWith(['view']));
    $this->getJson("/api/agent-fiscal-profiles/{$profile->id}")->assertOk();
    $this->deleteJson("/api/agent-fiscal-profiles/{$profile->id}")->assertForbidden();

    Sanctum::actingAs(agentFiscalProfileUserWith(['delete']));
    $this->getJson("/api/agent-fiscal-profiles/{$profile->id}")->assertForbidden();
});

it('AC-013: 401 without authentication', function () {
    $profile = AgentFiscalProfile::factory()->create();

    $this->getJson("/api/agent-fiscal-profiles/{$profile->id}")->assertUnauthorized();
    $this->postJson('/api/agent-fiscal-profiles', [])->assertUnauthorized();
    $this->putJson("/api/agent-fiscal-profiles/{$profile->id}", [])->assertUnauthorized();
    $this->deleteJson("/api/agent-fiscal-profiles/{$profile->id}")->assertUnauthorized();
});

it('AC-013: 404 on an unknown id', function () {
    Sanctum::actingAs(agentFiscalProfileUserWith(['view', 'update', 'delete']));

    $this->getJson('/api/agent-fiscal-profiles/999999')->assertNotFound();
    $this->putJson('/api/agent-fiscal-profiles/999999', agentFiscalProfilePayload())->assertNotFound();
    $this->deleteJson('/api/agent-fiscal-profiles/999999')->assertNotFound();
});
