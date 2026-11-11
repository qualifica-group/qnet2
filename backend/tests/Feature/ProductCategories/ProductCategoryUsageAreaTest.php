<?php

use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

/**
 * Spec 0389: the root-owned `usage_area` setting of a product category
 * (AC-001/AC-002) and its grid column.
 */
uses(RefreshDatabase::class);

it('AC-001: a fresh root without usage_area is used everywhere; a root may pick its area', function () {
    Sanctum::actingAs(productCategoryUserWith(['create']));

    $this->postJson('/api/product-categories', ['name' => 'Consulenza'])
        ->assertCreated()
        ->assertJsonPath('data.usage_area', 'all');

    $this->postJson('/api/product-categories', ['name' => 'Formazione', 'usage_area' => 'requests'])
        ->assertCreated()
        ->assertJsonPath('data.usage_area', 'requests');
});

it('AC-001: a child inherits the area of its root and shows where it comes from', function () {
    $root = ProductCategory::factory()->create(['name' => 'Formazione', 'usage_area' => 'requests']);
    Sanctum::actingAs(productCategoryUserWith(['create', 'view']));

    $childId = $this->postJson('/api/product-categories', ['name' => 'GOL - Lazio', 'parent_id' => $root->id])
        ->assertCreated()
        ->assertJsonPath('data.usage_area', 'requests')
        ->json('data.id');

    $this->getJson("/api/product-categories/{$childId}")
        ->assertOk()
        ->assertJsonPath('data.usage_area_source_category.id', $root->id);
});

it('AC-001: changing the root area realigns the whole branch', function () {
    $root = ProductCategory::factory()->create(['usage_area' => 'all']);
    $child = ProductCategory::factory()->childOf($root)->create(['usage_area' => 'all']);
    $grandchild = ProductCategory::factory()->childOf($child)->create(['usage_area' => 'all']);
    Sanctum::actingAs(productCategoryUserWith(['update']));

    $this->patchJson("/api/product-categories/{$root->id}", ['usage_area' => 'opportunities'])
        ->assertOk()
        ->assertJsonPath('data.usage_area', 'opportunities');

    expect($child->fresh()->usage_area->value)->toBe('opportunities')
        ->and($grandchild->fresh()->usage_area->value)->toBe('opportunities');
});

it('AC-001: moving a category under another root takes that root area', function () {
    $requests = ProductCategory::factory()->create(['usage_area' => 'requests']);
    $opportunities = ProductCategory::factory()->create(['usage_area' => 'opportunities']);
    $child = ProductCategory::factory()->childOf($requests)->create(['usage_area' => 'requests']);
    Sanctum::actingAs(productCategoryUserWith(['update']));

    $this->patchJson("/api/product-categories/{$child->id}", ['parent_id' => $opportunities->id])->assertOk();

    expect($child->fresh()->usage_area->value)->toBe('opportunities');
});

it('AC-002: a child may not define an area different from its root', function () {
    $root = ProductCategory::factory()->create(['usage_area' => 'requests']);
    $child = ProductCategory::factory()->childOf($root)->create(['usage_area' => 'requests']);
    Sanctum::actingAs(productCategoryUserWith(['create', 'update']));

    $this->postJson('/api/product-categories', ['name' => 'X', 'parent_id' => $root->id, 'usage_area' => 'opportunities'])
        ->assertStatus(422);
    $this->patchJson("/api/product-categories/{$child->id}", ['usage_area' => 'all'])->assertStatus(422);
    $this->patchJson("/api/product-categories/{$child->id}", ['usage_area' => 'requests'])->assertOk();

    expect($child->fresh()->usage_area->value)->toBe('requests');
});

it('rejects an unknown area', function () {
    Sanctum::actingAs(productCategoryUserWith(['create']));

    $this->postJson('/api/product-categories', ['name' => 'Bad', 'usage_area' => 'everywhere'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('usage_area');
});

it('the tree carries the area of every node', function () {
    $root = ProductCategory::factory()->create(['usage_area' => 'opportunities']);
    ProductCategory::factory()->childOf($root)->create(['usage_area' => 'opportunities']);
    Sanctum::actingAs(productCategoryUserWith(['viewAny']));

    $this->getJson('/api/product-categories/tree')
        ->assertOk()
        ->assertJsonPath('data.0.usage_area', 'opportunities')
        ->assertJsonPath('data.0.children.0.usage_area', 'opportunities');
});

it('D-5: the categories grid shows the area as a filterable badge column', function () {
    ProductCategory::factory()->create(['name' => 'Formazione', 'usage_area' => 'requests']);
    ProductCategory::factory()->create(['name' => 'Consulenza', 'usage_area' => 'all']);
    Sanctum::actingAs(productCategoryUserWith(['viewAny']));

    $column = collect($this->getJson('/api/tables/product-categories/columns')->assertOk()->json('data.columns'))
        ->firstWhere('id', 'usage_area');
    expect($column['type'])->toBe('badge')
        ->and($column['enumKey'])->toBe('category_usage_area');

    $names = collect($this->postJson('/api/tables/product-categories/rows', [
        'startRow' => 0, 'endRow' => 25,
        'filterModel' => ['usage_area' => ['filterType' => 'set', 'values' => ['requests']]],
    ])->assertOk()->json('items'))->pluck('name')->all();

    expect($names)->toBe(['Formazione']);
});
