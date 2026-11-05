<?php

use App\Models\ChartAccount;
use App\Models\CostType;
use App\Models\RevenueType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function revenueAccount(): ChartAccount
{
    return ChartAccount::factory()->account(ChartAccount::factory()->revenue()->create())->create();
}

it('AC-008: creates a revenue sub-account under a revenue account', function () {
    Sanctum::actingAs(chartUserWith(['revenue-types.create']));
    $account = revenueAccount();

    $this->postJson('/api/revenue-types', ['chart_account_id' => $account->id, 'code' => 'RT.1', 'name' => 'Ricavo'])
        ->assertCreated()
        ->assertJsonPath('data.chart_account_id', $account->id)
        ->assertJsonPath('data.code', 'RT.1')
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.abilities', ['update' => false, 'delete' => false]);
});

it('AC-008: a group, a cost account or a missing parent is a 422 on create and update', function () {
    Sanctum::actingAs(chartUserWith(['revenue-types.create', 'revenue-types.update']));
    $revenueGroup = ChartAccount::factory()->revenue()->create();
    $costAccount = ChartAccount::factory()->account()->create();
    $revenueType = RevenueType::factory()->create();

    foreach ([$revenueGroup->id, $costAccount->id, 999999] as $invalid) {
        $this->postJson('/api/revenue-types', ['chart_account_id' => $invalid, 'code' => 'RT.2', 'name' => 'X'])
            ->assertStatus(422)->assertJsonValidationErrors('chart_account_id');
        $this->patchJson("/api/revenue-types/{$revenueType->id}", ['chart_account_id' => $invalid])
            ->assertStatus(422)->assertJsonValidationErrors('chart_account_id');
    }

    expect(RevenueType::where('code', 'RT.2')->exists())->toBeFalse();
});

it('AC-008: updates fields and moves to another revenue account', function () {
    Sanctum::actingAs(chartUserWith(['revenue-types.update']));
    $revenueType = RevenueType::factory()->create();
    $target = revenueAccount();

    $this->patchJson("/api/revenue-types/{$revenueType->id}", ['chart_account_id' => $target->id, 'name' => 'Nuovo nome', 'is_active' => false])
        ->assertOk()
        ->assertJsonPath('data.chart_account_id', $target->id)
        ->assertJsonPath('data.name', 'Nuovo nome')
        ->assertJsonPath('data.is_active', false);
});

it('AC-005: a code already used in the chart is a 422, the own code is accepted', function () {
    Sanctum::actingAs(chartUserWith(['revenue-types.create', 'revenue-types.update']));
    $account = revenueAccount();
    $revenueType = RevenueType::factory()->create(['code' => 'RC.1']);
    ChartAccount::factory()->create(['code' => 'RC.GROUP']);
    CostType::factory()->create(['code' => 'RC.COST']);

    foreach (['RC.1', 'RC.GROUP', 'RC.COST', $account->code] as $taken) {
        $this->postJson('/api/revenue-types', ['chart_account_id' => $account->id, 'code' => $taken, 'name' => 'Dup'])
            ->assertStatus(422)->assertJsonValidationErrors('code');
    }

    $this->patchJson("/api/revenue-types/{$revenueType->id}", ['code' => 'RC.GROUP'])->assertStatus(422)->assertJsonValidationErrors('code');
    $this->patchJson("/api/revenue-types/{$revenueType->id}", ['code' => 'RC.1', 'name' => 'Kept'])->assertOk();
});

it('AC-008: deletes always, 404 when missing', function () {
    Sanctum::actingAs(chartUserWith(['revenue-types.delete', 'revenue-types.view']));
    $revenueType = RevenueType::factory()->create();

    $this->getJson("/api/revenue-types/{$revenueType->id}")->assertOk()->assertJsonPath('data.id', $revenueType->id);
    $this->deleteJson("/api/revenue-types/{$revenueType->id}")->assertOk()->assertJsonPath('data', null);
    $this->assertDatabaseMissing('revenue_types', ['id' => $revenueType->id]);
    $this->deleteJson("/api/revenue-types/{$revenueType->id}")->assertNotFound();
    $this->getJson('/api/revenue-types/999999')->assertNotFound();
});

it('AC-003: without the permission every revenue-types endpoint is 403 and nothing changes', function () {
    Sanctum::actingAs(chartUserWith(['chart-accounts.view', 'chart-accounts.create', 'chart-accounts.update', 'chart-accounts.delete']));
    $account = revenueAccount();
    $revenueType = RevenueType::factory()->create(['name' => 'Original']);

    $this->getJson("/api/revenue-types/{$revenueType->id}")->assertForbidden();
    $this->postJson('/api/revenue-types', ['chart_account_id' => $account->id, 'code' => 'RF.1', 'name' => 'X'])->assertForbidden();
    $this->patchJson("/api/revenue-types/{$revenueType->id}", ['name' => 'Changed'])->assertForbidden();
    $this->deleteJson("/api/revenue-types/{$revenueType->id}")->assertForbidden();

    expect($revenueType->fresh()->name)->toBe('Original')->and(RevenueType::where('code', 'RF.1')->exists())->toBeFalse();
});
