<?php

use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

// Spec 0092 D-4 — the branch picker of the quote-workflow criteria editor:
// the categories a branch criterion may point at are the CONTAINERS (those
// with at least one child), exactly the population the sibling
// /product-categories/for-select hides behind its unconditional
// `is_selectable` filter (spec 0074 D-4, asserted here as non-regression).
uses(RefreshDatabase::class);

if (! function_exists('branchTree')) {
    /**
     * @return array{root: ProductCategory, middle: ProductCategory, leaf: ProductCategory}
     */
    function branchTree(): array
    {
        $root = ProductCategory::factory()->create(['name' => 'Consulenza', 'parent_id' => null, 'is_selectable' => false]);
        $middle = ProductCategory::factory()->create(['name' => 'ISO', 'parent_id' => $root->id]);
        $leaf = ProductCategory::factory()->create(['name' => '10854', 'parent_id' => $middle->id]);

        return ['root' => $root, 'middle' => $middle, 'leaf' => $leaf];
    }
}

it('requires authentication (401, AC-018)', function () {
    $this->getJson('/api/product-category-branches/for-select')->assertUnauthorized();
});

it('allows an actor without product-categories.view (200 — ADR 0011 amended, AC-018)', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/product-category-branches/for-select')
        ->assertOk()
        ->assertJsonStructure([
            'items' => [],
            'pagination' => ['total', 'offset', 'limit'],
        ]);
});

it('lists the containers, is_selectable ignored, and omits childless categories (AC-015)', function () {
    ['root' => $root, 'middle' => $middle, 'leaf' => $leaf] = branchTree();
    $selectableLeaf = ProductCategory::factory()->create(['parent_id' => null, 'is_selectable' => true]);

    Sanctum::actingAs(User::factory()->create());

    $response = $this->getJson('/api/product-category-branches/for-select')->assertOk();
    $ids = collect($response->json('items'))->pluck('id');

    expect($ids)->toContain($root->id, $middle->id)
        ->and($ids)->not->toContain($leaf->id)
        ->and($ids)->not->toContain($selectableLeaf->id);
});

it('emits the minimal {id, label} item, with the category name as label (AC-015)', function () {
    ['root' => $root] = branchTree();
    Sanctum::actingAs(User::factory()->create());

    $item = collect($this->getJson('/api/product-category-branches/for-select')->assertOk()->json('items'))
        ->firstWhere('id', $root->id);

    expect($item)->toBe(['id' => $root->id, 'label' => 'Consulenza']);
});

it('searches by name while keeping the has-children scope, and paginates (AC-016)', function () {
    ['root' => $root] = branchTree();
    // Matches the search term but has no child: must stay out.
    ProductCategory::factory()->create(['name' => 'Consulenza IT', 'parent_id' => null]);

    Sanctum::actingAs(User::factory()->create());

    $response = $this->getJson('/api/product-category-branches/for-select?search=consulenza')->assertOk();

    expect(collect($response->json('items'))->pluck('id')->all())->toBe([$root->id])
        ->and($response->json('pagination.total'))->toBe(1);

    $firstPage = $this->getJson('/api/product-category-branches/for-select?limit=1&offset=0')->assertOk();

    expect($firstPage->json('items'))->toHaveCount(1)
        ->and($firstPage->json('pagination.total'))->toBe(2)
        // Ordered by name over the CONTAINERS only: 'Consulenza' then 'ISO'
        // (the leaf '10854' would sort first but is not a container).
        ->and($firstPage->json('items.0.id'))->toBe($root->id);
});

it('hydrates an explicitly requested id that no longer has children (AC-017)', function () {
    ['middle' => $middle, 'leaf' => $leaf] = branchTree();
    $leaf->delete();

    Sanctum::actingAs(User::factory()->create());

    $ids = collect(
        $this->getJson("/api/product-category-branches/for-select?ids[]={$middle->id}")->assertOk()->json('items')
    )->pluck('id');

    expect($middle->fresh()->children()->count())->toBe(0)
        ->and($ids)->toContain($middle->id);
});

it('leaves the destination for-select filtering is_selectable (AC-019, non-regression)', function () {
    ['root' => $root, 'leaf' => $leaf] = branchTree();
    Sanctum::actingAs(User::factory()->create());

    $ids = collect($this->getJson('/api/product-categories/for-select')->assertOk()->json('items'))->pluck('id');

    expect($ids)->not->toContain($root->id)
        ->and($ids)->toContain($leaf->id);
});
