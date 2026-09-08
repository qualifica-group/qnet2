<?php

use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\QuoteWorkflow;
use App\Models\QuoteWorkflowStatus;
use App\Models\Source;
use App\Services\Quotes\QuoteWorkflowResolver;
use App\Support\QuoteWorkflows\QuoteCriterionFieldRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// Spec 0092 — the `product_category_branch_id` criterion: matches the offer
// line's own category OR any ancestor of it (D-2), with the CLOSEST branch
// winning a specificity tie (D-3). Separate file from
// QuoteWorkflowResolverTest (the base spec's native-field ACs) and
// QuoteCriterionFieldRegistryTest (the custom-relation amendment), mirroring
// the split those two already use.
uses(TestCase::class, RefreshDatabase::class);

if (! function_exists('workflowWithSystemStatuses')) {
    /**
     * Signature MUST stay identical to the same-named helpers in
     * QuoteWorkflowResolverTest.php / QuoteCriterionFieldRegistryTest.php:
     * whichever file Pest loads first wins the definition (function_exists
     * guard), so a divergent signature there would break call sites here.
     *
     * @param  array<string, mixed>  $attributes
     */
    function workflowWithSystemStatuses(array $attributes = []): QuoteWorkflow
    {
        $workflow = QuoteWorkflow::factory()->create($attributes);

        foreach (['open', 'closed_won', 'closed_lost'] as $key) {
            QuoteWorkflowStatus::factory()->system($key)->create(['quote_workflow_id' => $workflow->id]);
        }

        return $workflow;
    }
}

if (! function_exists('workflowResolver')) {
    function workflowResolver(): QuoteWorkflowResolver
    {
        return app(QuoteWorkflowResolver::class);
    }
}

if (! function_exists('quoteForNewOpportunity')) {
    /**
     * @param  array<string, mixed>  $opportunityAttributes
     */
    function quoteForNewOpportunity(array $opportunityAttributes = []): Quote
    {
        $opportunity = Opportunity::factory()->create($opportunityAttributes);

        return Quote::factory()->create(['opportunity_id' => $opportunity->id]);
    }
}

if (! function_exists('revenueLineFor')) {
    function revenueLineFor(Quote $quote, Product $product): QuoteLine
    {
        return QuoteLine::factory()->create(['quote_id' => $quote->id, 'product_id' => $product->id]);
    }
}

if (! function_exists('consulenzaBranch')) {
    /**
     * The real shape of this install's tree, three levels deep:
     * `Consulenza` (a container: is_selectable false) > `ISO` > `10854`, the
     * leaf products actually sit on.
     *
     * @return array{root: ProductCategory, middle: ProductCategory, leaf: ProductCategory}
     */
    function consulenzaBranch(): array
    {
        $root = ProductCategory::factory()->create(['name' => 'Consulenza', 'parent_id' => null, 'is_selectable' => false]);
        $middle = ProductCategory::factory()->create(['name' => 'ISO', 'parent_id' => $root->id]);
        $leaf = ProductCategory::factory()->create(['name' => '10854', 'parent_id' => $middle->id]);

        return ['root' => $root, 'middle' => $middle, 'leaf' => $leaf];
    }
}

if (! function_exists('quoteOnCategory')) {
    function quoteOnCategory(ProductCategory $category): Quote
    {
        $quote = quoteForNewOpportunity();
        revenueLineFor($quote, Product::factory()->create(['category_id' => $category->id]));

        return $quote->fresh();
    }
}

if (! function_exists('criterionRegistry')) {
    function criterionRegistry(): QuoteCriterionFieldRegistry
    {
        return app(QuoteCriterionFieldRegistry::class);
    }
}

// ---------------------------------------------------------------------------
// Catalogue + quoteValues() — AC-001..AC-006
// ---------------------------------------------------------------------------

it('exposes product_category_branch_id in the criterion catalogue, right after product_category_id (AC-001)', function () {
    $fields = criterionRegistry()->allowedFields();
    $names = array_column($fields, 'field');
    $branch = collect($fields)->firstWhere('field', 'product_category_branch_id');

    expect($branch)->not->toBeNull()
        ->and($branch['source'])->toBe('native')
        ->and($branch['for_select_resource'])->toBe('product-category-branches')
        ->and($branch['multi_valued'])->toBeTrue()
        ->and($branch['inherited'])->toBeFalse()
        ->and(array_search('product_category_branch_id', $names, true))
        ->toBe(array_search('product_category_id', $names, true) + 1);
});

it('resolves the branch values to the line category AND every ancestor (AC-002)', function () {
    ['root' => $root, 'middle' => $middle, 'leaf' => $leaf] = consulenzaBranch();
    $quote = quoteOnCategory($leaf);

    $values = criterionRegistry()->quoteValues($quote, 'product_category_branch_id');

    expect($values)->toHaveCount(3)
        ->and($values)->toContain($leaf->id, $middle->id, $root->id);
});

it('leaves product_category_id an EXACT match on the line category (AC-003)', function () {
    ['middle' => $middle, 'leaf' => $leaf] = consulenzaBranch();
    $quote = quoteOnCategory($leaf);

    expect(criterionRegistry()->quoteValues($quote, 'product_category_id'))->toBe([$leaf->id])
        ->and(criterionRegistry()->quoteValues($quote, 'product_category_id'))->not->toContain($middle->id);
});

it('includes the category itself when it is a root, with no ancestors above it (AC-004)', function () {
    $root = ProductCategory::factory()->create(['parent_id' => null]);
    $quote = quoteOnCategory($root);

    expect(criterionRegistry()->quoteValues($quote, 'product_category_branch_id'))->toBe([$root->id]);
});

it('unions the branches of several offer lines without duplicates, ignoring cost lines (AC-005)', function () {
    ['root' => $root, 'middle' => $middle, 'leaf' => $leaf] = consulenzaBranch();
    $otherRoot = ProductCategory::factory()->create(['parent_id' => null]);
    $otherLeaf = ProductCategory::factory()->create(['parent_id' => $otherRoot->id]);

    $quote = quoteForNewOpportunity();
    revenueLineFor($quote, Product::factory()->create(['category_id' => $leaf->id]));
    revenueLineFor($quote, Product::factory()->create(['category_id' => $middle->id]));
    revenueLineFor($quote, Product::factory()->create(['category_id' => $otherLeaf->id]));

    // A cost line on a category outside both branches must contribute nothing.
    $costCategory = ProductCategory::factory()->create(['parent_id' => null]);
    QuoteLine::factory()->cost()->create([
        'quote_id' => $quote->id,
        'product_id' => Product::factory()->create(['category_id' => $costCategory->id])->id,
    ]);

    $values = criterionRegistry()->quoteValues($quote->fresh(), 'product_category_branch_id');

    expect($values)->toHaveCount(5)
        ->and($values)->toContain($leaf->id, $middle->id, $root->id, $otherLeaf->id, $otherRoot->id)
        ->and($values)->not->toContain($costCategory->id);
});

it('terminates on a corrupted parent_id cycle instead of looping forever (AC-006)', function () {
    $first = ProductCategory::factory()->create(['parent_id' => null]);
    $second = ProductCategory::factory()->create(['parent_id' => $first->id]);
    // Corrupted data the write-side anti-cycle guard would never persist.
    $first->forceFill(['parent_id' => $second->id])->save();

    $quote = quoteOnCategory($second);

    $values = criterionRegistry()->quoteValues($quote, 'product_category_branch_id');

    expect($values)->toHaveCount(2)
        ->and($values)->toContain($first->id, $second->id);
});

// ---------------------------------------------------------------------------
// resolve() + specificity — AC-007..AC-013
// ---------------------------------------------------------------------------

it('resolves a workflow whose branch criterion is an ANCESTOR of the line category (AC-007)', function () {
    ['root' => $root, 'leaf' => $leaf] = consulenzaBranch();
    $quote = quoteOnCategory($leaf);

    $workflow = workflowWithSystemStatuses();
    $workflow->criteria()->create(['field' => 'product_category_branch_id', 'value_id' => $root->id]);

    expect(workflowResolver()->resolve($quote)?->id)->toBe($workflow->id);
});

it('lets the CLOSEST ancestor win between two branch workflows, whatever the id order (AC-008)', function () {
    ['root' => $root, 'middle' => $middle, 'leaf' => $leaf] = consulenzaBranch();
    $quote = quoteOnCategory($leaf);

    // Far branch created FIRST, so a lower id would win under the old
    // id-asc tie-break: the depth comparison is what must decide here.
    $far = workflowWithSystemStatuses();
    $far->criteria()->create(['field' => 'product_category_branch_id', 'value_id' => $root->id]);

    $near = workflowWithSystemStatuses();
    $near->criteria()->create(['field' => 'product_category_branch_id', 'value_id' => $middle->id]);

    expect($far->id)->toBeLessThan($near->id)
        ->and(workflowResolver()->resolve($quote)?->id)->toBe($near->id);
});

it('lets the EXACT category criterion beat a branch criterion at equal criteria count (AC-009)', function () {
    ['middle' => $middle, 'leaf' => $leaf] = consulenzaBranch();
    $quote = quoteOnCategory($leaf);

    $branch = workflowWithSystemStatuses();
    $branch->criteria()->create(['field' => 'product_category_branch_id', 'value_id' => $middle->id]);

    $exact = workflowWithSystemStatuses();
    $exact->criteria()->create(['field' => 'product_category_id', 'value_id' => $leaf->id]);

    expect($branch->id)->toBeLessThan($exact->id)
        ->and(workflowResolver()->resolve($quote)?->id)->toBe($exact->id);
});

it('keeps the criteria COUNT as the first discriminant, above branch depth (AC-010)', function () {
    ['root' => $root, 'middle' => $middle, 'leaf' => $leaf] = consulenzaBranch();
    $source = Source::factory()->create();

    $quote = quoteForNewOpportunity(['source_id' => $source->id]);
    revenueLineFor($quote, Product::factory()->create(['category_id' => $leaf->id]));
    $quote = $quote->fresh();

    $nearButSingle = workflowWithSystemStatuses();
    $nearButSingle->criteria()->create(['field' => 'product_category_branch_id', 'value_id' => $middle->id]);

    $farButDouble = workflowWithSystemStatuses();
    $farButDouble->criteria()->create(['field' => 'product_category_branch_id', 'value_id' => $root->id]);
    $farButDouble->criteria()->create(['field' => 'source_id', 'value_id' => $source->id]);

    expect(workflowResolver()->resolve($quote)?->id)->toBe($farButDouble->id);
});

it('still tie-breaks by id asc at equal criteria count AND equal depth (AC-011)', function () {
    ['middle' => $middle, 'leaf' => $leaf] = consulenzaBranch();
    $quote = quoteOnCategory($leaf);

    $first = workflowWithSystemStatuses();
    $first->criteria()->create(['field' => 'product_category_branch_id', 'value_id' => $middle->id]);

    $second = workflowWithSystemStatuses();
    $second->criteria()->create(['field' => 'product_category_branch_id', 'value_id' => $middle->id]);

    expect(workflowResolver()->resolve($quote)?->id)->toBe(min($first->id, $second->id));
});

it('does not match a branch criterion when the quote has no offer line and its opportunity no product line (AC-013)', function () {
    ['root' => $root] = consulenzaBranch();
    $quote = quoteForNewOpportunity();

    $workflow = workflowWithSystemStatuses();
    $workflow->criteria()->create(['field' => 'product_category_branch_id', 'value_id' => $root->id]);

    expect(workflowResolver()->resolve($quote))->toBeNull();
});

// ---------------------------------------------------------------------------
// Perf — AC-014
// ---------------------------------------------------------------------------

it('reads the category tree projection ONCE, whatever the number of branch workflows (AC-014)', function () {
    ['root' => $root, 'middle' => $middle, 'leaf' => $leaf] = consulenzaBranch();

    $quote = quoteForNewOpportunity();
    revenueLineFor($quote, Product::factory()->create(['category_id' => $leaf->id]));
    revenueLineFor($quote, Product::factory()->create(['category_id' => $middle->id]));
    $quote = $quote->fresh();

    foreach ([$root->id, $middle->id, $leaf->id, $root->id, $middle->id] as $valueId) {
        workflowWithSystemStatuses()->criteria()->create([
            'field' => 'product_category_branch_id',
            'value_id' => $valueId,
        ]);
    }

    DB::enableQueryLog();
    $resolved = workflowResolver()->resolve($quote);
    $treeProjections = collect(DB::getQueryLog())
        ->filter(fn (array $entry): bool => str_contains($entry['query'], '"parent_id"') || str_contains($entry['query'], '`parent_id`'));
    DB::disableQueryLog();

    expect($resolved)->not->toBeNull()
        ->and($treeProjections)->toHaveCount(1);
});
