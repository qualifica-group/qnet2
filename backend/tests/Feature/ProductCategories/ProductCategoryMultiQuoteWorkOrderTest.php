<?php

use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

/**
 * `multi_quote_work_order` (spec 0267, D-1): the branch-ROOT owned flag
 * allowing revenue lines of different quotes to share one work order. Same
 * inheritance contract as `generates_contract` (root authors, subtree
 * mirrors, a divergent child write is a 422), but it defaults to FALSE.
 */
uses(RefreshDatabase::class);

it('AC-001: a root persists its own flag and a fresh root defaults to false', function () {
    Sanctum::actingAs(productCategoryUserWith(['create']));

    $this->postJson('/api/product-categories', ['name' => 'Qualifica', 'multi_quote_work_order' => true])
        ->assertCreated()
        ->assertJsonPath('data.multi_quote_work_order', true);

    $this->postJson('/api/product-categories', ['name' => 'Consulenza'])
        ->assertCreated()
        ->assertJsonPath('data.multi_quote_work_order', false);
});

it('AC-001: a child inherits the root flag at creation even when the payload omits it', function () {
    $root = ProductCategory::factory()->create(['multi_quote_work_order' => true]);
    Sanctum::actingAs(productCategoryUserWith(['create']));

    $this->postJson('/api/product-categories', ['name' => 'SOA', 'parent_id' => $root->id])
        ->assertCreated()
        ->assertJsonPath('data.multi_quote_work_order', true);
});

it('AC-001: 422 on multi_quote_work_order when a child diverges at creation, nothing persisted', function () {
    $root = ProductCategory::factory()->create(['multi_quote_work_order' => true]);
    Sanctum::actingAs(productCategoryUserWith(['create']));

    $this->postJson('/api/product-categories', [
        'name' => 'SOA',
        'parent_id' => $root->id,
        'multi_quote_work_order' => false,
    ])->assertStatus(422);

    expect(ProductCategory::query()->where('name', 'SOA')->exists())->toBeFalse();
});

it('AC-001: a child echoing the inherited flag is accepted as a no-op', function () {
    $root = ProductCategory::factory()->create(['multi_quote_work_order' => true]);
    Sanctum::actingAs(productCategoryUserWith(['create']));

    $this->postJson('/api/product-categories', [
        'name' => 'SOA',
        'parent_id' => $root->id,
        'multi_quote_work_order' => true,
    ])->assertCreated()->assertJsonPath('data.multi_quote_work_order', true);
});

it('AC-001: a category with a parent may not write a diverging flag on update', function () {
    $root = ProductCategory::factory()->create(['multi_quote_work_order' => true]);
    $child = ProductCategory::factory()->childOf($root)->create(['multi_quote_work_order' => true]);
    Sanctum::actingAs(productCategoryUserWith(['update']));

    $this->patchJson("/api/product-categories/{$child->id}", ['multi_quote_work_order' => false])
        ->assertStatus(422);

    expect($child->fresh()->multi_quote_work_order)->toBeTrue();
});

it('AC-001: setting the root flag cascades to every descendant', function () {
    $root = ProductCategory::factory()->create(['multi_quote_work_order' => false]);
    $child = ProductCategory::factory()->childOf($root)->create(['multi_quote_work_order' => false]);
    $grandchild = ProductCategory::factory()->childOf($child)->create(['multi_quote_work_order' => false]);
    Sanctum::actingAs(productCategoryUserWith(['update']));

    $this->patchJson("/api/product-categories/{$root->id}", ['multi_quote_work_order' => true])
        ->assertOk()
        ->assertJsonPath('data.multi_quote_work_order', true);

    expect($child->fresh()->multi_quote_work_order)->toBeTrue()
        ->and($grandchild->fresh()->multi_quote_work_order)->toBeTrue();
});

it('AC-001: reparenting under a differently-flagged root re-aligns the moved subtree', function () {
    $multiRoot = ProductCategory::factory()->create(['multi_quote_work_order' => true]);
    $plainRoot = ProductCategory::factory()->create(['multi_quote_work_order' => false]);
    $moved = ProductCategory::factory()->childOf($multiRoot)->create(['multi_quote_work_order' => true]);
    $movedChild = ProductCategory::factory()->childOf($moved)->create(['multi_quote_work_order' => true]);
    Sanctum::actingAs(productCategoryUserWith(['update']));

    $this->patchJson("/api/product-categories/{$moved->id}", ['parent_id' => $plainRoot->id])
        ->assertOk()
        ->assertJsonPath('data.multi_quote_work_order', false);

    expect($movedChild->fresh()->multi_quote_work_order)->toBeFalse()
        ->and($multiRoot->fresh()->multi_quote_work_order)->toBeTrue();
});

it('show exposes the flag, its source category and the field permission', function () {
    $root = ProductCategory::factory()->create(['name' => 'Qualifica', 'multi_quote_work_order' => true]);
    $child = ProductCategory::factory()->childOf($root)->create(['multi_quote_work_order' => true]);
    Sanctum::actingAs(productCategoryUserWith(['view', 'update']));

    $this->getJson("/api/product-categories/{$root->id}")
        ->assertOk()
        ->assertJsonPath('data.multi_quote_work_order', true)
        ->assertJsonPath('data.multi_quote_work_order_source_category', null)
        ->assertJsonPath('permissions.fields.multi_quote_work_order.editable', true);

    $this->getJson("/api/product-categories/{$child->id}")
        ->assertOk()
        ->assertJsonPath('data.multi_quote_work_order_source_category.id', $root->id)
        ->assertJsonPath('data.multi_quote_work_order_source_category.name', 'Qualifica');
});

it('tree nodes carry the effective flag on every level', function () {
    $root = ProductCategory::factory()->create(['name' => 'Qualifica', 'multi_quote_work_order' => true]);
    ProductCategory::factory()->childOf($root)->create(['name' => 'SOA', 'multi_quote_work_order' => true]);
    Sanctum::actingAs(productCategoryUserWith(['viewAny']));

    $rootNode = collect($this->getJson('/api/product-categories/tree')->assertOk()->json('data'))->firstWhere('name', 'Qualifica');

    expect($rootNode['multi_quote_work_order'])->toBeTrue()
        ->and($rootNode['children'][0]['multi_quote_work_order'])->toBeTrue();
});
