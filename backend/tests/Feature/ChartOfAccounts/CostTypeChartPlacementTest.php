<?php

use App\Models\ChartAccount;
use App\Models\CostType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('AC-009: placing a cost type under a cost account with a code moves it out of the unplaced list', function () {
    Sanctum::actingAs(chartUserWith(['cost-types.update', 'chart-accounts.view']));
    $account = ChartAccount::factory()->account()->create();
    $costType = CostType::factory()->create(['name' => 'Da collocare']);

    $this->patchJson("/api/cost-types/{$costType->id}", ['chart_account_id' => $account->id, 'code' => 'CP.1'])
        ->assertOk()
        ->assertJsonPath('data.chart_account_id', $account->id)
        ->assertJsonPath('data.code', 'CP.1');

    $tree = $this->getJson('/api/chart-of-accounts/tree')->assertOk()->json('data');
    expect(array_column($tree['unplaced_cost_types'], 'id'))->not->toContain($costType->id);

    $placed = collect($tree['groups'])->pluck('accounts')->flatten(1)->firstWhere('id', $account->id);
    expect(array_column($placed['sub_accounts'], 'id'))->toBe([$costType->id]);
});

it('AC-009: a revenue account, a group or a missing code are 422', function () {
    Sanctum::actingAs(chartUserWith(['cost-types.update', 'cost-types.create']));
    $revenueAccount = ChartAccount::factory()->account(ChartAccount::factory()->revenue()->create())->create();
    $costGroup = ChartAccount::factory()->create();
    $costAccount = ChartAccount::factory()->account()->create();
    $costType = CostType::factory()->create();

    $this->patchJson("/api/cost-types/{$costType->id}", ['chart_account_id' => $revenueAccount->id, 'code' => 'CP.2'])
        ->assertStatus(422)->assertJsonValidationErrors('chart_account_id');
    $this->patchJson("/api/cost-types/{$costType->id}", ['chart_account_id' => $costGroup->id, 'code' => 'CP.3'])
        ->assertStatus(422)->assertJsonValidationErrors('chart_account_id');
    $this->patchJson("/api/cost-types/{$costType->id}", ['chart_account_id' => $costAccount->id])
        ->assertStatus(422)->assertJsonValidationErrors('code');
    $this->postJson('/api/cost-types', ['name' => 'Nuovo', 'nature' => 'direct', 'color' => 'slate', 'chart_account_id' => $costAccount->id])
        ->assertStatus(422)->assertJsonValidationErrors('code');

    expect($costType->fresh()->chart_account_id)->toBeNull();
});

it('AC-005: a code taken elsewhere in the chart is a 422 for cost types, its own code is accepted', function () {
    Sanctum::actingAs(chartUserWith(['cost-types.update', 'cost-types.create']));
    $account = ChartAccount::factory()->account()->create();
    ChartAccount::factory()->create(['code' => 'CT.GROUP']);
    $costType = CostType::factory()->create(['chart_account_id' => $account->id, 'code' => 'CT.1']);

    $this->patchJson("/api/cost-types/{$costType->id}", ['chart_account_id' => $account->id, 'code' => 'CT.GROUP'])
        ->assertStatus(422)->assertJsonValidationErrors('code');
    $this->postJson('/api/cost-types', ['name' => 'Altro nuovo', 'nature' => 'direct', 'color' => 'slate', 'code' => 'CT.1'])
        ->assertStatus(422)->assertJsonValidationErrors('code');
    $this->patchJson("/api/cost-types/{$costType->id}", ['chart_account_id' => $account->id, 'code' => 'CT.1', 'color' => 'blue'])->assertOk();
});

it('AC-009: creating with account and code places the new cost type', function () {
    Sanctum::actingAs(chartUserWith(['cost-types.create']));
    $account = ChartAccount::factory()->account()->create();

    $this->postJson('/api/cost-types', ['name' => 'Sottoconto nuovo', 'nature' => 'indirect', 'color' => 'teal', 'chart_account_id' => $account->id, 'code' => 'CN.1'])
        ->assertCreated()
        ->assertJsonPath('data.chart_account_id', $account->id)
        ->assertJsonPath('data.code', 'CN.1');
});

it('AC-009: a PATCH without the two fields (Cost types page) keeps account and code', function () {
    Sanctum::actingAs(chartUserWith(['cost-types.update']));
    $account = ChartAccount::factory()->account()->create();
    $costType = CostType::factory()->create(['chart_account_id' => $account->id, 'code' => 'KP.1']);

    $this->putJson("/api/cost-types/{$costType->id}", ['name' => 'Rinominato', 'color' => 'blue'])
        ->assertOk()
        ->assertJsonPath('data.chart_account_id', $account->id)
        ->assertJsonPath('data.code', 'KP.1');

    expect($costType->fresh())->chart_account_id->toBe($account->id)->code->toBe('KP.1');
});

it('AC-009: explicit nulls unplace the cost type', function () {
    Sanctum::actingAs(chartUserWith(['cost-types.update']));
    $account = ChartAccount::factory()->account()->create();
    $costType = CostType::factory()->create(['chart_account_id' => $account->id, 'code' => 'KP.2']);

    $this->patchJson("/api/cost-types/{$costType->id}", ['chart_account_id' => null, 'code' => null])
        ->assertOk()
        ->assertJsonPath('data.chart_account_id', null)
        ->assertJsonPath('data.code', null);
});
