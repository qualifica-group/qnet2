<?php

use App\Models\CostType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('AC-002: create with a valid nature and color returns 201 and the new fields, placed last', function () {
    Sanctum::actingAs(costTypeUserWith(['create']));
    $lastOrder = CostType::query()->max('sort_order');

    $this->postJson('/api/cost-types', ['name' => 'Overhead', 'nature' => 'general', 'color' => 'violet'])
        ->assertCreated()
        ->assertJsonPath('data.nature', 'general')
        ->assertJsonPath('data.color', 'violet')
        ->assertJsonPath('data.sort_order', $lastOrder + 10);
});

it('AC-002: update changes nature and color', function () {
    Sanctum::actingAs(costTypeUserWith(['update']));
    $costType = CostType::factory()->create();

    $this->patchJson("/api/cost-types/{$costType->id}", ['nature' => 'indirect', 'color' => 'red'])
        ->assertOk()
        ->assertJsonPath('data.nature', 'indirect')
        ->assertJsonPath('data.color', 'red');
});

it('AC-002: nature or color outside the allow-list, or a sort_order, are 422', function (array $payload, string $field) {
    Sanctum::actingAs(costTypeUserWith(['create', 'update']));
    $costType = CostType::factory()->create();

    $this->postJson('/api/cost-types', $payload + ['name' => 'X1', 'nature' => 'direct', 'color' => 'slate'])
        ->assertStatus(422)->assertJsonValidationErrors($field);
    $this->patchJson("/api/cost-types/{$costType->id}", $payload)
        ->assertStatus(422)->assertJsonValidationErrors($field);
})->with([
    'nature' => [['nature' => 'unknown'], 'nature'],
    'color' => [['color' => '#ff0000'], 'color'],
    'sort_order' => [['sort_order' => 3], 'sort_order'],
]);

it('AC-002: nature and color are required on create', function () {
    Sanctum::actingAs(costTypeUserWith(['create']));

    $this->postJson('/api/cost-types', ['name' => 'NoNature'])
        ->assertStatus(422)->assertJsonValidationErrors(['nature', 'color']);
});

it('AC-002: write without the permission is 403', function () {
    Sanctum::actingAs(costTypeUserWith(['view']));
    $costType = CostType::factory()->create();

    $this->postJson('/api/cost-types', ['name' => 'Y1', 'nature' => 'direct', 'color' => 'slate'])->assertForbidden();
    $this->patchJson("/api/cost-types/{$costType->id}", ['nature' => 'general'])->assertForbidden();
});

it('AC-004: for-select exposes meta.color and meta.nature', function () {
    Sanctum::actingAs(costTypeUserWith([]));
    CostType::query()->delete();
    CostType::factory()->create(['name' => 'Solo', 'nature' => 'indirect', 'color' => 'amber']);

    $this->getJson('/api/cost-types/for-select')
        ->assertOk()
        ->assertJsonPath('items.0.meta', ['color' => 'amber', 'nature' => 'indirect']);
});

it('AC-004: the grid exposes the nature badge column (set filter, enum key) and the color column', function () {
    Sanctum::actingAs(costTypeUserWith(['viewAny', 'view']));
    CostType::factory()->create(['name' => 'Gen', 'nature' => 'general', 'color' => 'pink']);

    $columns = collect($this->getJson('/api/tables/cost-types/columns')->assertOk()->json('data.columns'))->keyBy('id');
    $rows = collect($this->postJson('/api/tables/cost-types/rows', ['startRow' => 0, 'endRow' => 50])->assertOk()->json('items'));
    $gen = $rows->firstWhere('name', 'Gen');

    expect($columns['nature'])->toMatchArray(['type' => 'badge', 'filterType' => 'set', 'enumKey' => 'cost_type_nature'])
        ->and(collect($columns['nature']['badges'])->pluck('value')->all())->toBe(['direct', 'indirect', 'general'])
        ->and($columns)->toHaveKey('color')
        ->and($gen['nature'])->toBe('general')
        ->and($gen['color'])->toBe('pink');

    $filtered = $this->postJson('/api/tables/cost-types/rows', [
        'startRow' => 0, 'endRow' => 50,
        'filterModel' => ['nature' => ['filterType' => 'set', 'values' => ['general']]],
    ])->assertOk()->json('items');

    expect(collect($filtered)->pluck('name')->all())->toBe(['Gen']);
});
