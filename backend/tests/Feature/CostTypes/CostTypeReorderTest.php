<?php

use App\Models\CostType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('AC-003: reorder with every row resequences sort_order', function () {
    Sanctum::actingAs(costTypeUserWith(['update']));
    $ids = CostType::query()->orderBy('sort_order')->pluck('id')->reverse()->values()->all();

    $this->postJson('/api/cost-types/reorder', ['ordered_ids' => $ids])
        ->assertOk()
        ->assertJsonPath('data.0.id', $ids[0])
        ->assertJsonPath('data.0.sort_order', 10);

    expect(CostType::query()->orderBy('sort_order')->pluck('id')->all())->toBe($ids);
});

it('AC-003: an incomplete or duplicated set is 422', function () {
    Sanctum::actingAs(costTypeUserWith(['update']));
    $ids = CostType::query()->orderBy('sort_order')->pluck('id')->all();

    $this->postJson('/api/cost-types/reorder', ['ordered_ids' => array_slice($ids, 1)])->assertStatus(422);
    $this->postJson('/api/cost-types/reorder', ['ordered_ids' => [...$ids, $ids[0]]])->assertStatus(422);
});

it('AC-003: reorder without cost-types.update is 403', function () {
    Sanctum::actingAs(costTypeUserWith(['view']));
    $ids = CostType::query()->pluck('id')->all();

    $this->postJson('/api/cost-types/reorder', ['ordered_ids' => $ids])->assertForbidden();
});

it('AC-003: a new cost type goes last, after a reorder too', function () {
    Sanctum::actingAs(costTypeUserWith(['create', 'update']));
    $ids = CostType::query()->orderBy('sort_order')->pluck('id')->reverse()->values()->all();
    $this->postJson('/api/cost-types/reorder', ['ordered_ids' => $ids])->assertOk();

    $newId = $this->postJson('/api/cost-types', ['name' => 'Ultimo', 'nature' => 'direct', 'color' => 'slate'])
        ->assertCreated()->json('data.id');

    expect(CostType::query()->orderBy('sort_order')->pluck('id')->last())->toBe($newId);
});
