<?php

declare(strict_types=1);

use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0061: products.attribute_values, validated against the PRODUCT-context
// effective attributes of the product's category
// (App\Products\ProductAttributeResolver), reusing the SAME
// AttributeValueValidator/AttributeValueNormalizer pipeline as the
// Opportunity path (App\RequestManagement) — mirrors
// OpportunityAttributeValuesResourceTest's shape.

uses(RefreshDatabase::class);

if (! function_exists('productAttributeUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function productAttributeUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("products.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("products.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('productAttributeGenericFields')) {
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function productAttributeGenericFields(array $overrides = []): array
    {
        return array_merge(['cost' => 10, 'price' => 20, 'product_type' => 'SERVICE'], $overrides);
    }
}

// ---------------------------------------------------------------------------
// create — happy path
// ---------------------------------------------------------------------------

it('create: happy path persists attribute_values and exposes applicable_attributes', function () {
    $actor = productAttributeUserWith(['create']);
    $category = ProductCategory::factory()->create();
    $attribute = Attribute::factory()->create(['code' => 'material', 'type' => 'text']);
    $category->attributes()->attach($attribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'product']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/products', productAttributeGenericFields([
        'name' => 'Widget', 'category_id' => $category->id,
        'attribute_values' => ['material' => 'steel'],
    ]))->assertCreated();

    $response->assertJsonPath('data.attribute_values.material', 'steel');
    expect(collect($response->json('data.applicable_attributes'))->pluck('code')->all())->toBe(['material']);

    $product = Product::where('name', 'Widget')->firstOrFail();
    expect($product->attribute_values)->toBe(['material' => 'steel']);
});

it('show: no stored values -> attribute_values is an empty object, applicable_attributes still reflects the category', function () {
    $actor = productAttributeUserWith(['view']);
    $category = ProductCategory::factory()->create();
    $attribute = Attribute::factory()->create(['code' => 'material', 'type' => 'text']);
    $category->attributes()->attach($attribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'product']);
    $product = Product::factory()->create(['category_id' => $category->id, 'attribute_values' => null]);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/products/{$product->id}")->assertOk();

    expect($response->json('data.attribute_values'))->toBe([]);
    expect(collect($response->json('data.applicable_attributes'))->pluck('code')->all())->toBe(['material']);
});

// ---------------------------------------------------------------------------
// update — sparse merge
// ---------------------------------------------------------------------------

it('update: merges into the existing map (sparse) — an unset code keeps its persisted value', function () {
    $actor = productAttributeUserWith(['update']);
    $category = ProductCategory::factory()->create();
    $a = Attribute::factory()->create(['code' => 'a_field', 'type' => 'text']);
    $b = Attribute::factory()->create(['code' => 'b_field', 'type' => 'text']);
    $category->attributes()->attach($a->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'product']);
    $category->attributes()->attach($b->id, ['is_required' => false, 'sort_order' => 1, 'context' => 'product']);
    $product = Product::factory()->create(['category_id' => $category->id, 'attribute_values' => ['a_field' => 'first']]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/products/{$product->id}", ['attribute_values' => ['b_field' => 'second']])
        ->assertOk()
        ->assertJsonPath('data.attribute_values.a_field', 'first')
        ->assertJsonPath('data.attribute_values.b_field', 'second');

    expect($product->fresh()->attribute_values)->toBe(['a_field' => 'first', 'b_field' => 'second']);
});

// ---------------------------------------------------------------------------
// validation — unknown code / missing required / wrong type
// ---------------------------------------------------------------------------

it('create: 422 on a code outside the applicable set', function () {
    $actor = productAttributeUserWith(['create']);
    $category = ProductCategory::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/products', productAttributeGenericFields([
        'name' => 'Widget', 'category_id' => $category->id,
        'attribute_values' => ['ghost_field' => 'x'],
    ]))->assertStatus(422)->assertJsonValidationErrors('attribute_values.ghost_field');
});

it('create: 422 when a required attribute is submitted empty', function () {
    $actor = productAttributeUserWith(['create']);
    $category = ProductCategory::factory()->create();
    $required = Attribute::factory()->create(['code' => 'serial', 'type' => 'text']);
    $category->attributes()->attach($required->id, ['is_required' => true, 'sort_order' => 0, 'context' => 'product']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/products', productAttributeGenericFields([
        'name' => 'Widget', 'category_id' => $category->id,
        'attribute_values' => ['serial' => ''],
    ]))->assertStatus(422)->assertJsonValidationErrors('attribute_values.serial');
});

it('create: 422 on a wrong-type value (integer expected)', function () {
    $actor = productAttributeUserWith(['create']);
    $category = ProductCategory::factory()->create();
    $qty = Attribute::factory()->create(['code' => 'quantity', 'type' => 'integer']);
    $category->attributes()->attach($qty->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'product']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/products', productAttributeGenericFields([
        'name' => 'Widget', 'category_id' => $category->id,
        'attribute_values' => ['quantity' => 'not-a-number'],
    ]))->assertStatus(422)->assertJsonValidationErrors('attribute_values.quantity');
});

it('an Opportunity-context-only attribute is not applicable to the product (context isolation, other direction)', function () {
    $actor = productAttributeUserWith(['create']);
    $category = ProductCategory::factory()->create();
    $opportunityOnly = Attribute::factory()->create(['code' => 'opp_only', 'type' => 'text']);
    $category->attributes()->attach($opportunityOnly->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'opportunity']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/products', productAttributeGenericFields([
        'name' => 'Widget', 'category_id' => $category->id,
        'attribute_values' => ['opp_only' => 'x'],
    ]))->assertStatus(422)->assertJsonValidationErrors('attribute_values.opp_only');
});

// ---------------------------------------------------------------------------
// authz
// ---------------------------------------------------------------------------

it('create: 403 without products.create', function () {
    $category = ProductCategory::factory()->create();
    $actor = productAttributeUserWith([]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/products', productAttributeGenericFields([
        'name' => 'Widget', 'category_id' => $category->id,
        'attribute_values' => ['x' => 'y'],
    ]))->assertForbidden();
});

it('update: 403 without products.update', function () {
    $product = Product::factory()->create();
    $actor = productAttributeUserWith([]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/products/{$product->id}", ['attribute_values' => ['x' => 'y']])->assertForbidden();
});
