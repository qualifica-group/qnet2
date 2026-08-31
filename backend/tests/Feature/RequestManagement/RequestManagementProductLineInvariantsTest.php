<?php

use App\Enums\CategoryManagementMode;
use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\Registry;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0077 INV-3 on the SAME shared `ProductLineSetValidator`, exercised
 * through the two request-management-specific channels: the create form
 * (AC-011 — mirrors the opportunities form's AC-010) and the inline cell
 * editor (AC-018), which has no FormRequest at all and reaches the validator
 * through `RequestProductLineWriter::apply()`. Rev.2 revoked INV-1/INV-2, so
 * the row cap of a `single` root is what both channels still refuse; AC-043
 * covers the other half — rows with different business functions go through
 * here exactly as they do on the opportunities form.
 */
uses(RefreshDatabase::class);

if (! function_exists('lineInvariantsRequestActor')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function lineInvariantsRequestActor(array $abilities = ['viewAny', 'create', 'update']): User
    {
        foreach (['viewAny', 'view', 'create', 'update'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("request-management.{$ability}");
        }

        return $user;
    }
}

it('AC-011: create via /api/request-management with two rows under a single-mode root -> 422 on product_lines', function () {
    $actor = lineInvariantsRequestActor();
    $registry = Registry::factory()->create();
    $businessFunction = BusinessFunction::factory()->create();
    $root = ProductCategory::factory()->create([
        'business_function_id' => $businessFunction->id,
        'management_mode' => CategoryManagementMode::Single,
    ]);
    $child = ProductCategory::factory()->childOf($root)->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management', [
        'registry_id' => $registry->id,
        'source_id' => Source::factory()->create()->id,
        'product_lines' => [
            ['business_function_id' => $businessFunction->id, 'product_category_id' => $root->id],
            ['business_function_id' => $businessFunction->id, 'product_category_id' => $child->id],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('product_lines');

    expect(Opportunity::count())->toBe(0);
    expect(Quote::count())->toBe(0);
});

it('AC-018: the inline cell editor refuses a second row on a single-mode root, same rule as the form', function () {
    $actor = lineInvariantsRequestActor();
    $businessFunction = BusinessFunction::factory()->create();
    $root = ProductCategory::factory()->create([
        'business_function_id' => $businessFunction->id,
        'management_mode' => CategoryManagementMode::Single,
    ]);
    $child = ProductCategory::factory()->childOf($root)->create();

    $opportunity = Opportunity::factory()->create();
    $opportunity->managers()->sync([$actor->id => ['position' => 2]]);
    OpportunityProductLine::factory()->create([
        'opportunity_id' => $opportunity->id,
        'business_function_id' => $businessFunction->id,
        'product_category_id' => $root->id,
    ]);
    $quote = Quote::factory()->for($opportunity)->create(['operator_id' => $actor->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'product_categories',
        'value' => [
            ['business_function_id' => $businessFunction->id, 'product_category_id' => $root->id],
            ['business_function_id' => $businessFunction->id, 'product_category_id' => $child->id],
        ],
    ])->assertStatus(422);

    expect($opportunity->productLines()->count())->toBe(1);
    $this->assertDatabaseHas('opportunity_product_lines', [
        'opportunity_id' => $opportunity->id,
        'product_category_id' => $root->id,
    ]);
});

it('AC-043 rev.2: the create form accepts rows with DIFFERENT business functions, like the opportunities form', function () {
    $actor = lineInvariantsRequestActor();
    $registry = Registry::factory()->create();
    $functionA = BusinessFunction::factory()->create();
    $functionB = BusinessFunction::factory()->create();
    $categoryA = ProductCategory::factory()->create(['business_function_id' => $functionA->id]);
    $categoryB = ProductCategory::factory()->create(['business_function_id' => $functionB->id]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management', [
        'registry_id' => $registry->id,
        'source_id' => Source::factory()->create()->id,
        'product_lines' => [
            ['business_function_id' => $functionA->id, 'product_category_id' => $categoryA->id],
            ['business_function_id' => $functionB->id, 'product_category_id' => $categoryB->id],
        ],
    ])->assertCreated();

    $this->assertDatabaseCount('opportunity_product_lines', 2);
});
