<?php

use App\Models\AgentFiscalProfile;
use App\Models\CostType;
use App\Models\PaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('AC-015: payment_method_id and cost_type_id are saved and returned with {id,name}', function () {
    Sanctum::actingAs(agentFiscalProfileUserWith(['view', 'create', 'update']));
    $method = PaymentMethod::factory()->create(['name' => 'Bonifico 30gg']);
    $costType = CostType::factory()->create(['name' => 'Provvigioni agenti']);

    $id = $this->postJson('/api/agent-fiscal-profiles', agentFiscalProfilePayload([
        'payment_method_id' => $method->id, 'cost_type_id' => $costType->id,
    ]))->assertCreated()
        ->assertJsonPath('data.payment_method_id', $method->id)
        ->assertJsonPath('data.payment_method.name', 'Bonifico 30gg')
        ->assertJsonPath('data.cost_type_id', $costType->id)
        ->assertJsonPath('data.cost_type.name', 'Provvigioni agenti')
        ->json('data.id');

    $this->getJson("/api/agent-fiscal-profiles/{$id}")
        ->assertJsonPath('data.payment_method.id', $method->id)
        ->assertJsonPath('data.cost_type.id', $costType->id);

    // PATCH of another field keeps both defaults.
    $this->patchJson("/api/agent-fiscal-profiles/{$id}", ['notes' => 'x'])->assertOk()
        ->assertJsonPath('data.payment_method_id', $method->id)
        ->assertJsonPath('data.cost_type_id', $costType->id);
});

it('AC-015: both fields are optional and null by default', function () {
    Sanctum::actingAs(agentFiscalProfileUserWith(['create']));

    $this->postJson('/api/agent-fiscal-profiles', agentFiscalProfilePayload())
        ->assertCreated()
        ->assertJsonPath('data.payment_method_id', null)
        ->assertJsonPath('data.payment_method', null)
        ->assertJsonPath('data.cost_type', null);
});

it('AC-015: unknown ids are a 422', function () {
    Sanctum::actingAs(agentFiscalProfileUserWith(['create']));

    $this->postJson('/api/agent-fiscal-profiles', agentFiscalProfilePayload(['payment_method_id' => 999999, 'cost_type_id' => 999999]))
        ->assertUnprocessable()->assertJsonValidationErrors(['payment_method_id', 'cost_type_id']);
});

it('AC-015: read-only fields reject a change, hidden ones are dropped from the detail', function () {
    $user = agentFiscalProfileUserWith(['view', 'update']);
    restrictAgentFiscalProfileField($user, 'payment_method_id', visible: true, editable: false);
    restrictAgentFiscalProfileField($user, 'cost_type_id', visible: false, editable: false);
    Sanctum::actingAs($user);
    $profile = AgentFiscalProfile::factory()->create();
    $method = PaymentMethod::factory()->create();

    $this->patchJson("/api/agent-fiscal-profiles/{$profile->id}", ['payment_method_id' => $method->id])
        ->assertUnprocessable()->assertJsonValidationErrors('payment_method_id');

    $data = $this->getJson("/api/agent-fiscal-profiles/{$profile->id}")->assertOk()->json('data');
    expect($data)->toHaveKeys(['payment_method_id', 'payment_method'])
        ->and($data)->not->toHaveKeys(['cost_type_id', 'cost_type']);
});
