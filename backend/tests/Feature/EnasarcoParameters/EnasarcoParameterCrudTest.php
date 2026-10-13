<?php

use App\Models\CommissionSettlement;
use App\Models\EnasarcoParameter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('AC-015: creates a parameter row and returns the Resource with permissions', function () {
    Sanctum::actingAs(enasarcoParameterUserWith(['create']));

    $this->postJson('/api/enasarco-parameters', enasarcoParameterPayload(['notes' => 'Demo']))
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Created')
        ->assertJsonPath('data.year', 2026)
        ->assertJsonPath('data.total_rate', '17.00')
        ->assertJsonPath('data.agent_share_percent', '50.00')
        ->assertJsonPath('data.single_firm_ceiling', '45000.00')
        ->assertJsonPath('data.multi_firm_ceiling', '26000.00')
        ->assertJsonPath('data.notes', 'Demo')
        ->assertJsonStructure([
            'permissions' => ['resource', 'fields', 'actions'],
            'data' => ['id', 'year', 'total_rate', 'agent_share_percent', 'single_firm_ceiling', 'multi_firm_ceiling', 'notes', 'created_at', 'updated_at'],
        ]);

    $this->assertDatabaseHas('enasarco_parameters', ['year' => 2026]);
});

it('AC-015: shows a parameter row with the field and action permissions', function () {
    Sanctum::actingAs(enasarcoParameterUserWith(['view', 'delete']));
    $parameter = EnasarcoParameter::factory()->create(['year' => 2025]);

    $this->getJson("/api/enasarco-parameters/{$parameter->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $parameter->id)
        ->assertJsonPath('data.year', 2025)
        ->assertJsonPath('permissions.actions.delete', true)
        ->assertJsonPath('permissions.actions.view_activity', false)
        ->assertJsonPath('permissions.fields.year.editable', false);
});

it('AC-015: updates a row (PUT and PATCH take the same body), the own year is not a duplicate', function () {
    Sanctum::actingAs(enasarcoParameterUserWith(['update']));
    $parameter = EnasarcoParameter::factory()->create(['year' => 2026]);
    $payload = enasarcoParameterPayload(['year' => 2026, 'total_rate' => '16.50']);

    $this->putJson("/api/enasarco-parameters/{$parameter->id}", $payload)
        ->assertOk()
        ->assertJsonPath('data.total_rate', '16.50');

    $this->patchJson("/api/enasarco-parameters/{$parameter->id}", [...$payload, 'multi_firm_ceiling' => '30000.00'])
        ->assertOk()
        ->assertJsonPath('data.multi_firm_ceiling', '30000.00');

    expect($parameter->fresh()->multi_firm_ceiling)->toBe('30000.00');
});

it('AC-015: deletes a row nobody uses', function () {
    Sanctum::actingAs(enasarcoParameterUserWith(['delete']));
    $parameter = EnasarcoParameter::factory()->create(['year' => 2026]);
    CommissionSettlement::factory()->create(['period_to' => '2026-09-30', 'enasarco_enabled' => true]);

    $this->deleteJson("/api/enasarco-parameters/{$parameter->id}")->assertNoContent();

    $this->assertDatabaseMissing('enasarco_parameters', ['id' => $parameter->id]);
});

it('AC-015: DELETE is a 409 when an issued ENASARCO settlement of that year exists', function () {
    Sanctum::actingAs(enasarcoParameterUserWith(['delete']));
    $parameter = EnasarcoParameter::factory()->create(['year' => 2026]);
    CommissionSettlement::factory()->issued(1, 2026)->create([
        'period_to' => '2026-12-31', 'enasarco_enabled' => true, 'enasarco_mandate_type' => 'mono',
    ]);

    $this->deleteJson("/api/enasarco-parameters/{$parameter->id}")
        ->assertStatus(409)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'This ENASARCO year is used by an issued settlement.');

    $this->assertDatabaseHas('enasarco_parameters', ['id' => $parameter->id]);
});

it('AC-015: DELETE ignores drafts, cancelled, other years and settlements without ENASARCO', function () {
    Sanctum::actingAs(enasarcoParameterUserWith(['delete']));
    $parameter = EnasarcoParameter::factory()->create(['year' => 2026]);
    CommissionSettlement::factory()->create(['period_to' => '2026-06-30', 'enasarco_enabled' => true]);
    CommissionSettlement::factory()->issued(1, 2026)->cancelled()->create(['period_to' => '2026-06-30', 'enasarco_enabled' => true]);
    CommissionSettlement::factory()->issued(2, 2025)->create(['period_to' => '2025-12-31', 'enasarco_enabled' => true]);
    CommissionSettlement::factory()->issued(3, 2026)->create(['period_to' => '2026-06-30', 'enasarco_enabled' => false]);

    $this->deleteJson("/api/enasarco-parameters/{$parameter->id}")->assertNoContent();
});

it('AC-015: 403 without the matching permission on every endpoint, nothing written', function () {
    Sanctum::actingAs(enasarcoParameterUserWith([]));
    $parameter = EnasarcoParameter::factory()->create(['year' => 2026, 'notes' => 'keep']);

    $this->getJson("/api/enasarco-parameters/{$parameter->id}")->assertForbidden();
    $this->postJson('/api/enasarco-parameters', enasarcoParameterPayload(['year' => 2027]))->assertForbidden();
    $this->putJson("/api/enasarco-parameters/{$parameter->id}", enasarcoParameterPayload())->assertForbidden();
    $this->patchJson("/api/enasarco-parameters/{$parameter->id}", enasarcoParameterPayload())->assertForbidden();
    $this->deleteJson("/api/enasarco-parameters/{$parameter->id}")->assertForbidden();

    expect(EnasarcoParameter::count())->toBe(1)
        ->and($parameter->fresh()->notes)->toBe('keep');
});

it('AC-015: each ability grants only its own endpoint', function () {
    $parameter = EnasarcoParameter::factory()->create(['year' => 2026]);

    Sanctum::actingAs(enasarcoParameterUserWith(['view']));
    $this->getJson("/api/enasarco-parameters/{$parameter->id}")->assertOk();
    $this->deleteJson("/api/enasarco-parameters/{$parameter->id}")->assertForbidden();

    Sanctum::actingAs(enasarcoParameterUserWith(['delete']));
    $this->getJson("/api/enasarco-parameters/{$parameter->id}")->assertForbidden();
});

it('AC-015: 401 without authentication', function () {
    $parameter = EnasarcoParameter::factory()->create(['year' => 2026]);

    $this->getJson("/api/enasarco-parameters/{$parameter->id}")->assertUnauthorized();
    $this->postJson('/api/enasarco-parameters', [])->assertUnauthorized();
    $this->deleteJson("/api/enasarco-parameters/{$parameter->id}")->assertUnauthorized();
});

it('AC-015: 404 on an unknown id', function () {
    Sanctum::actingAs(enasarcoParameterUserWith(['view', 'update', 'delete']));

    $this->getJson('/api/enasarco-parameters/999999')->assertNotFound();
    $this->putJson('/api/enasarco-parameters/999999', enasarcoParameterPayload())->assertNotFound();
    $this->deleteJson('/api/enasarco-parameters/999999')->assertNotFound();
});

it('AC-015: create and update write the activity log', function () {
    Sanctum::actingAs(enasarcoParameterUserWith(['create', 'update']));

    $id = $this->postJson('/api/enasarco-parameters', enasarcoParameterPayload())->assertCreated()->json('data.id');
    $this->putJson("/api/enasarco-parameters/{$id}", enasarcoParameterPayload(['total_rate' => '18.00']))->assertOk();

    $parameter = EnasarcoParameter::findOrFail($id);
    $events = $parameter->activities()->pluck('event')->all();

    expect($events)->toContain('created')->toContain('updated')
        ->and($parameter->activities()->latest('id')->first()->properties['attributes']['total_rate'])->toBe('18.00');
});

it('AC-015: the activity log endpoint serves the resource to viewActivity holders', function () {
    $actor = enasarcoParameterUserWith(['view', 'viewActivity']);
    Sanctum::actingAs($actor);
    $parameter = EnasarcoParameter::factory()->create(['year' => 2026]);

    $this->getJson("/api/activity-log/enasarco-parameters/{$parameter->id}")
        ->assertOk()
        ->assertJsonStructure(['success', 'message', 'data' => ['items', 'next_cursor']]);

    Sanctum::actingAs(enasarcoParameterUserWith(['view']));
    $this->getJson("/api/activity-log/enasarco-parameters/{$parameter->id}")->assertForbidden();
});
