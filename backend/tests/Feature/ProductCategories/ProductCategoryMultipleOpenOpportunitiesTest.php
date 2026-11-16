<?php

use App\Enums\CategoryManagementMode;
use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * User request 2026-11-11: the root-owned `multiple_open_opportunities`
 * setting lets an anagrafica keep several open opportunities of a branch
 * managed in `multiple` mode, whose offers keep several product rows.
 */
uses(RefreshDatabase::class);

if (! function_exists('multipleOpenActor')) {
    function multipleOpenActor(): User
    {
        foreach (['viewAny', 'view', 'create', 'update'] as $ability) {
            Permission::findOrCreate("opportunities.{$ability}");
        }

        $user = User::factory()->create();
        $user->givePermissionTo(['opportunities.viewAny', 'opportunities.view', 'opportunities.create']);

        return $user;
    }
}

if (! function_exists('multipleOpenCategory')) {
    function multipleOpenCategory(bool $multipleOpen, array $attributes = []): ProductCategory
    {
        return ProductCategory::factory()->create([
            'business_function_id' => BusinessFunction::factory()->create()->id,
            'management_mode' => CategoryManagementMode::Multiple,
            'multiple_open_opportunities' => $multipleOpen,
            ...$attributes,
        ]);
    }
}

if (! function_exists('multipleOpenOpportunityOn')) {
    function multipleOpenOpportunityOn(Registry $registry, ProductCategory $category): Opportunity
    {
        $opportunity = Opportunity::factory()->create(['registry_id' => $registry->id]);

        OpportunityProductLine::create([
            'opportunity_id' => $opportunity->id,
            'business_function_id' => $category->business_function_id,
            'product_category_id' => $category->id,
        ]);

        return $opportunity;
    }
}

if (! function_exists('multipleOpenPayload')) {
    /**
     * @return array<string, mixed>
     */
    function multipleOpenPayload(Registry $registry, ProductCategory $category): array
    {
        return [
            'registry_id' => $registry->id,
            'product_lines' => [
                ['business_function_id' => $category->business_function_id, 'product_category_id' => $category->id],
            ],
            'products_of_interest' => [Product::factory()->create(['category_id' => $category->id])->id],
        ];
    }
}

// ---------------------------------------------------------------------------
// A) The setting on the category tree
// ---------------------------------------------------------------------------

it('a fresh root keeps one open opportunity per anagrafica; a root may allow several', function () {
    Sanctum::actingAs(productCategoryUserWith(['create']));

    $this->postJson('/api/product-categories', ['name' => 'ISO'])
        ->assertCreated()
        ->assertJsonPath('data.multiple_open_opportunities', false);

    $this->postJson('/api/product-categories', ['name' => 'Consulenza', 'multiple_open_opportunities' => true])
        ->assertCreated()
        ->assertJsonPath('data.multiple_open_opportunities', true);
});

it('a child inherits the rule of its root and shows where it comes from', function () {
    $root = ProductCategory::factory()->create(['multiple_open_opportunities' => true]);
    Sanctum::actingAs(productCategoryUserWith(['create', 'view']));

    $childId = $this->postJson('/api/product-categories', ['name' => 'Figlia', 'parent_id' => $root->id])
        ->assertCreated()
        ->assertJsonPath('data.multiple_open_opportunities', true)
        ->json('data.id');

    $this->getJson("/api/product-categories/{$childId}")
        ->assertOk()
        ->assertJsonPath('data.multiple_open_opportunities_source_category.id', $root->id);
});

it('changing the root rule realigns the whole branch, and the tree carries it', function () {
    $root = ProductCategory::factory()->create(['multiple_open_opportunities' => false]);
    $child = ProductCategory::factory()->childOf($root)->create(['multiple_open_opportunities' => false]);
    $grandchild = ProductCategory::factory()->childOf($child)->create(['multiple_open_opportunities' => false]);
    Sanctum::actingAs(productCategoryUserWith(['update', 'viewAny']));

    $this->patchJson("/api/product-categories/{$root->id}", ['multiple_open_opportunities' => true])->assertOk();

    expect($child->fresh()->multiple_open_opportunities)->toBeTrue()
        ->and($grandchild->fresh()->multiple_open_opportunities)->toBeTrue();

    $this->getJson('/api/product-categories/tree')
        ->assertOk()
        ->assertJsonPath('data.0.multiple_open_opportunities', true)
        ->assertJsonPath('data.0.children.0.multiple_open_opportunities', true);
});

it('a child may not define a rule different from its root', function () {
    $root = ProductCategory::factory()->create(['multiple_open_opportunities' => true]);
    $child = ProductCategory::factory()->childOf($root)->create(['multiple_open_opportunities' => true]);
    Sanctum::actingAs(productCategoryUserWith(['create', 'update']));

    $this->postJson('/api/product-categories', ['name' => 'X', 'parent_id' => $root->id, 'multiple_open_opportunities' => false])
        ->assertStatus(422);
    $this->patchJson("/api/product-categories/{$child->id}", ['multiple_open_opportunities' => false])->assertStatus(422);
    $this->patchJson("/api/product-categories/{$child->id}", ['multiple_open_opportunities' => true])->assertOk();
});

it('rejects a value that is not a boolean', function () {
    Sanctum::actingAs(productCategoryUserWith(['create']));

    $this->postJson('/api/product-categories', ['name' => 'Bad', 'multiple_open_opportunities' => 'sometimes'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('multiple_open_opportunities');
});

// ---------------------------------------------------------------------------
// B) Its effect on the one-open-opportunity rule
// ---------------------------------------------------------------------------

it('creates another opportunity beside an open one when both branches allow it', function () {
    Sanctum::actingAs(multipleOpenActor());
    $registry = Registry::factory()->create();
    $category = multipleOpenCategory(true);
    multipleOpenOpportunityOn($registry, $category);

    $this->postJson('/api/opportunities', multipleOpenPayload($registry, $category))->assertCreated();

    expect(Opportunity::query()->where('registry_id', $registry->id)->count())->toBe(2);
});

it('keeps blocking when the open opportunity sits on a branch that does not allow it', function () {
    Sanctum::actingAs(multipleOpenActor());
    $registry = Registry::factory()->create();
    multipleOpenOpportunityOn($registry, multipleOpenCategory(false));

    $this->postJson('/api/opportunities', multipleOpenPayload($registry, multipleOpenCategory(true)))->assertCreated();
    $this->postJson('/api/opportunities', multipleOpenPayload($registry, multipleOpenCategory(false)))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['registry_id']);
});

it('keeps blocking when one category of the open opportunity does not allow it', function () {
    Sanctum::actingAs(multipleOpenActor());
    $registry = Registry::factory()->create();
    $opportunity = multipleOpenOpportunityOn($registry, multipleOpenCategory(true));
    $strict = multipleOpenCategory(false);
    OpportunityProductLine::create([
        'opportunity_id' => $opportunity->id,
        'business_function_id' => $strict->business_function_id,
        'product_category_id' => $strict->id,
    ]);

    $this->postJson('/api/opportunities', multipleOpenPayload($registry, multipleOpenCategory(false)))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['registry_id']);
});

it('ignores a legacy category beside a current one that allows it', function () {
    Sanctum::actingAs(multipleOpenActor());
    $registry = Registry::factory()->create();
    $opportunity = multipleOpenOpportunityOn($registry, multipleOpenCategory(true));
    $legacy = multipleOpenCategory(false);
    $legacy->forceFill(['is_legacy' => true])->save();
    OpportunityProductLine::create([
        'opportunity_id' => $opportunity->id,
        'business_function_id' => $legacy->business_function_id,
        'product_category_id' => $legacy->id,
    ]);

    $this->postJson('/api/opportunities', multipleOpenPayload($registry, multipleOpenCategory(false)))->assertCreated();
});

it('lets the offer of an opportunity on such a branch carry several product rows', function () {
    $category = multipleOpenCategory(true);
    $opportunity = multipleOpenOpportunityOn(Registry::factory()->create(), $category);
    $products = Product::factory()->count(2)->create(['category_id' => $category->id]);

    foreach (['viewAny', 'view', 'create', 'update'] as $ability) {
        Permission::findOrCreate("quotes.{$ability}");
    }
    $user = User::factory()->create();
    $user->givePermissionTo(['quotes.viewAny', 'quotes.view', 'quotes.create']);
    Sanctum::actingAs($user);

    $this->postJson('/api/quotes', [
        'opportunity_id' => $opportunity->id,
        'offer_lines' => $products->map(static fn (Product $product): array => [
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 100,
        ])->all(),
    ])->assertCreated();

    expect(Quote::query()->where('opportunity_id', $opportunity->id)->first()->offerLines()->count())->toBe(2);
});

// ---------------------------------------------------------------------------
// C) The deploy backfill follows the rule in force until now
// ---------------------------------------------------------------------------

it('the migration turns the rule on for the single-mode categories only', function () {
    $single = ProductCategory::factory()->create(['management_mode' => CategoryManagementMode::Single]);
    $multiple = ProductCategory::factory()->create(['management_mode' => CategoryManagementMode::Multiple]);
    $migration = require database_path('migrations/2026_12_07_100000_add_multiple_open_opportunities_to_product_categories_table.php');

    $migration->down();
    $migration->up();

    expect($single->fresh()->multiple_open_opportunities)->toBeTrue()
        ->and($multiple->fresh()->multiple_open_opportunities)->toBeFalse();
});
