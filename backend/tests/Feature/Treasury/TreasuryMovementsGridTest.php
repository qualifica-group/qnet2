<?php

use App\Enums\TreasuryMovementDirection;
use App\Models\Company;
use App\Models\FinancialAccount;
use App\Models\TreasuryMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// Spec 0355 — `treasury-movements` grid: columns, computed inflow/outflow, allow-listed sort/filter, authz.

const TREASURY_GRID_COLUMNS = [
    'movement_date', 'type', 'account', 'counterpart_account', 'company', 'description', 'sub_account',
    'registry', 'work_order', 'inflow', 'outflow', 'created_by',
];

/**
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function treasuryRows(array $extra = []): array
{
    return test()->postJson('/api/tables/treasury-movements/rows', ['startRow' => 0, 'endRow' => 50, ...$extra])
        ->assertOk()->json();
}

it('AC-009: the grid exposes the contract columns and computes inflow and outflow from the type', function () {
    Sanctum::actingAs(treasuryUserWith(['viewAny']));
    $company = Company::factory()->create();
    $from = FinancialAccount::factory()->create(['company_id' => $company->id, 'name' => 'Banca A']);
    $to = FinancialAccount::factory()->create(['company_id' => $company->id, 'name' => 'Cassa B']);
    TreasuryMovement::factory()->income()->create(['financial_account_id' => $from->id, 'amount' => '10.00', 'movement_date' => '2026-03-01']);
    TreasuryMovement::factory()->expense()->create(['financial_account_id' => $from->id, 'amount' => '20.00', 'movement_date' => '2026-03-02']);
    TreasuryMovement::factory()->transfer($from, $to)->create(['amount' => '30.00', 'movement_date' => '2026-03-03']);
    TreasuryMovement::factory()->adjustment()->create(['financial_account_id' => $from->id, 'amount' => '40.00', 'movement_date' => '2026-03-04']);
    TreasuryMovement::factory()->adjustment(TreasuryMovementDirection::Out)->create(['financial_account_id' => $from->id, 'amount' => '50.00', 'movement_date' => '2026-03-05']);

    $columns = collect(test()->getJson('/api/tables/treasury-movements/columns')->assertOk()->json('data.columns'))->pluck('id')->all();
    expect($columns)->toContain(...TREASURY_GRID_COLUMNS);

    $rows = collect(treasuryRows()['items'])->keyBy('movement_date');
    expect($rows['2026-03-01'])->toMatchArray(['inflow' => '10.00', 'outflow' => null, 'type' => 'income'])
        ->and($rows['2026-03-02'])->toMatchArray(['inflow' => null, 'outflow' => '20.00'])
        ->and($rows['2026-03-03'])->toMatchArray(['inflow' => null, 'outflow' => '30.00', 'account' => 'Banca A', 'counterpart_account' => 'Cassa B', 'company' => $company->denomination])
        ->and($rows['2026-03-04'])->toMatchArray(['inflow' => '40.00', 'outflow' => null])
        ->and($rows['2026-03-05'])->toMatchArray(['inflow' => null, 'outflow' => '50.00']);
});

it('AC-009: sort and set filters on the derived columns go through the allow-list', function () {
    Sanctum::actingAs(treasuryUserWith(['viewAny']));
    $alfa = FinancialAccount::factory()->create(['name' => 'Alfa']);
    $beta = FinancialAccount::factory()->create(['name' => 'Beta']);
    TreasuryMovement::factory()->income()->create(['financial_account_id' => $beta->id, 'description' => 'second']);
    TreasuryMovement::factory()->income()->create(['financial_account_id' => $alfa->id, 'description' => 'first']);

    $sorted = collect(treasuryRows(['sortModel' => [['colId' => 'account', 'sort' => 'asc']]])['items'])->pluck('description')->all();
    expect($sorted)->toBe(['first', 'second']);

    $filtered = collect(treasuryRows(['filterModel' => ['account' => ['filterType' => 'set', 'values' => ['Beta']]]])['items'])->pluck('description')->all();
    expect($filtered)->toBe(['second']);

    $blankCounterpart = treasuryRows(['filterModel' => ['counterpart_account' => ['filterType' => 'set', 'values' => [null]]]])['items'];
    expect($blankCounterpart)->toHaveCount(2);
});

it('AC-009: the grid is 403 without viewAny and 200 with it', function () {
    Sanctum::actingAs(treasuryUserWith([]));
    $this->postJson('/api/tables/treasury-movements/rows', ['startRow' => 0, 'endRow' => 10])->assertForbidden();

    Sanctum::actingAs(treasuryUserWith(['viewAny']));
    $this->postJson('/api/tables/treasury-movements/rows', ['startRow' => 0, 'endRow' => 10])->assertOk();
});
