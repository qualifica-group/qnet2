<?php

use App\Models\Attribute;
use App\Models\BusinessFunction;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('productCategoryUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function productCategoryUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("product-categories.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("product-categories.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-012 — GET /api/product-categories/tree
// ---------------------------------------------------------------------------

it('tree: 403 without product-categories.viewAny', function () {
    $actor = productCategoryUserWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/product-categories/tree')->assertForbidden();
});

it('tree: nested roots→children with attributes_count/products_count', function () {
    $actor = productCategoryUserWith(['viewAny']);
    $root = ProductCategory::factory()->create(['name' => 'Root']);
    $child = ProductCategory::factory()->childOf($root)->create(['name' => 'Child']);
    $attribute = Attribute::factory()->create();
    $root->attributes()->attach($attribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'quote']);
    Product::factory()->create(['category_id' => $child->id]);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/product-categories/tree')->assertOk()->json('data');

    $rootNode = collect($data)->firstWhere('name', 'Root');
    expect($rootNode['attributes_count'])->toBe(1)
        ->and($rootNode['products_count'])->toBe(0);

    $childNode = collect($rootNode['children'])->firstWhere('name', 'Child');
    expect($childNode)->not->toBeNull()
        ->and($childNode['products_count'])->toBe(1)
        ->and($childNode['children'])->toBe([]);
});

// ---------------------------------------------------------------------------
// AC-018 (REV) — each tree node exposes its OWN business_function_id, not
// the effective/inherited one; the frontend resolves inheritance itself by
// walking parent_id on this cached tree.
// ---------------------------------------------------------------------------

it('tree: each node carries its OWN business_function_id (null if it has none, even when it inherits one)', function () {
    $actor = productCategoryUserWith(['viewAny']);
    $function = BusinessFunction::factory()->create();
    $root = ProductCategory::factory()->create(['name' => 'Root', 'business_function_id' => $function->id]);
    $child = ProductCategory::factory()->childOf($root)->create(['name' => 'Child']);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/product-categories/tree')->assertOk()->json('data');

    $rootNode = collect($data)->firstWhere('name', 'Root');
    expect($rootNode['business_function_id'])->toBe($function->id);

    // Child inherits the function but the tree exposes only its OWN value —
    // the frontend, not this endpoint, resolves the inherited state.
    $childNode = collect($rootNode['children'])->firstWhere('name', 'Child');
    expect($childNode['business_function_id'])->toBeNull();
});

// ---------------------------------------------------------------------------
// AC-008 — GET /api/product-categories/{productCategory}/effective-attributes
// ---------------------------------------------------------------------------

it('effective-attributes: A→B→C inherits a1,a2,a3 ancestors-first with correct `inherited` flags', function () {
    $actor = productCategoryUserWith(['view']);
    $a = ProductCategory::factory()->create(['name' => 'A']);
    $b = ProductCategory::factory()->childOf($a)->create(['name' => 'B']);
    $c = ProductCategory::factory()->childOf($b)->create(['name' => 'C']);
    $a1 = Attribute::factory()->create(['code' => 'a1']);
    $a2 = Attribute::factory()->create(['code' => 'a2']);
    $a3 = Attribute::factory()->create(['code' => 'a3']);
    $a->attributes()->attach($a1->id, ['is_required' => true, 'sort_order' => 0, 'context' => 'quote']);
    $b->attributes()->attach($a2->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'quote']);
    $c->attributes()->attach($a3->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'quote']);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/product-categories/{$c->id}/effective-attributes?context=quote")->assertOk();
    $data = $response->json('data');

    expect(collect($data)->pluck('code')->all())->toBe(['a1', 'a2', 'a3']);
    expect(collect($data)->pluck('inherited')->all())->toBe([true, true, false]);
    expect(collect($data)->firstWhere('code', 'a1')['is_required'])->toBeTrue();
});

it('effective-attributes: a category opting out inherits nothing — only its own', function () {
    $actor = productCategoryUserWith(['view']);
    $root = ProductCategory::factory()->create(['name' => 'Root']);
    $child = ProductCategory::factory()->childOf($root)->notInheriting()->create(['name' => 'Child']);
    $rootAttr = Attribute::factory()->create(['code' => 'root_attr']);
    $childAttr = Attribute::factory()->create(['code' => 'child_attr']);
    $root->attributes()->attach($rootAttr->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'quote']);
    $child->attributes()->attach($childAttr->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'quote']);
    Sanctum::actingAs($actor);

    $data = $this->getJson("/api/product-categories/{$child->id}/effective-attributes?context=quote")->assertOk()->json('data');

    expect(collect($data)->pluck('code')->all())->toBe(['child_attr']);
});

it('effective-attributes: barrier cuts a descendant off from everything above the opted-out node', function () {
    // Root[A] → Child(inherit=OFF)[B] → Grandchild(inherit=ON)[C] ⇒ grandchild sees B,C (A cut).
    $actor = productCategoryUserWith(['view']);
    $root = ProductCategory::factory()->create(['name' => 'Root']);
    $child = ProductCategory::factory()->childOf($root)->notInheriting()->create(['name' => 'Child']);
    $grandchild = ProductCategory::factory()->childOf($child)->create(['name' => 'Grandchild']);
    $a = Attribute::factory()->create(['code' => 'a']);
    $b = Attribute::factory()->create(['code' => 'b']);
    $c = Attribute::factory()->create(['code' => 'c']);
    $root->attributes()->attach($a->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'quote']);
    $child->attributes()->attach($b->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'quote']);
    $grandchild->attributes()->attach($c->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'quote']);
    Sanctum::actingAs($actor);

    $data = $this->getJson("/api/product-categories/{$grandchild->id}/effective-attributes?context=quote")->assertOk()->json('data');

    expect(collect($data)->pluck('code')->all())->toBe(['b', 'c']);
    expect(collect($data)->pluck('inherited')->all())->toBe([true, false]);
});

it('effective-attributes: ENUM attribute carries its options', function () {
    $actor = productCategoryUserWith(['view']);
    $category = ProductCategory::factory()->create();
    $enum = Attribute::factory()->enum(2)->create();
    $category->attributes()->attach($enum->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'quote']);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/product-categories/{$category->id}/effective-attributes?context=quote")->assertOk();

    expect($response->json('data.0.options'))->toHaveCount(2);
});

it('effective-attributes: 403 for an actor with no product-categories/products access', function () {
    $actor = productCategoryUserWith([]);
    $category = ProductCategory::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/product-categories/{$category->id}/effective-attributes?context=quote")->assertForbidden();
});

it('effective-attributes: allowed for an actor with only products.create (no product-categories access)', function () {
    Permission::findOrCreate('products.create');
    $actor = User::factory()->create();
    $actor->givePermissionTo('products.create');
    $category = ProductCategory::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/product-categories/{$category->id}/effective-attributes?context=quote")->assertOk();
});

it('effective-attributes: 404 for a non-existent category', function () {
    $actor = productCategoryUserWith(['view']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/product-categories/999999/effective-attributes?context=quote')->assertNotFound();
});
