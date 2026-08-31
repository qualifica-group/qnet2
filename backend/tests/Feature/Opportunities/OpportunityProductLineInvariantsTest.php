<?php

use App\Enums\CategoryManagementMode;
use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Registry;
use App\Models\User;
use App\Services\ProductCategories\CategoryManagementModeInheritance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0077 INV-3 on the opportunities form (AC-010, AC-014, AC-015, AC-016,
 * AC-017): the card-level check `ProductLineSetValidator::crossRowErrors()`
 * adds beyond the pre-existing duplicate-pair/business-function-mismatch
 * pair.
 *
 * Rev.2 (user directive 2026-08-31) revoked INV-1/INV-2 — rows are
 * independent — so AC-014 and AC-015, which used to assert a 422 on mixed
 * business functions and mixed roots, now assert the opposite: they are the
 * regression guard that the two rules do not creep back in. The row cap of a
 * `single` root is the only card-level rule left, and it now applies as soon
 * as ONE row resolves to such a root (D-10).
 */
uses(RefreshDatabase::class);

if (! function_exists('invariantsOpportunityActor')) {
    function invariantsOpportunityActor(): User
    {
        foreach (['viewAny', 'view', 'create', 'update'] as $ability) {
            Permission::findOrCreate("opportunities.{$ability}");
        }

        $user = User::factory()->create();
        $user->givePermissionTo(['opportunities.create', 'opportunities.update', 'opportunities.view', 'opportunities.viewAny']);

        return $user;
    }
}

if (! function_exists('invariantsMandatoryOpportunityFks')) {
    /**
     * @return array{registry_id: int, supervisor_id: int}
     */
    function invariantsMandatoryOpportunityFks(): array
    {
        return [
            'registry_id' => Registry::factory()->create()->id,
            'supervisor_id' => User::factory()->create()->id,
        ];
    }
}

it('AC-010: create with two rows under a single-mode root -> 422 on product_lines, no opportunity created', function () {
    $actor = invariantsOpportunityActor();
    $businessFunction = BusinessFunction::factory()->create();
    $root = ProductCategory::factory()->create([
        'business_function_id' => $businessFunction->id,
        'management_mode' => CategoryManagementMode::Single,
    ]);
    $child = ProductCategory::factory()->childOf($root)->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunities', array_merge(invariantsMandatoryOpportunityFks(), [
        'product_lines' => [
            ['business_function_id' => $businessFunction->id, 'product_category_id' => $root->id],
            ['business_function_id' => $businessFunction->id, 'product_category_id' => $child->id],
        ],
        'products_of_interest' => [Product::factory()->create(['category_id' => $root->id])->id],
    ]))->assertStatus(422)->assertJsonValidationErrors('product_lines');

    expect(Opportunity::count())->toBe(0);
});

it('AC-014 rev.2: create with rows carrying two different business functions succeeds, both persisted', function () {
    $actor = invariantsOpportunityActor();
    $functionA = BusinessFunction::factory()->create();
    $functionB = BusinessFunction::factory()->create();
    $categoryA = ProductCategory::factory()->create(['business_function_id' => $functionA->id]);
    $categoryB = ProductCategory::factory()->create(['business_function_id' => $functionB->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/opportunities', array_merge(invariantsMandatoryOpportunityFks(), [
        'product_lines' => [
            ['business_function_id' => $functionA->id, 'product_category_id' => $categoryA->id],
            ['business_function_id' => $functionB->id, 'product_category_id' => $categoryB->id],
        ],
        'products_of_interest' => [Product::factory()->create(['category_id' => $categoryA->id])->id],
    ]))->assertCreated();

    $opportunityId = $response->json('data.id');
    $this->assertDatabaseHas('opportunity_product_lines', [
        'opportunity_id' => $opportunityId,
        'business_function_id' => $functionA->id,
        'product_category_id' => $categoryA->id,
    ]);
    $this->assertDatabaseHas('opportunity_product_lines', [
        'opportunity_id' => $opportunityId,
        'business_function_id' => $functionB->id,
        'product_category_id' => $categoryB->id,
    ]);
});

it('AC-015 rev.2: create with rows resolving to two different multiple-mode roots succeeds', function () {
    $actor = invariantsOpportunityActor();
    $businessFunction = BusinessFunction::factory()->create();
    $rootA = ProductCategory::factory()->create(['business_function_id' => $businessFunction->id]);
    $rootB = ProductCategory::factory()->create(['business_function_id' => $businessFunction->id]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunities', array_merge(invariantsMandatoryOpportunityFks(), [
        'product_lines' => [
            ['business_function_id' => $businessFunction->id, 'product_category_id' => $rootA->id],
            ['business_function_id' => $businessFunction->id, 'product_category_id' => $rootB->id],
        ],
        'products_of_interest' => [Product::factory()->create(['category_id' => $rootA->id])->id],
    ]))->assertCreated();

    $this->assertDatabaseCount('opportunity_product_lines', 2);
});

it('D-10: one row on a single-mode root caps the card even when the other row is multiple-mode', function () {
    $actor = invariantsOpportunityActor();
    $businessFunction = BusinessFunction::factory()->create();
    $singleRoot = ProductCategory::factory()->create([
        'business_function_id' => $businessFunction->id,
        'management_mode' => CategoryManagementMode::Single,
    ]);
    $multipleRoot = ProductCategory::factory()->create([
        'business_function_id' => $businessFunction->id,
        'management_mode' => CategoryManagementMode::Multiple,
    ]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunities', array_merge(invariantsMandatoryOpportunityFks(), [
        'product_lines' => [
            ['business_function_id' => $businessFunction->id, 'product_category_id' => $multipleRoot->id],
            ['business_function_id' => $businessFunction->id, 'product_category_id' => $singleRoot->id],
        ],
        'products_of_interest' => [Product::factory()->create(['category_id' => $multipleRoot->id])->id],
    ]))->assertStatus(422)->assertJsonValidationErrors('product_lines');

    expect(Opportunity::count())->toBe(0);
});

it('AC-012: create with one row and three products of interest under a single-mode root succeeds, all three associated', function () {
    $actor = invariantsOpportunityActor();
    $businessFunction = BusinessFunction::factory()->create();
    $root = ProductCategory::factory()->create(['business_function_id' => $businessFunction->id]);
    app(CategoryManagementModeInheritance::class)->syncSubtree(
        tap($root)->update(['management_mode' => CategoryManagementMode::Single])
    );
    $products = Product::factory()->count(3)->create(['category_id' => $root->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/opportunities', array_merge(invariantsMandatoryOpportunityFks(), [
        'product_lines' => [
            ['business_function_id' => $businessFunction->id, 'product_category_id' => $root->id],
        ],
        'products_of_interest' => $products->pluck('id')->all(),
    ]))->assertCreated();

    $opportunityId = $response->json('data.id');
    expect(Opportunity::findOrFail($opportunityId)->productsOfInterest()->pluck('products.id')->sort()->values()->all())
        ->toEqual($products->pluck('id')->sort()->values()->all());
});

it('AC-013: create with three rows under one multiple-mode root succeeds, all three persisted', function () {
    $actor = invariantsOpportunityActor();
    $businessFunction = BusinessFunction::factory()->create();
    $root = ProductCategory::factory()->create(['business_function_id' => $businessFunction->id]);
    app(CategoryManagementModeInheritance::class)->syncSubtree(
        tap($root)->update(['management_mode' => CategoryManagementMode::Multiple])
    );
    $childOne = ProductCategory::factory()->childOf($root)->create(['business_function_id' => $businessFunction->id]);
    $childTwo = ProductCategory::factory()->childOf($root)->create(['business_function_id' => $businessFunction->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/opportunities', array_merge(invariantsMandatoryOpportunityFks(), [
        'product_lines' => [
            ['business_function_id' => $businessFunction->id, 'product_category_id' => $root->id],
            ['business_function_id' => $businessFunction->id, 'product_category_id' => $childOne->id],
            ['business_function_id' => $businessFunction->id, 'product_category_id' => $childTwo->id],
        ],
        'products_of_interest' => [Product::factory()->create(['category_id' => $root->id])->id],
    ]))->assertCreated();

    $opportunityId = $response->json('data.id');
    $this->assertDatabaseCount('opportunity_product_lines', 3);
    foreach ([$root, $childOne, $childTwo] as $category) {
        $this->assertDatabaseHas('opportunity_product_lines', [
            'opportunity_id' => $opportunityId,
            'business_function_id' => $businessFunction->id,
            'product_category_id' => $category->id,
        ]);
    }
});

if (! function_exists('invariantsHistoricalNonConformingOpportunity')) {
    /**
     * @return array{opportunity: Opportunity, root: ProductCategory}
     */
    function invariantsHistoricalNonConformingOpportunity(): array
    {
        $businessFunction = BusinessFunction::factory()->create();
        // Persisted while the root still allowed `multiple` — a legitimate
        // 2-row card at the time. The root is THEN switched to `single`,
        // producing exactly the "historical record, now non-conformant"
        // shape D-5 grandfathers.
        $root = ProductCategory::factory()->create([
            'business_function_id' => $businessFunction->id,
            'management_mode' => CategoryManagementMode::Multiple,
        ]);
        $child = ProductCategory::factory()->childOf($root)->create();

        $opportunity = Opportunity::factory()->create();
        OpportunityProductLine::factory()->create([
            'opportunity_id' => $opportunity->id,
            'business_function_id' => $businessFunction->id,
            'product_category_id' => $root->id,
        ]);
        OpportunityProductLine::factory()->create([
            'opportunity_id' => $opportunity->id,
            'business_function_id' => $businessFunction->id,
            'product_category_id' => $child->id,
        ]);

        $root->update(['management_mode' => CategoryManagementMode::Single]);

        return ['opportunity' => $opportunity, 'root' => $root];
    }
}

it('AC-016: updating a field other than product_lines on a historical non-conforming record still succeeds (D-5)', function () {
    $actor = invariantsOpportunityActor();
    $fixture = invariantsHistoricalNonConformingOpportunity();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/opportunities/{$fixture['opportunity']->id}", [
        'general_notes' => 'Reviewed after the root switched to single mode.',
    ])->assertOk();

    expect($fixture['opportunity']->productLines()->count())->toBe(2);
});

it('AC-017: submitting product_lines on that same record is rejected until conforming (D-5)', function () {
    $actor = invariantsOpportunityActor();
    $fixture = invariantsHistoricalNonConformingOpportunity();
    $opportunity = $fixture['opportunity'];
    Sanctum::actingAs($actor);

    $currentLines = $opportunity->productLines()->get(['business_function_id', 'product_category_id'])
        ->map(fn ($line): array => [
            'business_function_id' => $line->business_function_id,
            'product_category_id' => $line->product_category_id,
        ])->all();

    $this->patchJson("/api/opportunities/{$opportunity->id}", [
        'product_lines' => $currentLines,
    ])->assertStatus(422)->assertJsonValidationErrors('product_lines');

    expect($opportunity->productLines()->count())->toBe(2);
});
