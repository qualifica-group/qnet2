<?php

use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\OpportunityStatus;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * `product_lines` (spec 0040, amendment rev.3): SUBSTITUTES the former single
 * business_function_id/product_category_id scalars with a to-many collection
 * (AC-097..AC-101). Split out of OpportunityCrudTest (file-size limit,
 * engineering.md §6).
 */
uses(RefreshDatabase::class);

if (! function_exists('productLinesOpportunityUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function productLinesOpportunityUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("opportunities.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("opportunities.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('productLinesMandatoryOpportunityFks')) {
    /**
     * @return array{registry_id: int, opportunity_status_id: int, supervisor_id: int}
     */
    function productLinesMandatoryOpportunityFks(): array
    {
        return [
            'registry_id' => Registry::factory()->create()->id,
            'opportunity_status_id' => OpportunityStatus::factory()->create()->id,
            'supervisor_id' => User::factory()->create()->id,
        ];
    }
}

it('create: product_lines omitted -> 422 (user directive 2026-07-17: at least one row required)', function () {
    $actor = productLinesOpportunityUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunities', array_merge(productLinesMandatoryOpportunityFks(), [
        'name' => 'No product lines',
    ]))->assertStatus(422)->assertJsonValidationErrors('product_lines');

    expect(Opportunity::count())->toBe(0);
});

it('create: product_lines: [] -> 422 (user directive 2026-07-17: at least one row required)', function () {
    $actor = productLinesOpportunityUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunities', array_merge(productLinesMandatoryOpportunityFks(), [
        'name' => 'Empty product lines',
        'product_lines' => [],
    ]))->assertStatus(422)->assertJsonValidationErrors('product_lines');

    expect(Opportunity::count())->toBe(0);
});

it('create: multiple product_lines rows persist, same business function with different categories allowed (AC-099)', function () {
    $actor = productLinesOpportunityUserWith(['create']);
    $businessFunction = BusinessFunction::factory()->create();
    $categoryOne = ProductCategory::factory()->create(['business_function_id' => $businessFunction->id]);
    // Spec 0077 INV-1: rows must resolve to the SAME product-category root —
    // categoryTwo is a CHILD of categoryOne (not a second, independent root)
    // so "different categories, same business function" stays a valid case.
    $categoryTwo = ProductCategory::factory()->childOf($categoryOne)->create(['business_function_id' => $businessFunction->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/opportunities', array_merge(productLinesMandatoryOpportunityFks(), [
        'name' => 'Multi-line deal',
        'product_lines' => [
            ['business_function_id' => $businessFunction->id, 'product_category_id' => $categoryOne->id],
            ['business_function_id' => $businessFunction->id, 'product_category_id' => $categoryTwo->id],
        ],
        // Mandatory since 2026-07-23; taken from a submitted category so it
        // adds no product line of its own.
        'products_of_interest' => [Product::factory()->create(['category_id' => $categoryOne->id])->id],
    ]))->assertCreated();

    $opportunityId = $response->json('data.id');
    $this->assertDatabaseCount('opportunity_product_lines', 2);
    $this->assertDatabaseHas('opportunity_product_lines', [
        'opportunity_id' => $opportunityId, 'business_function_id' => $businessFunction->id, 'product_category_id' => $categoryOne->id,
    ]);
    $this->assertDatabaseHas('opportunity_product_lines', [
        'opportunity_id' => $opportunityId, 'business_function_id' => $businessFunction->id, 'product_category_id' => $categoryTwo->id,
    ]);
});

it('create: a duplicate {business_function_id, product_category_id} pair -> 422 (AC-100)', function () {
    $actor = productLinesOpportunityUserWith(['create']);
    $businessFunction = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $businessFunction->id]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunities', array_merge(productLinesMandatoryOpportunityFks(), [
        'name' => 'Duplicate pair',
        'product_lines' => [
            ['business_function_id' => $businessFunction->id, 'product_category_id' => $category->id],
            ['business_function_id' => $businessFunction->id, 'product_category_id' => $category->id],
        ],
    ]))->assertStatus(422)->assertJsonValidationErrors('product_lines.1.product_category_id');

    expect(Opportunity::count())->toBe(0);
});

it('create: a category whose EFFECTIVE business function differs from the row -> 422 (AC-100)', function () {
    $actor = productLinesOpportunityUserWith(['create']);
    $ownBusinessFunction = BusinessFunction::factory()->create();
    $otherBusinessFunction = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $ownBusinessFunction->id]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunities', array_merge(productLinesMandatoryOpportunityFks(), [
        'name' => 'Mismatched pair',
        'product_lines' => [
            ['business_function_id' => $otherBusinessFunction->id, 'product_category_id' => $category->id],
        ],
    ]))->assertStatus(422)->assertJsonValidationErrors('product_lines.0.business_function_id');

    expect(Opportunity::count())->toBe(0);
});

it('create: a category with NO effective business function -> 422 on any submitted pairing (AC-100)', function () {
    $actor = productLinesOpportunityUserWith(['create']);
    $businessFunction = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => null]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunities', array_merge(productLinesMandatoryOpportunityFks(), [
        'name' => 'No function category',
        'product_lines' => [
            ['business_function_id' => $businessFunction->id, 'product_category_id' => $category->id],
        ],
    ]))->assertStatus(422)->assertJsonValidationErrors('product_lines.0.business_function_id');
});

it('update: product_lines is a full-replace sync (AC-099)', function () {
    $actor = productLinesOpportunityUserWith(['create', 'update']);
    $businessFunction = BusinessFunction::factory()->create();
    $categoryOne = ProductCategory::factory()->create(['business_function_id' => $businessFunction->id]);
    $categoryTwo = ProductCategory::factory()->create(['business_function_id' => $businessFunction->id]);
    Sanctum::actingAs($actor);

    $created = $this->postJson('/api/opportunities', array_merge(productLinesMandatoryOpportunityFks(), [
        'name' => 'Replaceable lines',
        'product_lines' => [
            ['business_function_id' => $businessFunction->id, 'product_category_id' => $categoryOne->id],
        ],
        'products_of_interest' => [Product::factory()->create(['category_id' => $categoryOne->id])->id],
    ]))->assertCreated();
    $opportunityId = $created->json('data.id');

    $this->patchJson("/api/opportunities/{$opportunityId}", [
        'product_lines' => [
            ['business_function_id' => $businessFunction->id, 'product_category_id' => $categoryTwo->id],
        ],
    ])->assertOk();

    $this->assertDatabaseCount('opportunity_product_lines', 1);
    $this->assertDatabaseHas('opportunity_product_lines', [
        'opportunity_id' => $opportunityId, 'product_category_id' => $categoryTwo->id,
    ]);
    $this->assertDatabaseMissing('opportunity_product_lines', ['product_category_id' => $categoryOne->id]);
});

it('update: product_lines: [] -> 422, the existing rows are kept (user directive 2026-07-17: an opportunity always keeps >=1 row)', function () {
    $actor = productLinesOpportunityUserWith(['create', 'update']);
    $businessFunction = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $businessFunction->id]);
    Sanctum::actingAs($actor);

    $created = $this->postJson('/api/opportunities', array_merge(productLinesMandatoryOpportunityFks(), [
        'name' => 'Non-clearable lines',
        'product_lines' => [
            ['business_function_id' => $businessFunction->id, 'product_category_id' => $category->id],
        ],
        'products_of_interest' => [Product::factory()->create(['category_id' => $category->id])->id],
    ]))->assertCreated();
    $opportunityId = $created->json('data.id');

    $this->patchJson("/api/opportunities/{$opportunityId}", ['product_lines' => []])
        ->assertStatus(422)->assertJsonValidationErrors('product_lines');

    $this->assertDatabaseCount('opportunity_product_lines', 1);
    $this->assertDatabaseHas('opportunity_product_lines', [
        'opportunity_id' => $opportunityId, 'product_category_id' => $category->id,
    ]);
});

it('update: omitting product_lines leaves the existing rows untouched (partial PATCH)', function () {
    $actor = productLinesOpportunityUserWith(['create', 'update']);
    $businessFunction = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $businessFunction->id]);
    Sanctum::actingAs($actor);

    $created = $this->postJson('/api/opportunities', array_merge(productLinesMandatoryOpportunityFks(), [
        'name' => 'Untouched lines',
        'product_lines' => [
            ['business_function_id' => $businessFunction->id, 'product_category_id' => $category->id],
        ],
        'products_of_interest' => [Product::factory()->create(['category_id' => $category->id])->id],
    ]))->assertCreated();
    $opportunityId = $created->json('data.id');

    $this->patchJson("/api/opportunities/{$opportunityId}", ['estimated_value' => 555])->assertOk();

    $this->assertDatabaseCount('opportunity_product_lines', 1);
    $this->assertDatabaseHas('opportunity_product_lines', [
        'opportunity_id' => $opportunityId, 'product_category_id' => $category->id,
    ]);
});

it('delete: cascades the opportunity\'s own product line rows', function () {
    $actor = productLinesOpportunityUserWith(['create', 'delete']);
    $businessFunction = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $businessFunction->id]);
    Sanctum::actingAs($actor);

    $created = $this->postJson('/api/opportunities', array_merge(productLinesMandatoryOpportunityFks(), [
        'name' => 'Deleted with lines',
        'product_lines' => [
            ['business_function_id' => $businessFunction->id, 'product_category_id' => $category->id],
        ],
        'products_of_interest' => [Product::factory()->create(['category_id' => $category->id])->id],
    ]))->assertCreated();
    $opportunityId = $created->json('data.id');

    $this->deleteJson("/api/opportunities/{$opportunityId}")->assertNoContent();

    $this->assertDatabaseMissing('opportunity_product_lines', ['opportunity_id' => $opportunityId]);
});
