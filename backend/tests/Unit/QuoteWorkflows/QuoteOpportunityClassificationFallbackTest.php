<?php

use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\QuoteWorkflow;
use App\Models\QuoteWorkflowStatus;
use App\Services\Quotes\QuoteWorkflowResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// User directive 2026-09-08: an Offerta with NO revenue line of its own is
// classified by its Opportunita's `product_lines` — the SAME pairs the
// Gestione Richieste category tabs already group it under. Before the
// fallback such a request matched no category criterion at all and fell onto
// the GLOBAL default status set, showing a default "stato di lavorazione"
// for a request whose classification was perfectly well known.
// Own file, mirroring the split QuoteCategoryBranchCriterionTest /
// QuoteCriterionFieldRegistryTest already use against the base
// QuoteWorkflowResolverTest.
uses(TestCase::class, RefreshDatabase::class);

if (! function_exists('workflowWithSystemStatuses')) {
    /**
     * Signature MUST stay identical to the same-named helpers in
     * QuoteWorkflowResolverTest.php / QuoteCategoryBranchCriterionTest.php:
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

/**
 * A line-less Offerta whose Opportunita' carries the given pair — the exact
 * shape of a request created without an offer line.
 *
 * @return array{quote: Quote, function: BusinessFunction, category: ProductCategory}
 */
function lineLessQuoteClassifiedAs(?ProductCategory $category = null): array
{
    $function = BusinessFunction::factory()->create();
    $category ??= ProductCategory::factory()->create(['business_function_id' => $function->id]);

    $quote = quoteForNewOpportunity();
    OpportunityProductLine::factory()->create([
        'opportunity_id' => $quote->opportunity_id,
        'business_function_id' => $function->id,
        'product_category_id' => $category->id,
    ]);

    return ['quote' => $quote, 'function' => $function, 'category' => $category];
}

it('matches a product_category_id criterion through the opportunity when the offer has no revenue line', function () {
    ['quote' => $quote, 'category' => $category] = lineLessQuoteClassifiedAs();

    $workflow = workflowWithSystemStatuses();
    $workflow->criteria()->create(['field' => 'product_category_id', 'value_id' => $category->id]);

    $resolved = workflowResolver()->resolve($quote);

    expect($resolved?->id)->toBe($workflow->id);

    $target = workflowResolver()->targetStatus($quote, $resolved);

    expect($target->quote_workflow_id)->toBe($workflow->id)
        ->and($target->system_key)->toBe('open');
});

it('matches a business_function_id criterion through the opportunity product line pair', function () {
    ['quote' => $quote, 'function' => $function] = lineLessQuoteClassifiedAs();

    $workflow = workflowWithSystemStatuses();
    $workflow->criteria()->create(['field' => 'business_function_id', 'value_id' => $function->id]);

    expect(workflowResolver()->resolve($quote)?->id)->toBe($workflow->id);
});

it('matches a product_category_branch_id criterion on an ancestor of the opportunity category', function () {
    $root = ProductCategory::factory()->create(['parent_id' => null]);
    $leaf = ProductCategory::factory()->create(['parent_id' => $root->id]);

    ['quote' => $quote] = lineLessQuoteClassifiedAs($leaf);

    $workflow = workflowWithSystemStatuses();
    $workflow->criteria()->create(['field' => 'product_category_branch_id', 'value_id' => $root->id]);

    expect(workflowResolver()->resolve($quote)?->id)->toBe($workflow->id);
});

it('unions the categories of several opportunity product lines', function () {
    ['quote' => $quote] = lineLessQuoteClassifiedAs();
    $second = ProductCategory::factory()->create();
    OpportunityProductLine::factory()->create([
        'opportunity_id' => $quote->opportunity_id,
        'business_function_id' => BusinessFunction::factory()->create()->id,
        'product_category_id' => $second->id,
    ]);

    $workflow = workflowWithSystemStatuses();
    $workflow->criteria()->create(['field' => 'product_category_id', 'value_id' => $second->id]);

    expect(workflowResolver()->resolve($quote)?->id)->toBe($workflow->id);
});

it('never falls back once the offer carries a revenue line: its own category wins', function () {
    ['quote' => $quote, 'category' => $opportunityCategory] = lineLessQuoteClassifiedAs();

    $lineCategory = ProductCategory::factory()->create();
    revenueLineFor($quote, Product::factory()->create(['category_id' => $lineCategory->id]));
    $quote = $quote->fresh();

    $onOpportunityCategory = workflowWithSystemStatuses();
    $onOpportunityCategory->criteria()->create(['field' => 'product_category_id', 'value_id' => $opportunityCategory->id]);

    $onLineCategory = workflowWithSystemStatuses();
    $onLineCategory->criteria()->create(['field' => 'product_category_id', 'value_id' => $lineCategory->id]);

    expect(workflowResolver()->resolve($quote)?->id)->toBe($onLineCategory->id);
});

it('still resolves to the global default set when neither source carries a category', function () {
    $quote = quoteForNewOpportunity();
    $category = ProductCategory::factory()->create();

    $workflow = workflowWithSystemStatuses();
    $workflow->criteria()->create(['field' => 'product_category_id', 'value_id' => $category->id]);

    expect(workflowResolver()->resolve($quote))->toBeNull();
});
