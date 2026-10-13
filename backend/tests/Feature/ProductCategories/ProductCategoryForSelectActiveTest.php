<?php

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0208 — an effectively inactive category (own flag false OR any
// ancestor's) leaves the pickers; `include_inactive` and `ids[]` are the
// opt-out and the hydration exemption.
uses(RefreshDatabase::class);

if (! function_exists('inactiveBranchFixture')) {
    /**
     * @return array{root: ProductCategory, child: ProductCategory, active: ProductCategory}
     */
    function inactiveBranchFixture(): array
    {
        $root = ProductCategory::factory()->create(['name' => 'Dormant root', 'parent_id' => null, 'is_active' => false]);
        // The child keeps its OWN flag true: it is inactive only by cascade.
        $child = ProductCategory::factory()->create(['name' => 'Dormant child', 'parent_id' => $root->id, 'is_active' => true]);
        $active = ProductCategory::factory()->create(['name' => 'Live', 'parent_id' => null, 'is_active' => true]);

        return ['root' => $root, 'child' => $child, 'active' => $active];
    }
}

it('for-select omits an inactive root and its descendants from items and total (AC-004)', function () {
    ['root' => $root, 'child' => $child, 'active' => $active] = inactiveBranchFixture();
    Sanctum::actingAs(User::factory()->create());

    $response = $this->getJson('/api/product-categories/for-select')->assertOk();
    $ids = collect($response->json('items'))->pluck('id');

    expect($ids->all())->toBe([$active->id])
        ->and($ids)->not->toContain($root->id, $child->id)
        ->and($response->json('pagination.total'))->toBe(1);
});

it('for-select include_inactive=1 returns them, is_selectable still applied (AC-005)', function () {
    ['root' => $root, 'child' => $child] = inactiveBranchFixture();
    $unselectable = ProductCategory::factory()->create(['parent_id' => null, 'is_active' => false, 'is_selectable' => false]);
    Sanctum::actingAs(User::factory()->create());

    $response = $this->getJson('/api/product-categories/for-select?include_inactive=1')->assertOk();
    $ids = collect($response->json('items'))->pluck('id');

    expect($ids)->toContain($root->id, $child->id)
        ->and($ids)->not->toContain($unselectable->id)
        ->and($response->json('pagination.total'))->toBe(3);
});

it('for-select rejects a non boolean include_inactive', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/product-categories/for-select?include_inactive=maybe')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['include_inactive']);
});

it('for-select hydrates ids[] of an inactive category without counting it (AC-006)', function () {
    ['child' => $child] = inactiveBranchFixture();
    Sanctum::actingAs(User::factory()->create());

    $response = $this->getJson('/api/product-categories/for-select?ids[]='.$child->id)->assertOk();

    expect(collect($response->json('items'))->pluck('id'))->toContain($child->id)
        ->and($response->json('pagination.total'))->toBe(1);
});

it('branches for-select excludes an inactive branch and hydrates it with ids[] (AC-007)', function () {
    ['root' => $root, 'child' => $child] = inactiveBranchFixture();
    $liveRoot = ProductCategory::factory()->create(['parent_id' => null, 'is_active' => true]);
    ProductCategory::factory()->create(['parent_id' => $liveRoot->id]);
    // Container reachable only through an inactive ancestor.
    ProductCategory::factory()->create(['parent_id' => $child->id]);
    Sanctum::actingAs(User::factory()->create());

    $response = $this->getJson('/api/product-category-branches/for-select')->assertOk();
    $ids = collect($response->json('items'))->pluck('id');

    expect($ids->all())->toBe([$liveRoot->id])
        ->and($response->json('pagination.total'))->toBe(1);

    $hydrated = $this->getJson('/api/product-category-branches/for-select?ids[]='.$root->id)->assertOk();

    expect(collect($hydrated->json('items'))->pluck('id'))->toContain($root->id, $liveRoot->id);
});

it('products for-select excludes products of an inactive branch, hydrates them, and keeps the total honest (AC-008)', function () {
    ['child' => $child, 'active' => $active] = inactiveBranchFixture();
    Permission::findOrCreate('products.viewAny');
    $actor = User::factory()->create();
    $actor->givePermissionTo('products.viewAny');
    $hidden = Product::factory()->create(['category_id' => $child->id]);
    $visible = Product::factory()->create(['category_id' => $active->id]);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/products/for-select')->assertOk();

    expect(collect($response->json('items'))->pluck('id')->all())->toBe([$visible->id])
        ->and($response->json('pagination.total'))->toBe(1);

    $scoped = $this->getJson('/api/products/for-select?category_ids[]='.$child->id)->assertOk();

    expect($scoped->json('items'))->toBe([])
        ->and($scoped->json('pagination.total'))->toBe(0);

    $hydrated = $this->getJson('/api/products/for-select?ids[]='.$hidden->id)->assertOk();

    expect(collect($hydrated->json('items'))->pluck('id'))->toContain($hidden->id, $visible->id)
        ->and($hydrated->json('pagination.total'))->toBe(1);
});
