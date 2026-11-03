<?php

use App\Models\ChartAccount;
use App\Models\CostType;
use App\Models\RevenueType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('AC-004: creates a group with its kind', function () {
    Sanctum::actingAs(chartUserWith(['chart-accounts.create']));

    $this->postJson('/api/chart-accounts', ['level' => 'group', 'kind' => 'revenue', 'code' => 'T80', 'name' => 'Nuovo mastro'])
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.level', 'group')
        ->assertJsonPath('data.parent_id', null)
        ->assertJsonPath('data.kind', 'revenue')
        ->assertJsonPath('data.children_count', 0)
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.abilities', ['update' => false, 'delete' => false]);

    $this->assertDatabaseHas('chart_accounts', ['code' => 'T80', 'level' => 'group', 'kind' => 'revenue']);
});

it('AC-004: creates an account under a group and exposes the inherited kind', function () {
    Sanctum::actingAs(chartUserWith(['chart-accounts.create']));
    $group = ChartAccount::factory()->revenue()->create();

    $this->postJson('/api/chart-accounts', ['level' => 'account', 'parent_id' => $group->id, 'code' => 'T80.01', 'name' => 'Conto'])
        ->assertCreated()
        ->assertJsonPath('data.level', 'account')
        ->assertJsonPath('data.kind', 'revenue')
        ->assertJsonPath('data.parent_id', $group->id);

    $this->assertDatabaseHas('chart_accounts', ['code' => 'T80.01', 'kind' => null]);
});

it('AC-004: rejects invalid tree shapes with 422 and no write', function (array $payload, string $field) {
    Sanctum::actingAs(chartUserWith(['chart-accounts.create']));
    $group = ChartAccount::factory()->create();
    $account = ChartAccount::factory()->account($group)->create();
    $payload = array_map(fn ($v) => match ($v) {
        '@group' => $group->id,
        '@account' => $account->id,
        default => $v,
    }, $payload);
    $before = ChartAccount::count();

    $this->postJson('/api/chart-accounts', ['code' => 'T99', 'name' => 'X', ...$payload])
        ->assertStatus(422)
        ->assertJsonValidationErrors($field);

    expect(ChartAccount::count())->toBe($before);
})->with([
    'account under an account' => [['level' => 'account', 'parent_id' => '@account'], 'parent_id'],
    'account without parent' => [['level' => 'account'], 'parent_id'],
    'account with kind' => [['level' => 'account', 'parent_id' => '@group', 'kind' => 'cost'], 'kind'],
    'group with parent' => [['level' => 'group', 'kind' => 'cost', 'parent_id' => '@group'], 'parent_id'],
    'group without kind' => [['level' => 'group'], 'kind'],
    'invalid level' => [['level' => 'sub', 'kind' => 'cost'], 'level'],
    'bad code format' => [['level' => 'group', 'kind' => 'cost', 'code' => 'a b'], 'code'],
    'code too long' => [['level' => 'group', 'kind' => 'cost', 'code' => str_repeat('1', 21)], 'code'],
]);

it('AC-005: a code used anywhere in the chart is rejected on create, accepted for the own code on update', function () {
    Sanctum::actingAs(chartUserWith(['chart-accounts.create', 'chart-accounts.update']));
    $group = ChartAccount::factory()->create(['code' => 'U1']);
    $account = ChartAccount::factory()->account($group)->create(['code' => 'U1.01']);
    CostType::factory()->create(['chart_account_id' => $account->id, 'code' => 'U1.01.001']);
    RevenueType::factory()->create(['code' => 'U9.01.001']);

    foreach (['U1', 'U1.01', 'U1.01.001', 'U9.01.001'] as $taken) {
        $this->postJson('/api/chart-accounts', ['level' => 'group', 'kind' => 'cost', 'code' => $taken, 'name' => 'Dup'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');
    }

    $this->putJson("/api/chart-accounts/{$group->id}", ['code' => 'U1', 'name' => 'Same code'])->assertOk()->assertJsonPath('data.name', 'Same code');
    $this->putJson("/api/chart-accounts/{$group->id}", ['code' => 'U1.01'])->assertStatus(422)->assertJsonValidationErrors('code');
});

it('AC-006: moves an account under another group of the same kind, rejects other kinds', function () {
    Sanctum::actingAs(chartUserWith(['chart-accounts.update']));
    $costGroup = ChartAccount::factory()->create();
    $otherCost = ChartAccount::factory()->create();
    $revenueGroup = ChartAccount::factory()->revenue()->create();
    $account = ChartAccount::factory()->account($costGroup)->create();

    $this->patchJson("/api/chart-accounts/{$account->id}", ['parent_id' => $revenueGroup->id])->assertStatus(422)->assertJsonValidationErrors('parent_id');
    $this->patchJson("/api/chart-accounts/{$account->id}", ['parent_id' => $account->id])->assertStatus(422)->assertJsonValidationErrors('parent_id');
    $this->patchJson("/api/chart-accounts/{$account->id}", ['parent_id' => $otherCost->id])
        ->assertOk()
        ->assertJsonPath('data.parent_id', $otherCost->id)
        ->assertJsonPath('data.kind', 'cost');
});

it('AC-006: the kind of a group with accounts is frozen, level never changes', function () {
    Sanctum::actingAs(chartUserWith(['chart-accounts.update']));
    $group = ChartAccount::factory()->create();
    $empty = ChartAccount::factory()->create();
    ChartAccount::factory()->account($group)->create();

    $this->patchJson("/api/chart-accounts/{$group->id}", ['kind' => 'revenue'])->assertStatus(422)->assertJsonValidationErrors('kind');
    $this->patchJson("/api/chart-accounts/{$group->id}", ['kind' => 'cost', 'name' => 'Renamed'])->assertOk();
    $this->patchJson("/api/chart-accounts/{$empty->id}", ['kind' => 'revenue'])->assertOk()->assertJsonPath('data.kind', 'revenue');
    $this->patchJson("/api/chart-accounts/{$empty->id}", ['level' => 'account'])->assertStatus(422)->assertJsonValidationErrors('level');
    $this->patchJson("/api/chart-accounts/{$group->id}", ['parent_id' => $empty->id])->assertStatus(422)->assertJsonValidationErrors('parent_id');

    expect($group->fresh()->kind->value)->toBe('cost')->and($group->fresh()->level->value)->toBe('group');
});

it('AC-007: deleting a group with accounts or an account with sub-accounts is a 409', function () {
    Sanctum::actingAs(chartUserWith(['chart-accounts.delete']));
    $group = ChartAccount::factory()->create();
    $withCost = ChartAccount::factory()->account($group)->create();
    $revenueGroup = ChartAccount::factory()->revenue()->create();
    $withRevenue = ChartAccount::factory()->account($revenueGroup)->create();
    CostType::factory()->create(['chart_account_id' => $withCost->id, 'code' => 'D1']);
    RevenueType::factory()->create(['chart_account_id' => $withRevenue->id]);

    foreach ([$group, $withCost, $revenueGroup, $withRevenue] as $node) {
        $this->deleteJson("/api/chart-accounts/{$node->id}")
            ->assertStatus(409)
            ->assertJsonPath('success', false);
        $this->assertDatabaseHas('chart_accounts', ['id' => $node->id]);
    }
});

it('AC-007: deleting an entry without children succeeds', function () {
    Sanctum::actingAs(chartUserWith(['chart-accounts.delete']));
    $group = ChartAccount::factory()->create();
    $account = ChartAccount::factory()->account($group)->create();

    $this->deleteJson("/api/chart-accounts/{$account->id}")->assertOk()->assertJsonPath('data', null);
    $this->deleteJson("/api/chart-accounts/{$group->id}")->assertOk();

    $this->assertDatabaseMissing('chart_accounts', ['id' => $group->id]);
    $this->assertDatabaseMissing('chart_accounts', ['id' => $account->id]);
});

it('AC-003: show returns the resource with children_count and 404 when missing', function () {
    Sanctum::actingAs(chartUserWith(['chart-accounts.view', 'chart-accounts.update']));
    $group = ChartAccount::factory()->create();
    ChartAccount::factory()->account($group)->count(2)->create();

    $this->getJson("/api/chart-accounts/{$group->id}")
        ->assertOk()
        ->assertJsonPath('data.children_count', 2)
        ->assertJsonPath('data.kind', 'cost')
        ->assertJsonPath('data.abilities', ['update' => true, 'delete' => false]);
    $this->getJson('/api/chart-accounts/999999')->assertNotFound();
});

it('AC-003: without the permission every chart-accounts endpoint is 403 and nothing changes', function () {
    Sanctum::actingAs(chartUserWith(['cost-types.view']));
    $group = ChartAccount::factory()->create(['name' => 'Original']);

    $this->getJson("/api/chart-accounts/{$group->id}")->assertForbidden();
    $this->postJson('/api/chart-accounts', ['level' => 'group', 'kind' => 'cost', 'code' => 'F1', 'name' => 'X'])->assertForbidden();
    $this->putJson("/api/chart-accounts/{$group->id}", ['name' => 'Changed'])->assertForbidden();
    $this->deleteJson("/api/chart-accounts/{$group->id}")->assertForbidden();

    expect($group->fresh()->name)->toBe('Original')->and(ChartAccount::where('code', 'F1')->exists())->toBeFalse();
});
