<?php

use App\Models\ChartAccount;
use App\Models\CostType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function placedCostType(string $groupCode, string $accountCode, string $name): CostType
{
    $group = ChartAccount::query()->where('code', $groupCode)->first()
        ?? ChartAccount::factory()->create(['code' => $groupCode, 'name' => 'Servizi '.$groupCode]);
    $account = ChartAccount::factory()->account($group)->create(['code' => $accountCode, 'name' => 'Conto '.$accountCode]);

    return CostType::factory()->create(['name' => $name, 'code' => $accountCode.'.1', 'chart_account_id' => $account->id]);
}

it('AC-016: for-select adds code, path, group and account, null for an unplaced type', function () {
    Sanctum::actingAs(costTypeUserWith([]));
    CostType::query()->delete();
    $placed = placedCostType('91', '91.01', 'Consulenze');
    $unplaced = CostType::factory()->create(['name' => 'Zeta', 'code' => null]);

    $items = collect($this->getJson('/api/cost-types/for-select')->assertOk()->json('items'))->keyBy('id');

    expect($items[$placed->id])->toMatchArray([
        'label' => 'Consulenze',
        'code' => '91.01.1',
        'path' => '91 Servizi 91 > 91.01 Conto 91.01',
        'group' => ['code' => '91', 'name' => 'Servizi 91'],
        'account' => ['code' => '91.01', 'name' => 'Conto 91.01'],
        'meta' => ['color' => 'slate', 'nature' => 'direct'],
    ])->and($items[$unplaced->id])->toMatchArray(['code' => null, 'path' => null, 'group' => null, 'account' => null])
        ->and($items[$unplaced->id])->toHaveKeys(['code', 'path', 'group', 'account']);
});

it('AC-016: placed types come first by natural code, then the unplaced by name', function () {
    Sanctum::actingAs(costTypeUserWith([]));
    CostType::query()->delete();
    CostType::factory()->create(['name' => 'Alfa unplaced']);
    placedCostType('91', '91.10', 'Dieci');
    placedCostType('91', '91.2', 'Due');
    $group = ChartAccount::factory()->create(['code' => '90']);
    $first = CostType::factory()->create(['name' => 'Primo', 'code' => '90.01', 'chart_account_id' => ChartAccount::factory()->account($group)->create()->id]);

    $ids = collect($this->getJson('/api/cost-types/for-select')->assertOk()->json('items'))->pluck('label')->all();

    expect($ids)->toBe(['Primo', 'Due', 'Dieci', 'Alfa unplaced']);
});

it('AC-016: search matches the code as well as the name', function () {
    Sanctum::actingAs(costTypeUserWith([]));
    CostType::query()->delete();
    placedCostType('91', '91.01', 'Consulenze');
    CostType::factory()->create(['name' => 'Altro', 'code' => null]);

    $this->getJson('/api/cost-types/for-select?search=91.01')->assertOk()
        ->assertJsonCount(1, 'items')->assertJsonPath('items.0.label', 'Consulenze')->assertJsonPath('pagination.total', 1);
    $this->getJson('/api/cost-types/for-select?search=Altro')->assertOk()->assertJsonCount(1, 'items');
});

it('AC-016: paging and ids hydration keep their behaviour', function () {
    Sanctum::actingAs(costTypeUserWith([]));
    CostType::query()->delete();
    foreach (['A', 'B', 'C'] as $name) {
        CostType::factory()->create(['name' => $name]);
    }
    $inactive = CostType::factory()->inactive()->create(['name' => 'Spenta']);

    $page = $this->getJson('/api/cost-types/for-select?offset=1&limit=1')->assertOk();
    expect($page->json('pagination.total'))->toBe(3)->and(collect($page->json('items'))->pluck('label')->all())->toBe(['B']);

    $hydrated = $this->getJson("/api/cost-types/for-select?limit=1&ids[]={$inactive->id}")->assertOk();
    expect($hydrated->json('pagination.total'))->toBe(3)->and(collect($hydrated->json('items'))->pluck('label')->all())->toBe(['A', 'Spenta']);
});

it('AC-016: no query per row (placement is eager loaded)', function () {
    Sanctum::actingAs(costTypeUserWith([]));
    CostType::query()->delete();
    foreach (range(1, 6) as $n) {
        placedCostType('8'.$n, '8'.$n.'.01', 'Voce '.$n);
    }

    DB::enableQueryLog();
    $this->getJson('/api/cost-types/for-select')->assertOk()->assertJsonCount(6, 'items');
    $selects = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'chart_accounts'))->count();

    expect($selects)->toBeLessThanOrEqual(2);
});
