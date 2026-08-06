<?php

declare(strict_types=1);

use App\Enums\AttributeContext;
use App\Models\Attribute;
use App\Models\ProductCategory;
use App\Models\User;
use App\RequestManagement\AttributeSetResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0061: the context discriminator on the attribute_category pivot.
// Spec 0084 retires the Opportunity context (the dynamic "Informazioni
// aggiuntive" moved to the Offerta), leaving Product and Quote — so the
// invariant this suite guards is the SAME one, re-anchored: the two surviving
// contexts resolve, inherit and are stored completely independently, and an
// assignment made in one must never leak into the other's resolution.
//
// The former "absent context backfills to opportunity" regression is gone
// with the column default it tested: `context` is now named explicitly by
// every reader and writer (see the 422 case below).

uses(RefreshDatabase::class);

if (! function_exists('categoryContextUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function categoryContextUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
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
// REGRESSION — contexts never leak into one another
// ---------------------------------------------------------------------------

it('regression: AttributeSetResolver (quote context) never sees a Product-context attribute', function (): void {
    $category = ProductCategory::factory()->create();
    $quoteAttribute = Attribute::factory()->create(['code' => 'quote_only']);
    $productAttribute = Attribute::factory()->create(['code' => 'product_only']);

    $category->attributes()->attach($quoteAttribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'quote']);
    $category->attributes()->attach($productAttribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'product']);

    $resolved = app(AttributeSetResolver::class)->resolve([$category->id], AttributeContext::Quote);

    expect($resolved->pluck('code')->all())->toBe(['quote_only']);
});

it('regression: the retired opportunity context is no longer a valid enum value', function (): void {
    expect(AttributeContext::tryFrom('opportunity'))->toBeNull()
        ->and(array_column(AttributeContext::cases(), 'value'))->toBe(['product', 'quote']);
});

// ---------------------------------------------------------------------------
// Category sync stores both contexts (API)
// ---------------------------------------------------------------------------

it('sync: the SAME attribute can be assigned to both Product and Offerta in one request', function (): void {
    $actor = categoryContextUserWith(['create']);
    $attribute = Attribute::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/product-categories', [
        'name' => 'Dual',
        'attributes' => [
            ['attribute_id' => $attribute->id, 'context' => 'product', 'is_required' => true, 'sort_order' => 0],
            ['attribute_id' => $attribute->id, 'context' => 'quote', 'is_required' => false, 'sort_order' => 1],
        ],
    ])->assertCreated();

    expect(DB::table('attribute_category')->where('attribute_id', $attribute->id)->count())->toBe(2);
    $this->assertDatabaseHas('attribute_category', ['attribute_id' => $attribute->id, 'context' => 'product', 'is_required' => 1]);
    $this->assertDatabaseHas('attribute_category', ['attribute_id' => $attribute->id, 'context' => 'quote', 'is_required' => 0]);
});

it('sync: an assignment in the retired opportunity context is rejected', function (): void {
    $actor = categoryContextUserWith(['create']);
    $attribute = Attribute::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/product-categories', [
        'name' => 'Retired',
        'attributes' => [
            ['attribute_id' => $attribute->id, 'context' => 'opportunity', 'is_required' => false, 'sort_order' => 0],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('attributes.0.context');

    expect(DB::table('attribute_category')->count())->toBe(0);
});

it('sync: update replaces the whole pivot set (both contexts), idempotently', function (): void {
    $actor = categoryContextUserWith(['create', 'update']);
    $category = ProductCategory::factory()->create();
    $attribute = Attribute::factory()->create();
    Sanctum::actingAs($actor);

    $payload = [
        'attributes' => [
            ['attribute_id' => $attribute->id, 'context' => 'product', 'is_required' => true, 'sort_order' => 0],
        ],
    ];

    $this->patchJson("/api/product-categories/{$category->id}", $payload)->assertOk();
    $this->patchJson("/api/product-categories/{$category->id}", $payload)->assertOk();

    expect(DB::table('attribute_category')->where('category_id', $category->id)->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// effective-attributes endpoint filters by ?context=
// ---------------------------------------------------------------------------

it('effective-attributes: filters by context', function (): void {
    $actor = categoryContextUserWith(['view']);
    $category = ProductCategory::factory()->create();
    $productAttribute = Attribute::factory()->create(['code' => 'prod_field']);
    $quoteAttribute = Attribute::factory()->create(['code' => 'quote_field']);
    $category->attributes()->attach($productAttribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'product']);
    $category->attributes()->attach($quoteAttribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'quote']);
    Sanctum::actingAs($actor);

    $product = $this->getJson("/api/product-categories/{$category->id}/effective-attributes?context=product")->assertOk();
    expect(collect($product->json('data'))->pluck('code')->all())->toBe(['prod_field']);

    $quote = $this->getJson("/api/product-categories/{$category->id}/effective-attributes?context=quote")->assertOk();
    expect(collect($quote->json('data'))->pluck('code')->all())->toBe(['quote_field']);
});

it('effective-attributes: 422 when context is absent — there is no default slice', function (): void {
    $actor = categoryContextUserWith(['view']);
    $category = ProductCategory::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/product-categories/{$category->id}/effective-attributes")
        ->assertStatus(422)->assertJsonValidationErrors('context');
});

it('effective-attributes: 422 on an invalid context value', function (): void {
    $actor = categoryContextUserWith(['view']);
    $category = ProductCategory::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/product-categories/{$category->id}/effective-attributes?context=bogus")
        ->assertStatus(422)->assertJsonValidationErrors('context');

    $this->getJson("/api/product-categories/{$category->id}/effective-attributes?context=opportunity")
        ->assertStatus(422)->assertJsonValidationErrors('context');
});

// ---------------------------------------------------------------------------
// Inheritance resolves independently PER context (barrier + most-specific)
// ---------------------------------------------------------------------------

it('inheritance: barrier and most-specific-wins are honored independently per context', function (): void {
    $actor = categoryContextUserWith(['view']);
    $root = ProductCategory::factory()->create();
    $child = ProductCategory::factory()->childOf($root)->create();

    $shared = Attribute::factory()->create(['code' => 'shared']);
    $rootOnlyProduct = Attribute::factory()->create(['code' => 'root_product_only']);
    $rootOnlyQuote = Attribute::factory()->create(['code' => 'root_quote_only']);

    // Root: own assignment in both contexts (the child overrides the Product
    // one below — most-specific wins, checked independently per context).
    $root->attributes()->attach($shared->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'product']);
    $root->attributes()->attach($rootOnlyProduct->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'product']);
    $root->attributes()->attach($rootOnlyQuote->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'quote']);

    // Child overrides `shared` as REQUIRED in the Product context only.
    $child->attributes()->attach($shared->id, ['is_required' => true, 'sort_order' => 0, 'context' => 'product']);

    Sanctum::actingAs($actor);

    $product = $this->getJson("/api/product-categories/{$child->id}/effective-attributes?context=product")->assertOk();
    $productRows = collect($product->json('data'))->keyBy('code');
    expect($productRows->keys()->all())->toEqualCanonicalizing(['shared', 'root_product_only']);
    expect($productRows->get('shared')['is_required'])->toBeTrue();
    expect($productRows->get('shared')['inherited'])->toBeFalse();

    $quote = $this->getJson("/api/product-categories/{$child->id}/effective-attributes?context=quote")->assertOk();
    expect(collect($quote->json('data'))->pluck('code')->all())->toBe(['root_quote_only']);
});

it('inheritance: opting out of BOTH context flags empties both inherited lists', function (): void {
    $actor = categoryContextUserWith(['view']);
    $root = ProductCategory::factory()->create();
    $child = ProductCategory::factory()->childOf($root)->notInheriting()->create();

    $productAttr = Attribute::factory()->create(['code' => 'p']);
    $quoteAttr = Attribute::factory()->create(['code' => 'q']);
    $root->attributes()->attach($productAttr->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'product']);
    $root->attributes()->attach($quoteAttr->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'quote']);

    Sanctum::actingAs($actor);

    $product = $this->getJson("/api/product-categories/{$child->id}/effective-attributes?context=product")->assertOk();
    expect($product->json('data'))->toBe([]);

    $quote = $this->getJson("/api/product-categories/{$child->id}/effective-attributes?context=quote")->assertOk();
    expect($quote->json('data'))->toBe([]);
});

it('inheritance: the barrier is per context — opting Product out leaves Offerta inheriting', function (): void {
    $actor = categoryContextUserWith(['view']);
    $root = ProductCategory::factory()->create();
    $child = ProductCategory::factory()->childOf($root)->notInheritingIn(AttributeContext::Product)->create();

    $productAttr = Attribute::factory()->create(['code' => 'p']);
    $quoteAttr = Attribute::factory()->create(['code' => 'q']);
    $root->attributes()->attach($productAttr->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'product']);
    $root->attributes()->attach($quoteAttr->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'quote']);

    Sanctum::actingAs($actor);

    $product = $this->getJson("/api/product-categories/{$child->id}/effective-attributes?context=product")->assertOk();
    expect($product->json('data'))->toBe([]);

    $quote = $this->getJson("/api/product-categories/{$child->id}/effective-attributes?context=quote")->assertOk();
    expect(collect($quote->json('data'))->pluck('code')->all())->toBe(['q']);
});

it('inheritance: a mid-chain barrier truncates only its own context', function (): void {
    $actor = categoryContextUserWith(['view']);
    // grandparent -> parent (Offerta barrier) -> child (inherits both)
    $grandparent = ProductCategory::factory()->create();
    $parent = ProductCategory::factory()->childOf($grandparent)->notInheritingIn(AttributeContext::Quote)->create();
    $child = ProductCategory::factory()->childOf($parent)->create();

    $grandparentProduct = Attribute::factory()->create(['code' => 'gp_p']);
    $grandparentQuote = Attribute::factory()->create(['code' => 'gp_q']);
    $parentQuote = Attribute::factory()->create(['code' => 'p_q']);
    $grandparent->attributes()->attach($grandparentProduct->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'product']);
    $grandparent->attributes()->attach($grandparentQuote->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'quote']);
    $parent->attributes()->attach($parentQuote->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'quote']);

    Sanctum::actingAs($actor);

    // Product climbs past the parent up to the grandparent...
    $product = $this->getJson("/api/product-categories/{$child->id}/effective-attributes?context=product")->assertOk();
    expect(collect($product->json('data'))->pluck('code')->all())->toBe(['gp_p']);

    // ...while Offerta stops at the parent, which contributes its own.
    $quote = $this->getJson("/api/product-categories/{$child->id}/effective-attributes?context=quote")->assertOk();
    expect(collect($quote->json('data'))->pluck('code')->all())->toBe(['p_q']);
});

// ---------------------------------------------------------------------------
// ProductCategoryResource / config page — flat list, both contexts tagged
// ---------------------------------------------------------------------------

it('show: own attributes and inherited_attributes are a flat list, each row tagged with its context', function (): void {
    $actor = categoryContextUserWith(['view']);
    $root = ProductCategory::factory()->create();
    $child = ProductCategory::factory()->childOf($root)->create();

    $rootProductAttr = Attribute::factory()->create(['code' => 'root_p']);
    $childQuoteAttr = Attribute::factory()->create(['code' => 'child_q']);
    $root->attributes()->attach($rootProductAttr->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'product']);
    $child->attributes()->attach($childQuoteAttr->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'quote']);

    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/product-categories/{$child->id}")->assertOk();

    $own = collect($response->json('data.attributes'));
    expect($own->firstWhere('code', 'child_q')['context'])->toBe('quote');

    $inherited = collect($response->json('data.inherited_attributes'));
    expect($inherited->firstWhere('code', 'root_p')['context'])->toBe('product');
});
