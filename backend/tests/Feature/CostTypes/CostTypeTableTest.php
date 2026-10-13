<?php

use App\Models\CostType;
use App\Models\PurchaseOrderLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('AC-016: the cost-types grid exposes the contract columns, lines_count and abilities', function () {
    Sanctum::actingAs(costTypeUserWith(['viewAny', 'view', 'update']));
    $line = PurchaseOrderLine::factory()->create();

    $rows = collect($this->postJson('/api/tables/cost-types/rows', ['startRow' => 0, 'endRow' => 50])
        ->assertOk()->json('items'));

    $used = $rows->firstWhere('id', $line->cost_type_id);

    expect($rows)->toHaveCount(5)
        ->and(array_keys($used))->toEqualCanonicalizing(['id', 'name', 'description', 'nature', 'color', 'sort_order', 'is_active', 'lines_count', 'abilities', 'actions', 'editable'])
        ->and($used['lines_count'])->toBe(1)
        ->and($used['abilities'])->toBe(['update' => true, 'delete' => false])
        ->and($rows->firstWhere('name', 'Fitti')['lines_count'])->toBe(0);
});

it('AC-016: the cost-types grid needs the view permission (403)', function () {
    Sanctum::actingAs(costTypeUserWith(['create']));

    $this->postJson('/api/tables/cost-types/rows', ['startRow' => 0, 'endRow' => 10])->assertForbidden();
});

it('AC-016: the grid filters on the allow-listed is_active column', function () {
    Sanctum::actingAs(costTypeUserWith(['viewAny', 'view']));
    CostType::factory()->inactive()->create(['name' => 'Spenta']);

    $names = collect($this->postJson('/api/tables/cost-types/rows', [
        'startRow' => 0,
        'endRow' => 50,
        'filterModel' => ['is_active' => ['filterType' => 'boolean', 'type' => 'equals', 'filter' => false]],
    ])->assertOk()->json('items'))->pluck('name')->all();

    expect($names)->toBe(['Spenta']);
});
