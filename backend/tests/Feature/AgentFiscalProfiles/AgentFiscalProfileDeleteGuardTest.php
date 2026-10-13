<?php

use App\Models\AgentFiscalProfile;
use App\Models\CommissionSettlement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('M-3: deleting a profile referenced by a settlement is a 409 and nothing is removed', function () {
    Sanctum::actingAs(agentFiscalProfileUserWith(['delete']));
    $settlement = CommissionSettlement::factory()->create();

    $this->deleteJson("/api/agent-fiscal-profiles/{$settlement->agent_fiscal_profile_id}")
        ->assertStatus(409)
        ->assertJsonPath('message', 'This fiscal profile is used by a commission settlement.');

    $this->assertDatabaseHas('agent_fiscal_profiles', ['id' => $settlement->agent_fiscal_profile_id]);
});

it('M-3: the guard also holds for issued and cancelled settlements', function (string $state) {
    Sanctum::actingAs(agentFiscalProfileUserWith(['delete']));
    $settlement = CommissionSettlement::factory()->{$state}()->create();

    $this->deleteJson("/api/agent-fiscal-profiles/{$settlement->agent_fiscal_profile_id}")->assertStatus(409);
})->with(['issued', 'cancelled']);

it('M-3: bulk delete refuses the referenced profile and removes the free one', function () {
    Sanctum::actingAs(agentFiscalProfileUserWith(['viewAny', 'delete']));
    $used = CommissionSettlement::factory()->create()->agentFiscalProfile;
    $free = AgentFiscalProfile::factory()->create();

    $response = $this->postJson('/api/tables/agent-fiscal-profiles/bulk-delete', ['ids' => [$used->id, $free->id]])->assertOk();

    expect($response->json('data.deleted'))->toBe(1)
        ->and(collect($response->json('data.failed'))->firstWhere('id', $used->id)['reason'])->toBe('guarded');
    $this->assertDatabaseHas('agent_fiscal_profiles', ['id' => $used->id]);
    $this->assertDatabaseMissing('agent_fiscal_profiles', ['id' => $free->id]);
});

it('M-3: an unreferenced profile is still deletable', function () {
    Sanctum::actingAs(agentFiscalProfileUserWith(['delete']));
    $profile = AgentFiscalProfile::factory()->create();

    $this->deleteJson("/api/agent-fiscal-profiles/{$profile->id}")->assertNoContent();
});
