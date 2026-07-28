<?php

declare(strict_types=1);

use App\Enums\AttributeContext;
use App\Models\Attribute;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\ProductCategory;
use App\Models\User;
use App\RequestManagement\ApplicableAttributesResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0061: the Product/Opportunity context discriminator on the
// attribute_category pivot. The HARD INVARIANT (spec 0061 <hard-invariant>):
// the Opportunity path (ApplicableAttributesResolver, request-management)
// must stay byte-for-byte identical — every pre-existing assignment
// backfills to context='opportunity', and a NEW Product-context assignment
// must never leak into the Opportunity-side resolution.

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
// REGRESSION — the hard invariant (spec 0061)
// ---------------------------------------------------------------------------

it('regression: a legacy attach() with no context backfills to opportunity', function (): void {
    $category = ProductCategory::factory()->create();
    $attribute = Attribute::factory()->create();

    $category->attributes()->attach($attribute->id, ['is_required' => false, 'sort_order' => 0]);

    $this->assertDatabaseHas('attribute_category', [
        'attribute_id' => $attribute->id,
        'category_id' => $category->id,
        'context' => 'opportunity',
    ]);
});

it('regression: ApplicableAttributesResolver (opportunity path) never sees a Product-context attribute', function (): void {
    $category = ProductCategory::factory()->create();
    $opportunityAttribute = Attribute::factory()->create(['code' => 'opportunity_only']);
    $productAttribute = Attribute::factory()->create(['code' => 'product_only']);

    $category->attributes()->attach($opportunityAttribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'opportunity']);
    $category->attributes()->attach($productAttribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'product']);

    $opportunity = Opportunity::factory()->create();
    OpportunityProductLine::factory()->for($opportunity)->create(['product_category_id' => $category->id]);

    $resolved = app(ApplicableAttributesResolver::class)->resolve($opportunity);

    expect($resolved->pluck('code')->all())->toBe(['opportunity_only']);
});

// ---------------------------------------------------------------------------
// Category sync stores both contexts (API)
// ---------------------------------------------------------------------------

it('sync: the SAME attribute can be assigned to both Product and Opportunity in one request', function (): void {
    $actor = categoryContextUserWith(['create']);
    $attribute = Attribute::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/product-categories', [
        'name' => 'Dual',
        'attributes' => [
            ['attribute_id' => $attribute->id, 'context' => 'product', 'is_required' => true, 'sort_order' => 0],
            ['attribute_id' => $attribute->id, 'context' => 'opportunity', 'is_required' => false, 'sort_order' => 1],
        ],
    ])->assertCreated();

    expect(DB::table('attribute_category')->where('attribute_id', $attribute->id)->count())->toBe(2);
    $this->assertDatabaseHas('attribute_category', ['attribute_id' => $attribute->id, 'context' => 'product', 'is_required' => 1]);
    $this->assertDatabaseHas('attribute_category', ['attribute_id' => $attribute->id, 'context' => 'opportunity', 'is_required' => 0]);
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

it('effective-attributes: filters by context, defaulting to opportunity', function (): void {
    $actor = categoryContextUserWith(['view']);
    $category = ProductCategory::factory()->create();
    $productAttribute = Attribute::factory()->create(['code' => 'prod_field']);
    $opportunityAttribute = Attribute::factory()->create(['code' => 'opp_field']);
    $category->attributes()->attach($productAttribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'product']);
    $category->attributes()->attach($opportunityAttribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'opportunity']);
    Sanctum::actingAs($actor);

    $default = $this->getJson("/api/product-categories/{$category->id}/effective-attributes")->assertOk();
    expect(collect($default->json('data'))->pluck('code')->all())->toBe(['opp_field']);

    $product = $this->getJson("/api/product-categories/{$category->id}/effective-attributes?context=product")->assertOk();
    expect(collect($product->json('data'))->pluck('code')->all())->toBe(['prod_field']);

    $opportunity = $this->getJson("/api/product-categories/{$category->id}/effective-attributes?context=opportunity")->assertOk();
    expect(collect($opportunity->json('data'))->pluck('code')->all())->toBe(['opp_field']);
});

it('effective-attributes: 422 on an invalid context value', function (): void {
    $actor = categoryContextUserWith(['view']);
    $category = ProductCategory::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/product-categories/{$category->id}/effective-attributes?context=bogus")
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
    $rootOnlyOpportunity = Attribute::factory()->create(['code' => 'root_opportunity_only']);

    // Root: own assignment in both contexts (the child overrides the Product
    // one below — most-specific wins, same rule as the pre-existing single
    // context, now checked independently per context).
    $root->attributes()->attach($shared->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'product']);
    $root->attributes()->attach($rootOnlyProduct->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'product']);
    $root->attributes()->attach($rootOnlyOpportunity->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'opportunity']);

    // Child overrides `shared` as REQUIRED in the Product context only.
    $child->attributes()->attach($shared->id, ['is_required' => true, 'sort_order' => 0, 'context' => 'product']);

    Sanctum::actingAs($actor);

    $product = $this->getJson("/api/product-categories/{$child->id}/effective-attributes?context=product")->assertOk();
    $productRows = collect($product->json('data'))->keyBy('code');
    expect($productRows->keys()->all())->toEqualCanonicalizing(['shared', 'root_product_only']);
    expect($productRows->get('shared')['is_required'])->toBeTrue();
    expect($productRows->get('shared')['inherited'])->toBeFalse();

    $opportunity = $this->getJson("/api/product-categories/{$child->id}/effective-attributes?context=opportunity")->assertOk();
    expect(collect($opportunity->json('data'))->pluck('code')->all())->toBe(['root_opportunity_only']);
});

it('inheritance: opting out of BOTH context flags empties both inherited lists', function (): void {
    $actor = categoryContextUserWith(['view']);
    $root = ProductCategory::factory()->create();
    $child = ProductCategory::factory()->childOf($root)->notInheriting()->create();

    $productAttr = Attribute::factory()->create(['code' => 'p']);
    $opportunityAttr = Attribute::factory()->create(['code' => 'o']);
    $root->attributes()->attach($productAttr->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'product']);
    $root->attributes()->attach($opportunityAttr->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'opportunity']);

    Sanctum::actingAs($actor);

    $product = $this->getJson("/api/product-categories/{$child->id}/effective-attributes?context=product")->assertOk();
    expect($product->json('data'))->toBe([]);

    $opportunity = $this->getJson("/api/product-categories/{$child->id}/effective-attributes?context=opportunity")->assertOk();
    expect($opportunity->json('data'))->toBe([]);
});

it('inheritance: the barrier is per context — opting Product out leaves Opportunity inheriting', function (): void {
    $actor = categoryContextUserWith(['view']);
    $root = ProductCategory::factory()->create();
    $child = ProductCategory::factory()->childOf($root)->notInheritingIn(AttributeContext::Product)->create();

    $productAttr = Attribute::factory()->create(['code' => 'p']);
    $opportunityAttr = Attribute::factory()->create(['code' => 'o']);
    $root->attributes()->attach($productAttr->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'product']);
    $root->attributes()->attach($opportunityAttr->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'opportunity']);

    Sanctum::actingAs($actor);

    $product = $this->getJson("/api/product-categories/{$child->id}/effective-attributes?context=product")->assertOk();
    expect($product->json('data'))->toBe([]);

    $opportunity = $this->getJson("/api/product-categories/{$child->id}/effective-attributes?context=opportunity")->assertOk();
    expect(collect($opportunity->json('data'))->pluck('code')->all())->toBe(['o']);
});

it('inheritance: a mid-chain barrier truncates only its own context', function (): void {
    $actor = categoryContextUserWith(['view']);
    // grandparent -> parent (Opportunity barrier) -> child (inherits both)
    $grandparent = ProductCategory::factory()->create();
    $parent = ProductCategory::factory()->childOf($grandparent)->notInheritingIn(AttributeContext::Opportunity)->create();
    $child = ProductCategory::factory()->childOf($parent)->create();

    $grandparentProduct = Attribute::factory()->create(['code' => 'gp_p']);
    $grandparentOpportunity = Attribute::factory()->create(['code' => 'gp_o']);
    $parentOpportunity = Attribute::factory()->create(['code' => 'p_o']);
    $grandparent->attributes()->attach($grandparentProduct->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'product']);
    $grandparent->attributes()->attach($grandparentOpportunity->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'opportunity']);
    $parent->attributes()->attach($parentOpportunity->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'opportunity']);

    Sanctum::actingAs($actor);

    // Product climbs past the parent up to the grandparent...
    $product = $this->getJson("/api/product-categories/{$child->id}/effective-attributes?context=product")->assertOk();
    expect(collect($product->json('data'))->pluck('code')->all())->toBe(['gp_p']);

    // ...while Opportunity stops at the parent, which contributes its own.
    $opportunity = $this->getJson("/api/product-categories/{$child->id}/effective-attributes?context=opportunity")->assertOk();
    expect(collect($opportunity->json('data'))->pluck('code')->all())->toBe(['p_o']);
});

// ---------------------------------------------------------------------------
// ProductCategoryResource / config page — flat list, both contexts tagged
// ---------------------------------------------------------------------------

it('show: own attributes and inherited_attributes are a flat list, each row tagged with its context', function (): void {
    $actor = categoryContextUserWith(['view']);
    $root = ProductCategory::factory()->create();
    $child = ProductCategory::factory()->childOf($root)->create();

    $rootProductAttr = Attribute::factory()->create(['code' => 'root_p']);
    $childOpportunityAttr = Attribute::factory()->create(['code' => 'child_o']);
    $root->attributes()->attach($rootProductAttr->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'product']);
    $child->attributes()->attach($childOpportunityAttr->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'opportunity']);

    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/product-categories/{$child->id}")->assertOk();

    $own = collect($response->json('data.attributes'));
    expect($own->firstWhere('code', 'child_o')['context'])->toBe('opportunity');

    $inherited = collect($response->json('data.inherited_attributes'));
    expect($inherited->firstWhere('code', 'root_p')['context'])->toBe('product');
});
