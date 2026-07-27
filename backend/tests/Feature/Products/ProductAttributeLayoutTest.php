<?php

declare(strict_types=1);

use App\Models\Attribute;
use App\Models\AttributeLayout;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0062: ProductResource's additive `attribute_layout` (context=product,
// form_mode=view) — AC-007 regression (attribute_values/applicable_attributes
// stay byte-for-byte identical) plus the new field's own behavior.

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

it('AC-007 regression: with no attribute_layouts row, attribute_layout is null and attribute_values/applicable_attributes are unaffected', function () {
    $actor = productAttributeUserWith(['view']);
    $category = ProductCategory::factory()->create();
    $attribute = Attribute::factory()->create(['code' => 'material', 'type' => 'text']);
    $category->attributes()->attach($attribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'product']);
    $product = Product::factory()->create(['category_id' => $category->id, 'attribute_values' => ['material' => 'steel']]);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/products/{$product->id}")->assertOk();

    expect($response->json('data.attribute_layout'))->toBeNull();
    expect($response->json('data.attribute_values'))->toBe(['material' => 'steel']);
    expect(collect($response->json('data.applicable_attributes'))->pluck('code')->all())->toBe(['material']);
});

it('the category\'s (product, view) configured layout is resolved and exposed on the detail', function () {
    $actor = productAttributeUserWith(['view']);
    $category = ProductCategory::factory()->create();
    $attribute = Attribute::factory()->create(['code' => 'material', 'type' => 'text']);
    $category->attributes()->attach($attribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'product']);
    AttributeLayout::factory()->for($category, 'productCategory')
        ->withCodes(['material'])
        ->create(['context' => 'product', 'form_mode' => 'view']);
    $product = Product::factory()->create(['category_id' => $category->id]);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/products/{$product->id}")->assertOk();

    expect($response->json('data.attribute_layout.sections.0.rows.0.items.0.attribute_code'))->toBe('material');
});

it('a layout configured only for form_mode=create drives the detail too via cross-mode fallback (spec 0062 revised)', function () {
    // Requirement change: a single saved layout is meant to apply across every
    // form mode. With no dedicated `view` row, the `create` layout resolves for
    // the read-only detail instead of the previous flat fallback.
    $actor = productAttributeUserWith(['view']);
    $category = ProductCategory::factory()->create();
    $attribute = Attribute::factory()->create(['code' => 'material', 'type' => 'text']);
    $category->attributes()->attach($attribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'product']);
    AttributeLayout::factory()->for($category, 'productCategory')
        ->withCodes(['material'])
        ->create(['context' => 'product', 'form_mode' => 'create']);
    $product = Product::factory()->create(['category_id' => $category->id]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/products/{$product->id}")->assertOk()
        ->assertJsonPath('data.attribute_layout.sections.0.rows.0.items.0.attribute_code', 'material');
});

it('a dedicated form_mode=view layout wins over the create fallback (exact mode precedence)', function () {
    $actor = productAttributeUserWith(['view']);
    $category = ProductCategory::factory()->create();
    foreach (['material', 'colour'] as $code) {
        $attribute = Attribute::factory()->create(['code' => $code, 'type' => 'text']);
        $category->attributes()->attach($attribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'product']);
    }
    AttributeLayout::factory()->for($category, 'productCategory')
        ->withCodes(['material'])
        ->create(['context' => 'product', 'form_mode' => 'create']);
    AttributeLayout::factory()->for($category, 'productCategory')
        ->withCodes(['colour'])
        ->create(['context' => 'product', 'form_mode' => 'view']);
    $product = Product::factory()->create(['category_id' => $category->id]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/products/{$product->id}")->assertOk()
        ->assertJsonPath('data.attribute_layout.sections.0.rows.0.items.0.attribute_code', 'colour');
});
