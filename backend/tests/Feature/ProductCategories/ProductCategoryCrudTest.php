<?php

use App\Models\Attribute;
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
// show — GET /api/product-categories/{productCategory}
// ---------------------------------------------------------------------------

it('show: 200 with own attributes, inherited_attributes and parent summary', function () {
    $actor = productCategoryUserWith(['view']);
    $root = ProductCategory::factory()->create(['name' => 'Root']);
    $child = ProductCategory::factory()->childOf($root)->create(['name' => 'Child']);
    $rootAttribute = Attribute::factory()->create(['code' => 'root_attr']);
    $childAttribute = Attribute::factory()->create(['code' => 'child_attr']);
    $root->attributes()->attach($rootAttribute->id, ['is_required' => true, 'sort_order' => 0, 'context' => 'quote']);
    $child->attributes()->attach($childAttribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'quote']);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/product-categories/{$child->id}")
        ->assertOk()
        ->assertJsonPath('data.name', 'Child')
        ->assertJsonPath('data.parent.name', 'Root');

    expect(collect($response->json('data.attributes'))->pluck('code')->all())->toBe(['child_attr']);
    expect(collect($response->json('data.inherited_attributes'))->pluck('code')->all())->toBe(['root_attr']);
    expect($response->json('permissions'))->toHaveKeys(['resource', 'fields', 'actions']);
});

it('show: 403 without product-categories.view', function () {
    $actor = productCategoryUserWith([]);
    $target = ProductCategory::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/product-categories/{$target->id}")->assertForbidden();
});

it('show: 404 for a non-existent category', function () {
    $actor = productCategoryUserWith(['view']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/product-categories/999999')->assertNotFound();
});

// ---------------------------------------------------------------------------
// create — POST /api/product-categories (AC-007)
// ---------------------------------------------------------------------------

it('create: 201 + persists, syncing attribute assignments with pivot data', function () {
    $actor = productCategoryUserWith(['create']);
    $attribute = Attribute::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/product-categories', [
        'name' => 'Electronics',
        'attributes' => [['attribute_id' => $attribute->id, 'context' => 'quote', 'is_required' => true, 'sort_order' => 2]],
    ])->assertCreated()->assertJsonPath('data.name', 'Electronics');

    expect($response->json('data.attributes.0.attribute_id'))->toBe($attribute->id)
        ->and($response->json('data.attributes.0.is_required'))->toBeTrue()
        ->and($response->json('data.attributes.0.sort_order'))->toBe(2)
        ->and($response->json('data.attributes.0.context'))->toBe('quote');

    $this->assertDatabaseHas('attribute_category', ['attribute_id' => $attribute->id, 'context' => 'quote', 'is_required' => 1, 'sort_order' => 2]);
});

it('create: both inheritance flags default to true and are opted out independently', function () {
    $actor = productCategoryUserWith(['create']);
    $root = ProductCategory::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/product-categories', ['name' => 'Default'])
        ->assertCreated()
        ->assertJsonPath('data.inherits_product_attributes', true)
        ->assertJsonPath('data.inherits_quote_attributes', true);

    $this->postJson('/api/product-categories', [
        'name' => 'OptOut',
        'parent_id' => $root->id,
        'inherits_product_attributes' => false,
    ])->assertCreated()
        ->assertJsonPath('data.inherits_product_attributes', false)
        // Decoupled: opting the Product section out leaves Offerta inheriting.
        ->assertJsonPath('data.inherits_quote_attributes', true);

    $this->assertDatabaseHas('product_categories', [
        'name' => 'OptOut',
        'inherits_product_attributes' => 0,
        'inherits_quote_attributes' => 1,
    ]);
});

it('create: 403 without product-categories.create', function () {
    $actor = productCategoryUserWith([]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/product-categories', ['name' => 'Nope'])->assertForbidden();
});

it('create: 422 with a non-existent parent_id or a duplicated attribute_id/context pair', function () {
    $actor = productCategoryUserWith(['create']);
    $attribute = Attribute::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/product-categories', ['name' => 'X', 'parent_id' => 999999])
        ->assertStatus(422)->assertJsonValidationErrors('parent_id');

    $this->postJson('/api/product-categories', [
        'name' => 'X',
        'attributes' => [
            ['attribute_id' => $attribute->id, 'context' => 'quote'],
            ['attribute_id' => $attribute->id, 'context' => 'quote'],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('attributes.1.attribute_id');
});

it('create: the SAME attribute may be assigned to both contexts in one request', function () {
    $actor = productCategoryUserWith(['create']);
    $attribute = Attribute::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/product-categories', [
        'name' => 'Both',
        'attributes' => [
            ['attribute_id' => $attribute->id, 'context' => 'product'],
            ['attribute_id' => $attribute->id, 'context' => 'quote'],
        ],
    ])->assertCreated();

    expect(collect($response->json('data.attributes'))->pluck('context')->sort()->values()->all())
        ->toBe(['product', 'quote']);
});

it('create: 422 when an attributes row is missing context', function () {
    $actor = productCategoryUserWith(['create']);
    $attribute = Attribute::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/product-categories', [
        'name' => 'X',
        'attributes' => [['attribute_id' => $attribute->id]],
    ])->assertStatus(422)->assertJsonValidationErrors('attributes.0.context');
});

// ---------------------------------------------------------------------------
// update — PUT/PATCH /api/product-categories/{productCategory} (AC-009, AC-010)
// ---------------------------------------------------------------------------

it('update: attributes is a full-replace sync preserving pivot data', function () {
    $actor = productCategoryUserWith(['update']);
    $category = ProductCategory::factory()->create();
    $oldAttribute = Attribute::factory()->create();
    $category->attributes()->attach($oldAttribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'quote']);
    $newAttribute = Attribute::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/product-categories/{$category->id}", [
        'attributes' => [['attribute_id' => $newAttribute->id, 'context' => 'quote', 'is_required' => true, 'sort_order' => 5]],
    ])->assertOk();

    expect($category->fresh()->attributes->pluck('id')->all())->toBe([$newAttribute->id]);
    $this->assertDatabaseHas('attribute_category', ['attribute_id' => $newAttribute->id, 'context' => 'quote', 'is_required' => 1, 'sort_order' => 5]);
});

it('update: toggling a context inheritance flag off empties only that context inherited list', function () {
    $actor = productCategoryUserWith(['view', 'update']);
    $root = ProductCategory::factory()->create();
    $child = ProductCategory::factory()->childOf($root)->create();
    $quoteAttr = Attribute::factory()->create(['code' => 'root_attr']);
    $productAttr = Attribute::factory()->create(['code' => 'root_product_attr']);
    $root->attributes()->attach($quoteAttr->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'quote']);
    $root->attributes()->attach($productAttr->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'product']);
    Sanctum::actingAs($actor);

    $before = $this->getJson("/api/product-categories/{$child->id}")->assertOk();
    expect(collect($before->json('data.inherited_attributes'))->pluck('code')->all())
        ->toEqualCanonicalizing(['root_attr', 'root_product_attr']);

    $after = $this->patchJson("/api/product-categories/{$child->id}", ['inherits_quote_attributes' => false])
        ->assertOk()
        ->assertJsonPath('data.inherits_quote_attributes', false)
        ->assertJsonPath('data.inherits_product_attributes', true);

    // The Product section keeps inheriting: the two barriers are independent.
    expect(collect($after->json('data.inherited_attributes'))->pluck('code')->all())->toBe(['root_product_attr']);
});

it('update: parent_id = self → 422 (anti-cycle)', function () {
    $actor = productCategoryUserWith(['update']);
    $category = ProductCategory::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/product-categories/{$category->id}", ['parent_id' => $category->id])->assertStatus(422);
});

it('update: parent_id = own descendant → 422 (anti-cycle)', function () {
    $actor = productCategoryUserWith(['update']);
    $root = ProductCategory::factory()->create();
    $child = ProductCategory::factory()->childOf($root)->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/product-categories/{$root->id}", ['parent_id' => $child->id])->assertStatus(422);

    expect($root->fresh()->parent_id)->toBeNull();
});

it('update: 403 without product-categories.update', function () {
    $actor = productCategoryUserWith([]);
    $target = ProductCategory::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/product-categories/{$target->id}", ['name' => 'Nope'])->assertForbidden();
});

// ---------------------------------------------------------------------------
// delete — DELETE /api/product-categories/{productCategory} (AC-011)
// ---------------------------------------------------------------------------

it('delete: 204 when the category has no children and no products', function () {
    $actor = productCategoryUserWith(['delete']);
    $target = ProductCategory::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/product-categories/{$target->id}")->assertNoContent();
    $this->assertDatabaseMissing('product_categories', ['id' => $target->id]);
});

it('delete: 409 when it has children', function () {
    $actor = productCategoryUserWith(['delete']);
    $parent = ProductCategory::factory()->create();
    ProductCategory::factory()->childOf($parent)->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/product-categories/{$parent->id}")->assertStatus(409);
});

it('delete: 409 when it has products', function () {
    $actor = productCategoryUserWith(['delete']);
    $category = ProductCategory::factory()->create();
    Product::factory()->create(['category_id' => $category->id]);
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/product-categories/{$category->id}")->assertStatus(409);
});

it('delete: 403 without product-categories.delete', function () {
    $actor = productCategoryUserWith([]);
    $target = ProductCategory::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/product-categories/{$target->id}")->assertForbidden();
});
