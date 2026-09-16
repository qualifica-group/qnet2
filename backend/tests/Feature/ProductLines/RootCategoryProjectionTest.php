<?php

use App\Models\BusinessFunction;
use App\Models\Campaign;
use App\Models\Opportunity;
use App\Models\ProductCategory;
use App\Models\Project;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * `root_category` on the card Resources' + the request-management grid's
 * `product_lines` projection (spec 0132, AC-011): the root of the tree the
 * line's category hangs from, resolved via CategoryRootResolver — on a
 * root category itself it coincides with `product_category`. Pure read-side
 * coverage: fixtures are built directly against the product-line models,
 * never through the (in-flight, spec 0132) write endpoints.
 */
uses(RefreshDatabase::class);

if (! function_exists('rootCategoryChain')) {
    /**
     * A depth-2 branch under a fresh root category, all sharing
     * $businessFunction — mirrors the real tree shape spec 0132's context
     * describes (a root, then contentless containers, then a leaf).
     *
     * @return array{root: ProductCategory, leaf: ProductCategory}
     */
    function rootCategoryChain(BusinessFunction $businessFunction): array
    {
        $root = ProductCategory::factory()->create(['business_function_id' => $businessFunction->id]);
        $middle = ProductCategory::factory()->childOf($root)->create(['business_function_id' => null]);
        $leaf = ProductCategory::factory()->childOf($middle)->create(['business_function_id' => null]);

        return ['root' => $root, 'leaf' => $leaf];
    }
}

if (! function_exists('rootCategoryUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function rootCategoryUserWith(string $module, array $abilities): User
    {
        foreach (['viewAny', 'view', 'viewAll'] as $ability) {
            Permission::findOrCreate("{$module}.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("{$module}.{$ability}");
        }

        return $user;
    }
}

it('OpportunityResource: product_lines exposes root_category, coinciding with product_category on a root line (AC-011)', function () {
    $function = BusinessFunction::factory()->create();
    $chain = rootCategoryChain($function);
    $opportunity = Opportunity::factory()->create();
    $opportunity->productLines()->create(['business_function_id' => $function->id, 'product_category_id' => $chain['leaf']->id]);
    $opportunity->productLines()->create(['business_function_id' => $function->id, 'product_category_id' => $chain['root']->id]);

    Sanctum::actingAs(rootCategoryUserWith('opportunities', ['view']));

    $lines = collect($this->getJson("/api/opportunities/{$opportunity->id}")->assertOk()->json('data.product_lines'));

    $leafLine = $lines->firstWhere('product_category.id', $chain['leaf']->id);
    expect($leafLine['root_category'])->toBe(['id' => $chain['root']->id, 'name' => $chain['root']->name]);

    $rootLine = $lines->firstWhere('product_category.id', $chain['root']->id);
    expect($rootLine['root_category'])->toBe($rootLine['product_category']);
});

it('ProjectResource: product_lines exposes root_category for a descendant category (AC-011)', function () {
    $function = BusinessFunction::factory()->create();
    $chain = rootCategoryChain($function);
    $project = Project::factory()->create();
    $project->productLines()->create(['business_function_id' => $function->id, 'product_category_id' => $chain['leaf']->id]);

    Sanctum::actingAs(rootCategoryUserWith('projects', ['view']));

    $line = $this->getJson("/api/projects/{$project->id}")->assertOk()->json('data.product_lines.0');

    expect($line['root_category'])->toBe(['id' => $chain['root']->id, 'name' => $chain['root']->name])
        ->and($line['product_category'])->toBe(['id' => $chain['leaf']->id, 'name' => $chain['leaf']->name]);
});

it('CampaignResource: standalone campaign product_lines exposes root_category (AC-011)', function () {
    $function = BusinessFunction::factory()->create();
    $chain = rootCategoryChain($function);
    $campaign = Campaign::factory()->create();
    // The default fixture already seeds one root-level line (CampaignFactory
    // configure()); this ADDS the descendant-category line under test,
    // leaving the auto-created one as an incidental root-coincidence case.
    $campaign->productLines()->create(['business_function_id' => $function->id, 'product_category_id' => $chain['leaf']->id]);

    Sanctum::actingAs(rootCategoryUserWith('campaigns', ['view']));

    $lines = collect($this->getJson("/api/campaigns/{$campaign->id}")->assertOk()->json('data.product_lines'));
    $line = $lines->firstWhere('product_category.id', $chain['leaf']->id);

    expect($line['root_category'])->toBe(['id' => $chain['root']->id, 'name' => $chain['root']->name]);
});

it('QuoteResource: product_lines mirrors the parent Opportunity, root_category included (AC-011)', function () {
    $function = BusinessFunction::factory()->create();
    $chain = rootCategoryChain($function);
    $opportunity = Opportunity::factory()->create();
    $opportunity->productLines()->create(['business_function_id' => $function->id, 'product_category_id' => $chain['leaf']->id]);
    $quote = Quote::factory()->for($opportunity)->create();

    Sanctum::actingAs(rootCategoryUserWith('quotes', ['view']));

    $line = $this->getJson("/api/quotes/{$quote->id}")->assertOk()->json('data.product_lines.0');

    expect($line['root_category'])->toBe(['id' => $chain['root']->id, 'name' => $chain['root']->name]);
});

it('RequestManagementResource: work-panel product_lines exposes root_category (AC-011)', function () {
    $function = BusinessFunction::factory()->create();
    $chain = rootCategoryChain($function);
    $opportunity = Opportunity::factory()->create();
    $opportunity->productLines()->create(['business_function_id' => $function->id, 'product_category_id' => $chain['leaf']->id]);
    $quote = Quote::factory()->for($opportunity)->create();

    Sanctum::actingAs(rootCategoryUserWith('request-management', ['view', 'viewAll']));

    $line = $this->getJson("/api/request-management/{$quote->id}")->assertOk()->json('data.product_lines.0');

    expect($line['root_category'])->toBe(['id' => $chain['root']->id, 'name' => $chain['root']->name]);
});

it('request-management grid row: product_categories carries root_category_id/root_category_name (AC-011)', function () {
    $function = BusinessFunction::factory()->create();
    $chain = rootCategoryChain($function);
    $opportunity = Opportunity::factory()->create();
    $opportunity->productLines()->create(['business_function_id' => $function->id, 'product_category_id' => $chain['leaf']->id]);
    $quote = Quote::factory()->for($opportunity)->create();

    Sanctum::actingAs(rootCategoryUserWith('request-management', ['viewAny', 'viewAll']));

    $rows = collect($this->postJson('/api/tables/request-management/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()->json('items'));
    $row = $rows->firstWhere('id', $quote->id);
    $line = collect($row['product_categories'])->firstWhere('product_category_id', $chain['leaf']->id);

    expect($line['root_category_id'])->toBe($chain['root']->id)
        ->and($line['root_category_name'])->toBe($chain['root']->name);
});
